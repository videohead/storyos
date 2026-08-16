"""ComfyUI provider type descriptor for StoryOS orchestrator."""

from __future__ import annotations

import os
from typing import Any, Optional
from urllib.parse import quote, urlencode

import requests

from providers.base import ProviderTypeInterface


class ComfyUIProvider(ProviderTypeInterface):
    """Provider type for ComfyUI-backed generation workflows."""

    provider_type = "comfyui"
    provider_version = "0.1.0"
    capability_schema_version = "1.0"

    def __init__(self, endpoint_url: Optional[str] = None, client_id: Optional[str] = None):
        self.endpoint_url = (
            endpoint_url
            or os.getenv("COMFYUI_URL")
            or os.getenv("COMFY_URL")
            or "http://comfyui:8188"
        ).rstrip("/")
        self.client_id = client_id or "storyos-orchestrator"

    @classmethod
    def capability_descriptor(cls) -> dict[str, Any]:
        return {
            "provider_type": cls.provider_type,
            "provider_version": cls.provider_version,
            "capability_schema_version": cls.capability_schema_version,
            "structures": ["text_to_image", "image_to_image", "text_to_video", "image_to_video"],
            "input_constraints": {
                "mime_types": ["image/png", "image/jpeg", "video/mp4"],
                "max_image_count": 8,
                "max_video_count": 2,
                "max_audio_inputs": 1,
            },
            "output_constraints": {
                "mime_types": ["image/png", "image/jpeg", "video/mp4"],
            },
            "prompt_constraints": {
                "max_prompt_length": 8192,
                "negative_prompt_support": True,
                "seed_support": True,
            },
            "reference_support": {
                "image_references": True,
                "video_references": True,
                "start_frame_support": True,
                "end_frame_support": True,
            },
        }

    def submit(self, request: dict[str, Any], connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        connection = connection or {}
        endpoint_url = self._endpoint_url(connection)
        workflow = request.get("workflow") or request.get("prompt") or request
        if isinstance(workflow, dict) and isinstance(workflow.get("prompt"), dict):
            workflow = workflow["prompt"]
        submit_path = self._path(connection, "submit_path", "/prompt")
        response = requests.post(
            f"{endpoint_url}{submit_path}",
            json={"prompt": workflow, "client_id": connection.get("client_id", self.client_id)},
            headers=self._request_headers(connection),
            timeout=30,
        )
        response.raise_for_status()
        prompt_id = response.json().get("prompt_id")
        if not prompt_id:
            raise ProviderTypeError("ComfyUI submission did not return prompt_id")
        return {
            "remote_job_ref": str(prompt_id),
            "provider_type": self.provider_type,
            "endpoint_url": endpoint_url,
        }

    def poll(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        connection = connection or {}
        endpoint_url = self._endpoint_url(connection)
        history_path_template = str(
            connection.get("history_path_template")
            or connection.get("status_path_template")
            or "/history/{job_id}"
        )
        if not history_path_template.startswith("/"):
            history_path_template = "/" + history_path_template
        history_path = history_path_template.replace("{job_id}", quote(remote_job_ref, safe=""))
        response = requests.get(
            f"{endpoint_url}{history_path}",
            headers=self._request_headers(connection),
            timeout=30,
        )
        response.raise_for_status()
        payload = response.json().get(remote_job_ref) or {}
        outputs = payload.get("outputs") or {}
        status = payload.get("status") or {}
        status_name = str(status.get("status_str", "")).lower() if isinstance(status, dict) else ""
        if payload.get("exception_message") or status_name in {"error", "failed"}:
            state = "failed"
        elif outputs or status_name in {"success", "completed"}:
            state = "completed"
        else:
            state = "running"
        return {
            "remote_job_ref": remote_job_ref,
            "status": state,
            "outputs": outputs,
            "error": payload.get("exception_message"),
        }

    def cancel(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> bool:
        connection = connection or {}
        endpoint_url = self._endpoint_url(connection)
        cancel_path = self._path(connection, "cancel_path", "/interrupt").replace(
            "{job_id}", quote(remote_job_ref, safe="")
        )
        response = requests.post(
            f"{endpoint_url}{cancel_path}",
            headers=self._request_headers(connection),
            timeout=30,
        )
        return response.ok

    def health_check(self, connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        """Check ComfyUI reachability, runtime information, and node availability."""
        connection = connection or {}
        endpoint_url = self._endpoint_url(connection)
        headers = self._request_headers(connection)
        stats_path = self._path(connection, "system_stats_path", "/system_stats")
        object_info_path = self._path(connection, "object_info_path", "/object_info")
        required_nodes = set(connection.get("required_nodes") or [])
        evidence: dict[str, Any] = {
            "provider_type": self.provider_type,
            "endpoint_url": endpoint_url,
            "connector": str(connection.get("connector") or "local"),
            "status": "failed",
            "checks": {},
        }
        try:
            stats_response = requests.get(
                f"{endpoint_url}{stats_path}",
                headers=headers,
                timeout=10,
            )
            stats_response.raise_for_status()
            object_response = requests.get(
                f"{endpoint_url}{object_info_path}",
                headers=headers,
                timeout=10,
            )
            object_response.raise_for_status()
            available_nodes = set(object_response.json())
            missing_nodes = sorted(required_nodes - available_nodes)
            evidence["checks"] = {
                "endpoint": {"passed": True},
                "runtime": {"passed": True, "details": stats_response.json()},
                "nodes": {
                    "passed": not missing_nodes,
                    "required": sorted(required_nodes),
                    "missing": missing_nodes,
                    "available_count": len(available_nodes),
                },
            }
            evidence["status"] = "ready" if not missing_nodes else "degraded"
        except requests.RequestException as exc:
            evidence["status"] = "unreachable"
            evidence["checks"] = {"endpoint": {"passed": False, "error": str(exc)[:200]}}
        return evidence

    def download_artifacts(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> list[dict[str, Any]]:
        connection = connection or {}
        endpoint_url = self._endpoint_url(connection)
        view_path = self._path(connection, "view_path", "/view")
        poll = self.poll(remote_job_ref, connection)
        if poll["status"] != "completed":
            return []

        artifacts: list[dict[str, Any]] = []
        for node_output in poll.get("outputs", {}).values():
            if not isinstance(node_output, dict):
                continue
            for output_key in ("images", "gifs", "videos", "audio"):
                for item in node_output.get(output_key, []):
                    if not isinstance(item, dict) or not item.get("filename"):
                        continue
                    params = urlencode(
                        {
                            "filename": item["filename"],
                            "subfolder": item.get("subfolder", ""),
                            "type": item.get("type", "output"),
                        }
                    )
                    artifacts.append(
                        {
                            "uri": f"{endpoint_url}{view_path}?{params}",
                            "filename": item["filename"],
                            "mime_type": self._mime_type(output_key, item["filename"]),
                            "provider_type": self.provider_type,
                            "remote_job_ref": remote_job_ref,
                        }
                    )
        return artifacts

    @staticmethod
    def _mime_type(output_key: str, filename: str) -> str:
        extension = filename.rsplit(".", 1)[-1].lower() if "." in filename else ""
        if output_key in {"videos", "gifs"} or extension in {"mp4", "webm", "mov", "gif"}:
            return "video/mp4" if extension == "mp4" else "video/*"
        if output_key == "audio" or extension in {"mp3", "wav", "flac", "ogg"}:
            return "audio/*"
        return "image/png" if extension == "png" else "image/*"

    def _endpoint_url(self, connection: dict[str, Any]) -> str:
        return str(connection.get("endpoint_url") or self.endpoint_url).rstrip("/")

    @staticmethod
    def _path(connection: dict[str, Any], key: str, default: str) -> str:
        value = str(connection.get(key) or default).strip()
        if not value.startswith("/"):
            value = "/" + value
        return value

    @staticmethod
    def _request_headers(connection: dict[str, Any]) -> dict[str, str]:
        headers: dict[str, str] = {}
        raw_headers = connection.get("headers")
        if isinstance(raw_headers, dict):
            for key, value in raw_headers.items():
                if isinstance(key, str) and key.strip() and value is not None:
                    headers[key.strip()] = str(value).strip()

        token = str(
            connection.get("api_key")
            or connection.get("token")
            or connection.get("credential")
            or ""
        ).strip()
        connector = str(connection.get("connector") or "").strip().lower()
        auth_type = str(connection.get("auth_type") or "").strip().lower()

        if not auth_type:
            if connector in {"runcomfy", "comfydeploy", "comfy_cloud_bearer", "comfyui_cloud"}:
                auth_type = "bearer"
            elif connector in {"comfyicu", "comfy_cloud_api_key"}:
                auth_type = "x-api-key"

        if token and auth_type in {"bearer", "token"}:
            headers.setdefault("Authorization", "Bearer " + token)
        elif token and auth_type in {"x-api-key", "apikey", "api_key"}:
            headers.setdefault("X-API-Key", token)
        elif token and auth_type == "custom_header":
            header_name = str(connection.get("auth_header") or "").strip()
            if header_name:
                prefix = str(connection.get("auth_prefix") or "").strip()
                headers.setdefault(header_name, f"{prefix}{token}")

        return headers
