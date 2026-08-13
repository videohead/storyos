"""Celery tasks for StoryOS orchestrator.

Handles asset generation workflows with:
- Template-based ComfyUI workflow rendering
- Story Graph context building
- Retry logic with exponential backoff
- Error handling and status tracking
- WordPress media upload and post updates
"""

from __future__ import annotations

import logging
import os
import time
from typing import Any, Optional

import requests
from celery import Celery
from celery.utils.log import get_task_logger

from models import GenerationContext, TaskStatus
from artifact_downloader import ArtifactDownloadError, ArtifactDownloader
from provider_events import ProviderEventType, emit_provider_event
from providers import ComfyUIProvider, NovaReelProvider, NovaReelProviderError, VeoProvider, VeoProviderError
from story_graph import StoryGraphContextBuilder, WordPressAPIError
from workflows.loader import WorkflowTemplateError, build_workflow

logger = get_task_logger(__name__)
artifact_downloader = ArtifactDownloader()

# ── Environment variables ───────────────────────────────────────────────────

WORDPRESS_URL = os.getenv("WORDPRESS_URL", "http://wordpress:80")
WORDPRESS_USER = os.getenv("WORDPRESS_USER") or os.getenv("WP_USERNAME")
WORDPRESS_APP_PASSWORD = os.getenv("WORDPRESS_APP_PASSWORD") or os.getenv(
    "WP_APP_PASSWORD"
)
COMFYUI_URL = os.getenv("COMFYUI_URL", "http://comfyui:8188")
REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379/0")

# ── Celery app ──────────────────────────────────────────────────────────────

celery_app = Celery("tasks")
celery_app.conf.broker_url = REDIS_URL
celery_app.conf.result_backend = REDIS_URL
celery_app.conf.task_track_started = True
celery_app.conf.worker_max_tasks_per_child = 100
celery_app.conf.task_soft_time_limit = 600  # 10 minutes
celery_app.conf.task_time_limit = 900  # 15 minutes hard limit

# ── WordPress helpers ───────────────────────────────────────────────────────


def wp_auth():
    return (WORDPRESS_USER, WORDPRESS_APP_PASSWORD)


def update_post_meta(post_id: int, meta: dict[str, Any]):
    """Update WordPress post meta via REST API."""
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/posts/{post_id}"
    resp = requests.post(
        url, json={"meta": meta}, auth=wp_auth(), timeout=30
    )
    if not resp.ok:
        logger.error(
            "WordPress update failed for post %d: %s %s",
            post_id,
            resp.status_code,
            resp.text[:500],
        )
        raise WordPressAPIError(
            f"WordPress update failed: {resp.status_code} {resp.text}"
        )
    return resp.json()


def update_post_status(post_id: int, status: str, **kwargs):
    """Update post meta with generation status and optional extras."""
    meta: dict[str, Any] = {"video_status": status}
    if kwargs:
        meta.update(kwargs)
    update_post_meta(post_id, meta)


def upload_media_to_wordpress(filepath: str, filename: str) -> dict[str, Any]:
    """Upload a media file to WordPress and return the media object."""
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/media"
    headers = {"Content-Disposition": f"attachment; filename={filename}"}

    with open(filepath, "rb") as f:
        resp = requests.post(
            url, headers=headers, data=f, auth=wp_auth(), timeout=60
        )
    resp.raise_for_status()
    return resp.json()


def upload_video_to_wordpress(
    filepath: str, filename: str
) -> dict[str, Any]:
    """Upload a video file to WordPress and return the media object."""
    url = f"{WORDPRESS_URL}/wp-json/wp/v2/media"
    headers = {"Content-Disposition": f"attachment; filename={filename}"}

    with open(filepath, "rb") as f:
        resp = requests.post(
            url, headers=headers, data=f, auth=wp_auth(), timeout=120
        )
    resp.raise_for_status()
    return resp.json()


def ingest_provider_artifacts(artifacts: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Download provider artifacts, upload videos, and clean up temp files."""
    ingested: list[dict[str, Any]] = []
    for artifact in artifacts:
        local_path = ""
        try:
            downloaded = artifact_downloader.download(artifact)
            local_path = downloaded["local_path"]
            media = upload_video_to_wordpress(local_path, downloaded["filename"])
            downloaded["wordpress_media_id"] = media.get("id")
            downloaded["wordpress_source_url"] = media.get("source_url")
            ingested.append(downloaded)
        finally:
            if local_path:
                try:
                    os.unlink(local_path)
                except FileNotFoundError:
                    pass
    return ingested


def emit_asset_events(
    artifacts: list[dict[str, Any]],
    *,
    job_id: str,
    provider_type: str,
    connection_id: int | None,
    remote_job_ref: str,
) -> None:
    """Emit normalized artifact and asset events after provider ingestion."""
    emit_provider_event(
        ProviderEventType.ARTIFACTS_AVAILABLE,
        job_id=job_id,
        provider_type=provider_type,
        connection_id=connection_id,
        remote_job_ref=remote_job_ref,
        payload={"count": len(artifacts)},
    )
    for artifact in artifacts:
        emit_provider_event(
            ProviderEventType.ARTIFACT_DOWNLOADED,
            job_id=job_id,
            provider_type=provider_type,
            connection_id=connection_id,
            remote_job_ref=remote_job_ref,
            payload={
                "filename": artifact.get("filename"),
                "mime_type": artifact.get("mime_type"),
                "size_bytes": artifact.get("size_bytes"),
                "sha256": artifact.get("sha256"),
            },
        )
        emit_provider_event(
            ProviderEventType.ASSET_INGESTED,
            job_id=job_id,
            provider_type=provider_type,
            connection_id=connection_id,
            remote_job_ref=remote_job_ref,
            payload={
                "filename": artifact.get("filename"),
                "mime_type": artifact.get("mime_type"),
                "wordpress_media_id": artifact.get("wordpress_media_id"),
                "download_url": artifact.get("wordpress_source_url"),
            },
        )


# ── ComfyUI helpers ─────────────────────────────────────────────────────────


class ComfyUIError(Exception):
    """Raised when ComfyUI API calls fail."""


def submit_workflow(workflow_data: dict[str, Any]) -> str:
    """Submit a workflow to ComfyUI and return the prompt_id."""
    try:
        return ComfyUIProvider(COMFYUI_URL).submit({"workflow": workflow_data})["remote_job_ref"]
    except Exception as exc:
        raise ComfyUIError(f"ComfyUI submission failed: {exc}") from exc


def poll_comfyui(
    prompt_id: str, poll_interval: float = 2.0, max_polls: int = 300
) -> dict[str, Any]:
    """Poll ComfyUI for workflow completion. Returns outputs or raises."""
    provider = ComfyUIProvider(COMFYUI_URL)
    for i in range(max_polls):
        try:
            result = provider.poll(prompt_id)
            if result["status"] == "completed":
                return {"outputs": result.get("outputs", {})}
            if result["status"] == "failed":
                raise ComfyUIError(result.get("error") or "ComfyUI workflow failed")
            time.sleep(poll_interval)
        except Exception as e:
            logger.warning("ComfyUI poll error (attempt %d): %s", i + 1, e)
            time.sleep(poll_interval)

    raise ComfyUIError(
        f"ComfyUI timed out after {max_polls} polls for prompt {prompt_id}"
    )


def get_comfyui_outputs(
    prompt_id: str,
    poll_interval: float = 2.0,
    max_polls: int = 300,
) -> list[dict[str, Any]]:
    """Extract output file info from ComfyUI history."""
    provider = ComfyUIProvider(COMFYUI_URL)
    for _ in range(max_polls):
        result = provider.poll(prompt_id)
        if result["status"] == "completed":
            return provider.download_artifacts(prompt_id)
        if result["status"] == "failed":
            raise ComfyUIError(result.get("error") or "ComfyUI workflow failed")
        time.sleep(poll_interval)

    raise ComfyUIError(f"ComfyUI timed out after {max_polls} polls for prompt {prompt_id}")


# ── Celery tasks ────────────────────────────────────────────────────────────


class ComfyUIError(Exception):
    """Raised when ComfyUI operations fail."""


class GenerationError(Exception):
    """Raised when generation fails permanently."""


def _policy_max_retries(params: dict[str, Any], default: int = 3) -> int:
    return max(1, int(params.get("max_retries", default)))


def _policy_retry_countdown(params: dict[str, Any], attempt: int, default_delay: int = 60) -> int:
    base = max(1, int(params.get("retry_delay_seconds", default_delay)))
    return base * (2 ** max(0, attempt))


@celery_app.task(
    bind=True,
    name="tasks.generate_veo_task",
    max_retries=4,
    default_retry_delay=90,
)
def generate_veo_task(
    self,
    post_id: int,
    workflow: str = "base",
    custom_params: Optional[dict[str, Any]] = None,
):
    """Generate a video using the Veo provider path."""
    params = dict(custom_params or {})
    job_id = str(self.request.id)
    connection_id = int(params["connection_id"]) if params.get("connection_id") else None

    try:
        emit_provider_event(
            ProviderEventType.REQUEST_RECEIVED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            status=TaskStatus.QUEUED.value,
            payload={"post_id": post_id, "workflow": workflow},
        )
        provider = VeoProvider(
            api_key=params.get("veo_api_key") or os.getenv("GOOGLE_API_KEY"),
            model=str(params.get("model_id") or params.get("model") or "veo-2.0-generate-001"),
        )

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.QUEUED.value,
                "message": "Preparing Veo request...",
                "progress": 10,
            },
        )

        prompt = str(params.get("prompt", "")).strip()
        if not prompt:
            raise GenerationError("Veo prompt is required")

        emit_provider_event(
            ProviderEventType.SUBMISSION_STARTED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            status=TaskStatus.PROCESSING.value,
        )
        submitted = provider.submit(
            {
                "prompt": prompt,
                "duration_seconds": int(params.get("duration_seconds", 8)),
                "aspect_ratio": str(params.get("aspect_ratio", "16:9")),
                "image": params.get("image"),
            }
        )
        operation_name = submitted["remote_job_ref"]
        emit_provider_event(
            ProviderEventType.SUBMITTED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            remote_job_ref=operation_name,
            status=TaskStatus.PROCESSING.value,
        )

        update_post_status(
            post_id,
            TaskStatus.PROCESSING.value,
            video_job_id=self.request.id,
            video_provider_type="veo",
            video_veo_operation=operation_name,
            video_veo_model=provider.model,
        )

        max_polls = int(params.get("max_polls", 180))
        poll_interval = float(params.get("poll_interval_seconds", 8))
        last_poll: dict[str, Any] = {}

        for attempt in range(max_polls):
            if attempt == 0:
                emit_provider_event(
                    ProviderEventType.POLL_STARTED,
                    job_id=job_id,
                    provider_type="veo",
                    connection_id=connection_id,
                    remote_job_ref=operation_name,
                    status=TaskStatus.PROCESSING.value,
                )
            poll = provider.poll(operation_name)
            last_poll = poll
            emit_provider_event(
                ProviderEventType.POLL_UPDATED,
                job_id=job_id,
                provider_type="veo",
                connection_id=connection_id,
                remote_job_ref=operation_name,
                status=str(poll.get("status", "unknown")),
                progress=min(95, 35 + int((attempt / max(1, max_polls)) * 55)),
            )

            if poll["status"] == "completed":
                break

            if poll["status"] == "failed":
                raise GenerationError(f"Veo operation failed: {poll.get('error')}")

            progress = min(95, 35 + int((attempt / max(1, max_polls)) * 55))
            self.update_state(
                state="PROGRESS",
                meta={
                    "status": TaskStatus.PROCESSING.value,
                    "message": "Waiting for Veo generation...",
                    "progress": progress,
                },
            )
            time.sleep(poll_interval)
        else:
            raise GenerationError("Veo polling timed out")

        artifacts = ingest_provider_artifacts(provider.download_artifacts(operation_name))
        emit_asset_events(
            artifacts,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            remote_job_ref=operation_name,
        )
        video_uri = artifacts[0]["uri"] if artifacts else ""
        media_ids = [artifact.get("wordpress_media_id") for artifact in artifacts]

        update_post_status(
            post_id,
            TaskStatus.COMPLETED.value,
            video_job_id=self.request.id,
            video_provider_type="veo",
            video_veo_operation=operation_name,
            video_output_uri=video_uri,
            video_output_files=len(artifacts),
            video_media_ids=media_ids,
        )

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.COMPLETED.value,
                "message": "Veo generation complete",
                "progress": 100,
            },
        )

        emit_provider_event(
            ProviderEventType.COMPLETED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            remote_job_ref=operation_name,
            status=TaskStatus.COMPLETED.value,
            progress=100,
            payload={"asset_count": len(artifacts)},
        )

        return {
            "status": "completed",
            "post_id": post_id,
            "provider_type": "veo",
            "workflow": workflow,
            "operation_name": operation_name,
            "artifacts": artifacts,
            "provider_poll": last_poll,
        }

    except VeoProviderError as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": str(e)[:200]},
        )
        logger.error("Veo provider error: %s", e)
        update_post_status(
            post_id,
            TaskStatus.FAILED.value,
            video_job_id=self.request.id,
            video_provider_type="veo",
            video_error=str(e)[:200],
        )
        raise GenerationError(str(e)) from e
    except GenerationError as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": str(e)[:200]},
        )
        raise
    except WordPressAPIError as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": f"WordPress error: {str(e)[:200]}"},
        )
        logger.error("WordPress API error during Veo task: %s", e)
        update_post_status(
            post_id,
            TaskStatus.FAILED.value,
            video_job_id=self.request.id,
            video_provider_type="veo",
            video_error=f"WordPress error: {str(e)[:200]}",
        )
        raise
    except Exception as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="veo",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": str(e)[:200]},
        )
        logger.exception("Unexpected error in generate_veo_task")
        countdown = _policy_retry_countdown(params, self.request.retries, default_delay=90)
        raise self.retry(
            exc=e,
            countdown=countdown,
            max_retries=_policy_max_retries(params, default=4),
        )


@celery_app.task(
    bind=True,
    name="tasks.generate_nova_reel_task",
    max_retries=3,
    default_retry_delay=60,
)
def generate_nova_reel_task(
    self,
    post_id: int,
    workflow: str = "base",
    custom_params: Optional[dict[str, Any]] = None,
):
    """Generate a video using the Nova Reel provider path.

    Expects `custom_params` to include at least:
    - prompt (TEXT_VIDEO / MULTI_SHOT_AUTOMATED), or
    - shots (MULTI_SHOT_MANUAL)
    - optional task_type, model_id, output_s3_uri, seed, duration_seconds, images
    """
    params = dict(custom_params or {})
    job_id = str(self.request.id)
    connection_id = int(params["connection_id"]) if params.get("connection_id") else None

    try:
        emit_provider_event(
            ProviderEventType.REQUEST_RECEIVED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            status=TaskStatus.QUEUED.value,
            payload={"post_id": post_id, "workflow": workflow},
        )
        provider = NovaReelProvider(region_name=os.getenv("AWS_REGION") or os.getenv("AWS_DEFAULT_REGION") or "us-east-1")

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.QUEUED.value,
                "message": "Preparing Nova Reel request...",
                "progress": 10,
            },
        )

        params.setdefault("workflow", workflow)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Submitting job to Nova Reel...",
                "progress": 30,
            },
        )

        emit_provider_event(
            ProviderEventType.SUBMISSION_STARTED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            status=TaskStatus.PROCESSING.value,
        )
        submitted = provider.submit(params)
        invocation_arn = submitted["remote_job_ref"]
        emit_provider_event(
            ProviderEventType.SUBMITTED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            remote_job_ref=invocation_arn,
            status=TaskStatus.PROCESSING.value,
        )

        update_post_status(
            post_id,
            TaskStatus.PROCESSING.value,
            video_job_id=self.request.id,
            video_provider_type="nova_reel",
            video_nova_invocation_arn=invocation_arn,
            video_nova_model_id=submitted.get("model_id"),
            video_nova_task_type=submitted.get("task_type"),
            video_nova_output_s3_uri=submitted.get("output_s3_uri"),
        )

        max_polls = int(params.get("max_polls", 120))
        poll_interval = float(params.get("poll_interval_seconds", 10))
        last_poll: dict[str, Any] = {}

        for attempt in range(max_polls):
            if attempt == 0:
                emit_provider_event(
                    ProviderEventType.POLL_STARTED,
                    job_id=job_id,
                    provider_type="nova_reel",
                    connection_id=connection_id,
                    remote_job_ref=invocation_arn,
                    status=TaskStatus.PROCESSING.value,
                )
            poll = provider.poll(invocation_arn)
            last_poll = poll
            provider_status = poll.get("provider_status", "Unknown")
            emit_provider_event(
                ProviderEventType.POLL_UPDATED,
                job_id=job_id,
                provider_type="nova_reel",
                connection_id=connection_id,
                remote_job_ref=invocation_arn,
                status=str(poll.get("status", "unknown")),
                progress=min(95, 40 + int((attempt / max(1, max_polls)) * 50)),
                payload={"provider_status": provider_status},
            )

            if poll["status"] == "completed":
                break

            if poll["status"] == "failed":
                message = str(poll.get("failure_message") or "Nova Reel job failed")
                update_post_status(
                    post_id,
                    TaskStatus.FAILED.value,
                    video_job_id=self.request.id,
                    video_provider_type="nova_reel",
                    video_error=message[:200],
                    video_nova_invocation_arn=invocation_arn,
                    video_nova_status=provider_status,
                )
                raise GenerationError(message)

            progress = min(95, 40 + int((attempt / max(1, max_polls)) * 50))
            self.update_state(
                state="PROGRESS",
                meta={
                    "status": TaskStatus.PROCESSING.value,
                    "message": f"Nova Reel job status: {provider_status}",
                    "progress": progress,
                },
            )
            time.sleep(poll_interval)
        else:
            update_post_status(
                post_id,
                TaskStatus.FAILED.value,
                video_job_id=self.request.id,
                video_provider_type="nova_reel",
                video_error="Nova Reel polling timed out",
                video_nova_invocation_arn=invocation_arn,
            )
            raise GenerationError("Nova Reel polling timed out")

        artifacts = ingest_provider_artifacts(provider.download_artifacts(invocation_arn))
        emit_asset_events(
            artifacts,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            remote_job_ref=invocation_arn,
        )
        video_uri = artifacts[0]["uri"] if artifacts else ""
        media_ids = [artifact.get("wordpress_media_id") for artifact in artifacts]

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.COMPLETED.value,
                "message": "Nova Reel generation complete",
                "progress": 100,
            },
        )

        update_post_status(
            post_id,
            TaskStatus.COMPLETED.value,
            video_job_id=self.request.id,
            video_provider_type="nova_reel",
            video_nova_invocation_arn=invocation_arn,
            video_nova_status=last_poll.get("provider_status", "Completed"),
            video_output_uri=video_uri,
            video_output_files=len(artifacts),
            video_media_ids=media_ids,
        )

        emit_provider_event(
            ProviderEventType.COMPLETED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            remote_job_ref=invocation_arn,
            status=TaskStatus.COMPLETED.value,
            progress=100,
            payload={"asset_count": len(artifacts)},
        )

        return {
            "status": "completed",
            "post_id": post_id,
            "provider_type": "nova_reel",
            "workflow": workflow,
            "invocation_arn": invocation_arn,
            "artifacts": artifacts,
        }

    except NovaReelProviderError as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": str(e)[:200]},
        )
        logger.error("Nova Reel provider error: %s", e)
        update_post_status(
            post_id,
            TaskStatus.FAILED.value,
            video_job_id=self.request.id,
            video_provider_type="nova_reel",
            video_error=str(e)[:200],
        )
        raise GenerationError(str(e)) from e
    except GenerationError as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": str(e)[:200]},
        )
        raise
    except WordPressAPIError as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": f"WordPress error: {str(e)[:200]}"},
        )
        logger.error("WordPress API error during Nova Reel task: %s", e)
        update_post_status(
            post_id,
            TaskStatus.FAILED.value,
            video_job_id=self.request.id,
            video_provider_type="nova_reel",
            video_error=f"WordPress error: {str(e)[:200]}",
        )
        raise
    except Exception as e:
        emit_provider_event(
            ProviderEventType.FAILED,
            job_id=job_id,
            provider_type="nova_reel",
            connection_id=connection_id,
            status=TaskStatus.FAILED.value,
            payload={"error": str(e)[:200]},
        )
        logger.exception("Unexpected error in generate_nova_reel_task")
        countdown = _policy_retry_countdown(params, self.request.retries, default_delay=120)
        raise self.retry(
            exc=e,
            countdown=countdown,
            max_retries=_policy_max_retries(params, default=5),
        )


@celery_app.task(
    bind=True,
    name="tasks.generate_video_task",
    max_retries=3,
    default_retry_delay=60,
)
def generate_video_task(
    self,
    post_id: int,
    workflow: str = "base",
    custom_params: Optional[dict[str, Any]] = None,
):
    """Generate an asset using a workflow template.

    Args:
        post_id: WordPress post ID to generate for
        workflow: Workflow template name (e.g., 'character-sheet', 'environment')
        custom_params: Override parameters (seed, steps, cfg, etc.)
    """
    params = dict(custom_params or {})
    try:
        # Initialize builders
        context_builder = StoryGraphContextBuilder(
            wordpress_url=WORDPRESS_URL,
            username=WORDPRESS_USER,
            app_password=WORDPRESS_APP_PASSWORD,
        )

        # Step 1: Build context from Story Graph
        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.QUEUED.value,
                "message": f"Building context for post {post_id}...",
                "progress": 10,
            },
        )

        context = context_builder.build_for_post(post_id)

        # Apply custom params overrides
        if params:
            context.update(params)

        context["workflow_template"] = workflow

        # Step 2: Render workflow template
        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": f"Rendering workflow template '{workflow}'...",
                "progress": 25,
            },
        )

        try:
            workflow_data = build_workflow(workflow, context)
        except WorkflowTemplateError as e:
            logger.error("Workflow template error: %s", e)
            update_post_status(
                post_id,
                TaskStatus.FAILED.value,
                video_error=str(e),
                video_job_id=self.request.id,
            )
            raise GenerationError(f"Workflow template error: {e}") from e

        # Step 3: Submit to ComfyUI
        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Submitting workflow to ComfyUI...",
                "progress": 40,
            },
        )

        try:
            prompt_id = submit_workflow(workflow_data)
        except ComfyUIError as e:
            logger.error("ComfyUI submission failed: %s", e)
            countdown = _policy_retry_countdown(params, self.request.retries, default_delay=60)
            raise self.retry(
                exc=e,
                countdown=countdown,
                max_retries=_policy_max_retries(params, default=3),
            )

        # Step 4: Poll for completion
        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Waiting for ComfyUI generation...",
                "progress": 50,
            },
        )

        try:
            output_files = get_comfyui_outputs(
                prompt_id,
                poll_interval=float(params.get("poll_interval_seconds", 2)),
                max_polls=int(params.get("max_polls", 300)),
            )
        except ComfyUIError as e:
            logger.error("ComfyUI generation failed: %s", e)
            countdown = _policy_retry_countdown(params, self.request.retries, default_delay=60)
            raise self.retry(
                exc=e,
                countdown=countdown,
                max_retries=_policy_max_retries(params, default=3),
            )

        if not output_files:
            logger.warning("No output files from ComfyUI for prompt %s", prompt_id)
            update_post_status(
                post_id,
                TaskStatus.FAILED.value,
                video_error="No output files generated",
                video_job_id=self.request.id,
            )
            raise GenerationError("No output files generated by ComfyUI")

        # Step 5: Upload to WordPress
        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": f"Uploading {len(output_files)} file(s) to WordPress...",
                "progress": 75,
            },
        )

        ingested_artifacts = ingest_provider_artifacts(output_files)
        uploaded_media = [
            {
                "id": artifact.get("wordpress_media_id"),
                "source_url": artifact.get("wordpress_source_url"),
            }
            for artifact in ingested_artifacts
        ]

        # Step 6: Mark as complete
        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.COMPLETED.value,
                "message": "Generation complete!",
                "progress": 100,
            },
        )

        update_post_status(
            post_id,
            TaskStatus.COMPLETED.value,
            video_job_id=self.request.id,
            video_prompt_id=prompt_id,
            video_output_files=len(ingested_artifacts),
            video_media_ids=[m.get("id") for m in uploaded_media],
        )

        return {
            "status": "completed",
            "post_id": post_id,
            "prompt_id": prompt_id,
            "output_files": ingested_artifacts,
            "uploaded_media": [m.get("id") for m in uploaded_media],
            "workflow": workflow,
        }

    except GenerationError:
        # Don't retry generation errors (template errors, no output, etc.)
        raise
    except WordPressAPIError as e:
        logger.error("WordPress API error: %s", e)
        update_post_status(
            post_id,
            TaskStatus.FAILED.value,
            video_error=f"WordPress error: {str(e)[:200]}",
            video_job_id=self.request.id,
        )
        raise
    except Exception as e:
        # Unknown error — retry
        logger.exception("Unexpected error in generate_video_task")
        countdown = _policy_retry_countdown(params, self.request.retries, default_delay=60)
        raise self.retry(
            exc=e,
            countdown=countdown,
            max_retries=_policy_max_retries(params, default=3),
        )


@celery_app.task(
    bind=True,
    name="tasks.generate_character_sheet",
    max_retries=3,
    default_retry_delay=60,
)
def generate_character_sheet_task(
    self,
    character_id: int,
    scene_id: Optional[int] = None,
    custom_params: Optional[dict[str, Any]] = None,
):
    """Generate a character sheet using the character-sheet workflow."""
    try:
        context_builder = StoryGraphContextBuilder(
            wordpress_url=WORDPRESS_URL,
            username=WORDPRESS_USER,
            app_password=WORDPRESS_APP_PASSWORD,
        )

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.QUEUED.value,
                "message": f"Building context for character {character_id}...",
                "progress": 10,
            },
        )

        context = context_builder.build_for_character(character_id, scene_id)

        if custom_params:
            context.update(custom_params)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Rendering character-sheet workflow...",
                "progress": 25,
            },
        )

        workflow_data = build_workflow("character-sheet", context)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Submitting to ComfyUI...",
                "progress": 40,
            },
        )

        prompt_id = submit_workflow(workflow_data)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Waiting for generation...",
                "progress": 50,
            },
        )

        output_files = get_comfyui_outputs(prompt_id)

        if not output_files:
            raise GenerationError("No output files generated")

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Uploading to WordPress...",
                "progress": 75,
            },
        )

        uploaded_media = []
        for file_info in output_files:
            filename = file_info["filename"]
            filepath = f"/ComfyUI/output/{filename}"
            media_obj = upload_media_to_wordpress(filepath, filename)
            uploaded_media.append(media_obj)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.COMPLETED.value,
                "message": "Character sheet generated!",
                "progress": 100,
            },
        )

        return {
            "status": "completed",
            "character_id": character_id,
            "prompt_id": prompt_id,
            "output_files": output_files,
            "uploaded_media": [m.get("id") for m in uploaded_media],
        }

    except Exception as e:
        logger.exception("Error in generate_character_sheet_task")
        raise self.retry(exc=e, countdown=60 * (2 ** self.request.retries))


@celery_app.task(
    bind=True,
    name="tasks.generate_environment",
    max_retries=3,
    default_retry_delay=60,
)
def generate_environment_task(
    self,
    location_id: int,
    custom_params: Optional[dict[str, Any]] = None,
):
    """Generate an environment/concept art using the environment workflow."""
    try:
        context_builder = StoryGraphContextBuilder(
            wordpress_url=WORDPRESS_URL,
            username=WORDPRESS_USER,
            app_password=WORDPRESS_APP_PASSWORD,
        )

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.QUEUED.value,
                "message": f"Building context for location {location_id}...",
                "progress": 10,
            },
        )

        context = context_builder.build_for_location(location_id)

        if custom_params:
            context.update(custom_params)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Rendering environment workflow...",
                "progress": 25,
            },
        )

        workflow_data = build_workflow("environment", context)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Submitting to ComfyUI...",
                "progress": 40,
            },
        )

        prompt_id = submit_workflow(workflow_data)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Waiting for generation...",
                "progress": 50,
            },
        )

        output_files = get_comfyui_outputs(prompt_id)

        if not output_files:
            raise GenerationError("No output files generated")

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.PROCESSING.value,
                "message": "Uploading to WordPress...",
                "progress": 75,
            },
        )

        uploaded_media = []
        for file_info in output_files:
            filename = file_info["filename"]
            filepath = f"/ComfyUI/output/{filename}"
            media_obj = upload_media_to_wordpress(filepath, filename)
            uploaded_media.append(media_obj)

        self.update_state(
            state="PROGRESS",
            meta={
                "status": TaskStatus.COMPLETED.value,
                "message": "Environment generated!",
                "progress": 100,
            },
        )

        return {
            "status": "completed",
            "location_id": location_id,
            "prompt_id": prompt_id,
            "output_files": output_files,
            "uploaded_media": [m.get("id") for m in uploaded_media],
        }

    except Exception as e:
        logger.exception("Error in generate_environment_task")
        raise self.retry(exc=e, countdown=60 * (2 ** self.request.retries))
