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
from story_graph import StoryGraphContextBuilder, WordPressAPIError
from workflows.loader import WorkflowTemplateError, build_workflow

logger = get_task_logger(__name__)

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


# ── ComfyUI helpers ─────────────────────────────────────────────────────────


class ComfyUIError(Exception):
    """Raised when ComfyUI API calls fail."""


def submit_workflow(workflow_data: dict[str, Any]) -> str:
    """Submit a workflow to ComfyUI and return the prompt_id."""
    url = f"{COMFYUI_URL}/prompt"
    resp = requests.post(url, json=workflow_data, timeout=30)
    if resp.status_code != 200:
        raise ComfyUIError(
            f"ComfyUI POST /prompt failed: {resp.status_code} {resp.text[:500]}"
        )
    data = resp.json()
    return data["prompt_id"]


def poll_comfyui(
    prompt_id: str, poll_interval: float = 2.0, max_polls: int = 300
) -> dict[str, Any]:
    """Poll ComfyUI for workflow completion. Returns outputs or raises."""
    for i in range(max_polls):
        try:
            resp = requests.get(
                f"{COMFYUI_URL}/history/{prompt_id}", timeout=10
            )
            if resp.status_code == 200:
                data = resp.json()
                if prompt_id in data:
                    return data[prompt_id]
            time.sleep(poll_interval)
        except Exception as e:
            logger.warning("ComfyUI poll error (attempt %d): %s", i + 1, e)
            time.sleep(poll_interval)

    raise ComfyUIError(
        f"ComfyUI timed out after {max_polls} polls for prompt {prompt_id}"
    )


def get_comfyui_outputs(prompt_id: str) -> list[dict[str, Any]]:
    """Extract output file info from ComfyUI history."""
    history = poll_comfyui(prompt_id)
    outputs = history.get("outputs", {})

    files = []
    for node_id, node_output in outputs.items():
        for key, value in node_output.items():
            if key == "images" or key == "gifs":
                for img in value:
                    files.append(
                        {
                            "filename": img.get("filename", ""),
                            "subfolder": img.get("subfolder", ""),
                            "type": img.get("type", "output"),
                        }
                    )
    return files


# ── Celery tasks ────────────────────────────────────────────────────────────


class ComfyUIError(Exception):
    """Raised when ComfyUI operations fail."""


class GenerationError(Exception):
    """Raised when generation fails permanently."""


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
        if custom_params:
            context.update(custom_params)

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
            raise self.retry(exc=e, countdown=60 * (2 ** self.request.retries))

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
            output_files = get_comfyui_outputs(prompt_id)
        except ComfyUIError as e:
            logger.error("ComfyUI generation failed: %s", e)
            raise self.retry(exc=e, countdown=60 * (2 ** self.request.retries))

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

        uploaded_media = []
        for file_info in output_files:
            # Build full file path
            subfolder = file_info.get("subfolder", "")
            filename = file_info["filename"]
            if subfolder:
                filepath = f"/ ComfyUI/output/{subfolder}/{filename}"
            else:
                filepath = f"/ComfyUI/output/{filename}"

            try:
                media_obj = upload_media_to_wordpress(filepath, filename)
                uploaded_media.append(media_obj)
                logger.info(
                    "Uploaded %s to WordPress as media %s", filename, media_obj.get("id")
                )
            except Exception as e:
                logger.error(
                    "Failed to upload %s to WordPress: %s", filename, e
                )
                # Continue with other files even if one fails

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
            video_output_files=len(output_files),
            video_media_ids=[m.get("id") for m in uploaded_media],
        )

        return {
            "status": "completed",
            "post_id": post_id,
            "prompt_id": prompt_id,
            "output_files": output_files,
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
        raise self.retry(exc=e, countdown=60 * (2 ** self.request.retries))


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
