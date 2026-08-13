"""Amazon Nova Reel provider type adapter for StoryOS orchestrator."""

from __future__ import annotations

import os
from typing import Any, Optional

from providers.base import ProviderTypeError, ProviderTypeInterface


class NovaReelProviderError(ProviderTypeError):
    """Raised for Nova Reel provider failures."""


class NovaReelProvider(ProviderTypeInterface):
    """Adapter for Amazon Nova Reel asynchronous video generation."""

    provider_type = "nova_reel"
    provider_version = "0.1.0"
    capability_schema_version = "1.0"

    TASK_TEXT_VIDEO = "TEXT_VIDEO"
    TASK_MULTI_SHOT_AUTOMATED = "MULTI_SHOT_AUTOMATED"
    TASK_MULTI_SHOT_MANUAL = "MULTI_SHOT_MANUAL"

    def __init__(self, region_name: Optional[str] = None):
        self.region_name = region_name or os.getenv("AWS_REGION") or os.getenv("AWS_DEFAULT_REGION") or "us-east-1"

    @classmethod
    def capability_descriptor(cls) -> dict[str, Any]:
        return {
            "provider_type": cls.provider_type,
            "provider_version": cls.provider_version,
            "capability_schema_version": cls.capability_schema_version,
            "model_ids": ["amazon.nova-reel-v1:0", "amazon.nova-reel-v1:1"],
            "structures": ["text_to_video", "image_to_video", "multi_shot_automated", "multi_shot_manual"],
            "input_constraints": {
                "mime_types": ["image/png", "image/jpeg"],
                "max_image_count": 20,
                "max_video_count": 0,
                "max_audio_inputs": 0,
            },
            "output_constraints": {
                "mime_types": ["video/mp4"],
            },
            "dimension_constraints": {
                "supported_dimensions": ["1280x720"],
            },
            "aspect_ratios": ["16:9"],
            "video_constraints": {
                "supported_fps": [24],
                "min_duration_seconds": 6,
                "max_duration_seconds": 120,
                "duration_step_seconds": 6,
            },
            "prompt_constraints": {
                "max_prompt_length": 4000,
                "negative_prompt_support": False,
                "seed_support": True,
            },
            "reference_support": {
                "image_references": True,
                "video_references": False,
                "start_frame_support": True,
                "end_frame_support": False,
            },
            "geographic_availability": {
                "regions": ["us-east-1"],
            },
        }

    def _runtime_client(self):
        try:
            import boto3  # Lazy import keeps module importable without boto3 installed.
        except Exception as exc:
            raise NovaReelProviderError("boto3 is required for Nova Reel provider") from exc

        return boto3.client("bedrock-runtime", region_name=self.region_name)

    @staticmethod
    def _validate_image(image: dict[str, Any]) -> None:
        fmt = str(image.get("format", "")).lower()
        if fmt not in ("png", "jpeg"):
            raise NovaReelProviderError("image format must be 'png' or 'jpeg'")

        source = image.get("source") or {}
        if not isinstance(source, dict):
            raise NovaReelProviderError("image.source must be an object")

        has_bytes = bool(source.get("bytes"))
        has_s3 = isinstance(source.get("s3Location"), dict) and bool(source.get("s3Location", {}).get("uri"))
        if not has_bytes and not has_s3:
            raise NovaReelProviderError("image.source must include either bytes or s3Location.uri")

    @classmethod
    def _build_model_input(cls, request: dict[str, Any]) -> dict[str, Any]:
        task_type = str(request.get("task_type", cls.TASK_TEXT_VIDEO)).upper()

        if task_type not in (
            cls.TASK_TEXT_VIDEO,
            cls.TASK_MULTI_SHOT_AUTOMATED,
            cls.TASK_MULTI_SHOT_MANUAL,
        ):
            raise NovaReelProviderError(f"unsupported task_type: {task_type}")

        seed = int(request.get("seed", 0))
        if seed < 0 or seed > 2147483646:
            raise NovaReelProviderError("seed must be between 0 and 2147483646")

        config: dict[str, Any] = {
            "fps": 24,
            "dimension": "1280x720",
            "seed": seed,
        }

        model_input: dict[str, Any] = {
            "taskType": task_type,
            "videoGenerationConfig": config,
        }

        if task_type == cls.TASK_TEXT_VIDEO:
            duration_seconds = int(request.get("duration_seconds", 6))
            if duration_seconds != 6:
                raise NovaReelProviderError("TEXT_VIDEO duration_seconds must be 6")
            config["durationSeconds"] = duration_seconds

            text = str(request.get("prompt", "")).strip()
            images = request.get("images") or []
            if not text and not images:
                raise NovaReelProviderError("TEXT_VIDEO requires prompt or one image")

            params: dict[str, Any] = {}
            if text:
                if len(text) > 512:
                    raise NovaReelProviderError("TEXT_VIDEO prompt max length is 512")
                params["text"] = text

            if images:
                if not isinstance(images, list) or len(images) != 1:
                    raise NovaReelProviderError("TEXT_VIDEO supports exactly one image")
                image = images[0]
                if not isinstance(image, dict):
                    raise NovaReelProviderError("images[0] must be an object")
                cls._validate_image(image)
                params["images"] = [image]

            model_input["textToVideoParams"] = params
            return model_input

        if task_type == cls.TASK_MULTI_SHOT_AUTOMATED:
            duration_seconds = int(request.get("duration_seconds", 12))
            if duration_seconds < 12 or duration_seconds > 120 or duration_seconds % 6 != 0:
                raise NovaReelProviderError("MULTI_SHOT_AUTOMATED duration_seconds must be 12..120 in multiples of 6")
            config["durationSeconds"] = duration_seconds

            text = str(request.get("prompt", "")).strip()
            if not text:
                raise NovaReelProviderError("MULTI_SHOT_AUTOMATED requires prompt")
            if len(text) > 4000:
                raise NovaReelProviderError("MULTI_SHOT_AUTOMATED prompt max length is 4000")

            model_input["multiShotAutomatedParams"] = {"text": text}
            return model_input

        shots = request.get("shots")
        if not isinstance(shots, list) or not shots:
            raise NovaReelProviderError("MULTI_SHOT_MANUAL requires shots array")
        if len(shots) > 20:
            raise NovaReelProviderError("MULTI_SHOT_MANUAL supports at most 20 shots")

        normalized_shots: list[dict[str, Any]] = []
        for i, shot in enumerate(shots):
            if not isinstance(shot, dict):
                raise NovaReelProviderError(f"shots[{i}] must be an object")

            text = str(shot.get("text", "")).strip()
            if not text:
                raise NovaReelProviderError(f"shots[{i}].text is required")
            if len(text) > 512:
                raise NovaReelProviderError(f"shots[{i}].text max length is 512")

            item: dict[str, Any] = {"text": text}
            image = shot.get("image")
            if image is not None:
                if not isinstance(image, dict):
                    raise NovaReelProviderError(f"shots[{i}].image must be an object")
                cls._validate_image(image)
                item["image"] = image

            normalized_shots.append(item)

        model_input["multiShotManualParams"] = {"shots": normalized_shots}
        return model_input

    @staticmethod
    def _resolve_model_id(task_type: str, request: dict[str, Any]) -> str:
        explicit = str(request.get("model_id", "")).strip()
        if explicit:
            return explicit

        if task_type in (NovaReelProvider.TASK_MULTI_SHOT_AUTOMATED, NovaReelProvider.TASK_MULTI_SHOT_MANUAL):
            return "amazon.nova-reel-v1:1"

        # Use v1:1 by default to support future expansion and parity.
        return "amazon.nova-reel-v1:1"

    @staticmethod
    def _resolve_output_s3_uri(request: dict[str, Any], connection: Optional[dict[str, Any]]) -> str:
        direct = str(request.get("output_s3_uri", "")).strip()
        if direct:
            return direct

        if connection and isinstance(connection, dict):
            nested = str(connection.get("output_s3_uri", "")).strip()
            if nested:
                return nested

        env_uri = str(os.getenv("NOVA_REEL_S3_OUTPUT_URI", "")).strip()
        if env_uri:
            return env_uri

        raise NovaReelProviderError("output_s3_uri is required (request, connection, or NOVA_REEL_S3_OUTPUT_URI)")

    def submit(self, request: dict[str, Any], connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        model_input = self._build_model_input(request)
        task_type = str(model_input["taskType"])
        model_id = self._resolve_model_id(task_type, request)
        output_s3_uri = self._resolve_output_s3_uri(request, connection)

        client = self._runtime_client()
        response = client.start_async_invoke(
            modelId=model_id,
            modelInput=model_input,
            outputDataConfig={
                "s3OutputDataConfig": {
                    "s3Uri": output_s3_uri,
                }
            },
        )

        invocation_arn = str(response.get("invocationArn", "")).strip()
        if not invocation_arn:
            raise NovaReelProviderError("Nova Reel response did not include invocationArn")

        return {
            "remote_job_ref": invocation_arn,
            "provider_type": self.provider_type,
            "model_id": model_id,
            "task_type": task_type,
            "output_s3_uri": output_s3_uri,
            "raw": response,
        }

    def poll(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        client = self._runtime_client()
        response = client.get_async_invoke(invocationArn=remote_job_ref)
        status = str(response.get("status", "Unknown"))

        mapped = "running"
        if status == "Completed":
            mapped = "completed"
        elif status == "Failed":
            mapped = "failed"
        elif status in ("InProgress", "Submitted"):
            mapped = "running"

        return {
            "remote_job_ref": remote_job_ref,
            "status": mapped,
            "provider_status": status,
            "failure_message": response.get("failureMessage"),
            "raw": response,
        }

    def cancel(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> bool:
        # The documented async flow does not expose cancellation in this adapter contract.
        return False

    def download_artifacts(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> list[dict[str, Any]]:
        client = self._runtime_client()
        response = client.get_async_invoke(invocationArn=remote_job_ref)
        if str(response.get("status")) != "Completed":
            return []

        output_cfg = response.get("outputDataConfig") or {}
        s3_cfg = output_cfg.get("s3OutputDataConfig") or {}
        s3_uri = str(s3_cfg.get("s3Uri", "")).strip()
        if not s3_uri:
            return []

        invocation_id = remote_job_ref.rsplit("/", 1)[-1]
        output_video_uri = s3_uri.rstrip("/") + f"/{invocation_id}/output.mp4"

        return [
            {
                "kind": "video",
                "uri": output_video_uri,
                "mime_type": "video/mp4",
                "filename": "nova-reel-video.mp4",
                "metadata": {
                    "output_root_s3_uri": s3_uri,
                    "invocation_arn": remote_job_ref,
                },
            }
        ]
