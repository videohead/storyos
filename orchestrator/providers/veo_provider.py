"""Google Veo 2 provider adapter for StoryOS orchestrator.

This adapter follows the public Gemini / Veo video generation flow:
- POST /v1beta/models/{model}:generateVideo
- GET /v1beta/operations/{operation_name}

It is intentionally small and queue-friendly so it can be used from the Celery
orchestrator without direct UI or REST coupling.
"""

from __future__ import annotations

import os
import time
from typing import Any, Optional

import requests

from providers.base import ProviderTypeError, ProviderTypeInterface


class VeoProviderError(ProviderTypeError):
    """Raised for Veo provider failures."""


class VeoProvider(ProviderTypeInterface):
    """Adapter for Google Veo 2 video generation."""

    provider_type = "veo"
    provider_version = "0.1.0"
    capability_schema_version = "1.0"

    def __init__(self, api_key: Optional[str] = None, model: str = "veo-2.0-generate-001"):
        self.api_key = api_key or os.getenv("GOOGLE_API_KEY")
        self.model = model
        self.base_url = os.getenv("GOOGLE_GEMINI_BASE_URL", "https://generativelanguage.googleapis.com")

    def _headers(self) -> dict[str, str]:
        if not self.api_key:
            raise VeoProviderError("Google API key is required")
        return {
            "Content-Type": "application/json",
            "x-goog-api-key": self.api_key,
        }

    def submit_generation(
        self,
        prompt: str,
        duration_seconds: int = 8,
        aspect_ratio: str = "16:9",
        image: Optional[dict[str, Any]] = None,
        **kwargs: Any,
    ) -> str:
        """Submit a generation request and return the operation name."""
        payload: dict[str, Any] = {
            "prompt": prompt,
            "durationSeconds": duration_seconds,
            "aspectRatio": aspect_ratio,
        }

        if image:
            payload["image"] = image

        payload.update(kwargs)

        url = f"{self.base_url}/v1beta/models/{self.model}:generateVideo"
        response = requests.post(url, json=payload, headers=self._headers(), timeout=60)
        response.raise_for_status()
        data = response.json()

        operation_name = data.get("name")
        if not operation_name:
            raise VeoProviderError("Veo response did not include an operation name")
        return operation_name

    def poll_generation(self, operation_name: str, poll_interval: float = 5.0, max_polls: int = 60) -> dict[str, Any]:
        """Poll a Veo operation until completion or failure."""
        for attempt in range(max_polls):
            url = f"{self.base_url}/v1beta/operations/{operation_name}"
            response = requests.get(url, headers=self._headers(), timeout=30)
            if response.status_code >= 400:
                payload = response.json() if hasattr(response, "json") else {}
                raise VeoProviderError(f"Veo poll failed: {payload}")

            payload = response.json()
            if payload.get("done"):
                return payload
            time.sleep(poll_interval)

        raise VeoProviderError(f"Veo operation {operation_name} timed out")

    def get_generation_status(self, operation_name: str) -> dict[str, Any]:
        """Fetch a single Veo operation status payload."""
        url = f"{self.base_url}/v1beta/operations/{operation_name}"
        response = requests.get(url, headers=self._headers(), timeout=30)
        if response.status_code >= 400:
            payload = response.json() if hasattr(response, "json") else {}
            raise VeoProviderError(f"Veo status check failed: {payload}")
        return response.json()

    def get_video_uri(self, operation_payload: dict[str, Any]) -> Optional[str]:
        """Extract the first generated video URI from a completed operation payload."""
        response = operation_payload.get("response") or {}
        generated_videos = response.get("generatedVideos") or []
        if not generated_videos:
            return None

        video = generated_videos[0].get("video") or {}
        return video.get("uri")

    @classmethod
    def capability_descriptor(cls) -> dict[str, Any]:
        return {
            "provider_type": cls.provider_type,
            "provider_version": cls.provider_version,
            "capability_schema_version": cls.capability_schema_version,
            "structures": ["text_to_video", "image_to_video"],
            "input_constraints": {
                "mime_types": ["image/png", "image/jpeg"],
                "max_image_count": 1,
                "max_video_count": 0,
                "max_audio_inputs": 0,
            },
            "output_constraints": {
                "mime_types": ["video/mp4"],
            },
            "aspect_ratios": ["16:9", "9:16", "1:1"],
            "video_constraints": {
                "min_duration_seconds": 1,
                "max_duration_seconds": 8,
            },
            "prompt_constraints": {
                "max_prompt_length": 4096,
                "negative_prompt_support": False,
                "seed_support": False,
            },
            "reference_support": {
                "image_references": True,
                "video_references": False,
                "start_frame_support": True,
                "end_frame_support": False,
            },
        }

    def submit(self, request: dict[str, Any], connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        prompt = str(request.get("prompt", "")).strip()
        if not prompt:
            raise VeoProviderError("prompt is required")

        duration_seconds = int(request.get("duration_seconds", 8))
        aspect_ratio = str(request.get("aspect_ratio", "16:9"))
        image = request.get("image")

        operation_name = self.submit_generation(
            prompt=prompt,
            duration_seconds=duration_seconds,
            aspect_ratio=aspect_ratio,
            image=image,
        )

        return {
            "remote_job_ref": operation_name,
            "provider_type": self.provider_type,
        }

    def poll(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        payload = self.get_generation_status(operation_name=remote_job_ref)
        done = bool(payload.get("done"))
        status = "completed" if done else "running"
        error_payload = payload.get("error") if isinstance(payload, dict) else None
        if error_payload:
            status = "failed"
        return {
            "remote_job_ref": remote_job_ref,
            "status": status,
            "error": error_payload,
            "raw": payload,
        }

    def cancel(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> bool:
        # Veo public API cancellation support is not exposed here.
        return False

    def download_artifacts(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> list[dict[str, Any]]:
        payload = self.poll_generation(operation_name=remote_job_ref)
        uri = self.get_video_uri(payload)
        if not uri:
            return []
        return [
            {
                "kind": "video",
                "uri": uri,
                "mime_type": "video/mp4",
                "filename": "veo-video.mp4",
                "headers": self._headers(),
            }
        ]
