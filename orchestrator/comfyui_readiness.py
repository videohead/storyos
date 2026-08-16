"""Evidence-based readiness checks for ComfyUI connectors."""

from __future__ import annotations

import time
from typing import Any
from urllib.parse import quote, urlencode

import requests


class ComfyUIReadinessError(RuntimeError):
    """Raised when a ComfyUI connector cannot prove asset delivery readiness."""


def required_node_types(workflow: dict[str, Any]) -> set[str]:
    """Extract node class types required by a ComfyUI API-format workflow."""
    return {
        str(node.get("class_type"))
        for node in workflow.values()
        if isinstance(node, dict) and node.get("class_type")
    }


def _output_nodes(workflow: dict[str, Any]) -> set[str]:
    """Return output node types that should produce downloadable artifacts."""
    return {
        node_type
        for node_type in required_node_types(workflow)
        if node_type.startswith("Save") or node_type in {"PreviewImage", "PreviewVideo"}
    }


class ComfyUIReadinessChecker:
    """Prove connector dependencies and optional end-to-end asset retrieval."""

    def __init__(self, endpoint: str, timeout: float = 10.0, connection: dict[str, Any] | None = None):
        self.endpoint = endpoint.rstrip("/")
        self.timeout = timeout
        self.connection = connection or {}

    def check(self, workflow: dict[str, Any]) -> dict[str, Any]:
        """Check endpoint, runtime, node, and output-node readiness."""
        if not workflow:
            raise ComfyUIReadinessError("a rendered workflow is required")

        evidence: dict[str, Any] = {
            "endpoint": self.endpoint,
            "connector": str(self.connection.get("connector") or "local"),
            "checks": {},
            "ready": False,
            "proof_level": "static",
        }
        evidence["checks"]["endpoint"] = self._check_endpoint()
        evidence["checks"]["runtime"] = self._get_json("/system_stats")
        object_info = self._get_json("/object_info")
        required = sorted(required_node_types(workflow))
        available = set(object_info)
        missing = sorted(set(required) - available)
        output_nodes = sorted(_output_nodes(workflow))
        evidence["checks"]["nodes"] = {
            "required": required,
            "missing": missing,
            "available_count": len(available),
            "passed": not missing,
        }
        evidence["checks"]["output_nodes"] = {
            "required": output_nodes,
            "passed": bool(output_nodes),
        }
        evidence["ready"] = (
            evidence["checks"]["endpoint"]["passed"]
            and evidence["checks"]["nodes"]["passed"]
            and evidence["checks"]["output_nodes"]["passed"]
        )
        return evidence

    def smoke_test(self, workflow: dict[str, Any], poll_interval: float = 1.0, max_polls: int = 60) -> dict[str, Any]:
        """Submit a rendered workflow and prove at least one output is downloadable."""
        readiness = self.check(workflow)
        if not readiness["ready"]:
            raise ComfyUIReadinessError("static ComfyUI readiness checks failed")

        response = requests.post(
            f"{self.endpoint}{self._path('submit_path', '/prompt')}",
            json={"prompt": workflow, "client_id": "storyos-readiness"},
            headers=self._request_headers(),
            timeout=self.timeout,
        )
        response.raise_for_status()
        prompt_id = response.json().get("prompt_id")
        if not prompt_id:
            raise ComfyUIReadinessError("ComfyUI submission did not return prompt_id")

        for _ in range(max_polls):
            history_response = requests.get(
                f"{self.endpoint}{self._history_path(prompt_id)}",
                headers=self._request_headers(),
                timeout=self.timeout,
            )
            history_response.raise_for_status()
            history = history_response.json().get(prompt_id) or {}
            outputs = history.get("outputs") or {}
            artifacts = self._extract_artifacts(outputs)
            if artifacts:
                downloadable = [self._verify_download(artifact) for artifact in artifacts]
                return {
                    **readiness,
                    "ready": all(item["downloadable"] for item in downloadable),
                    "proof_level": "end_to_end",
                    "prompt_id": prompt_id,
                    "artifacts": downloadable,
                }
            time.sleep(poll_interval)

        raise ComfyUIReadinessError(
            f"ComfyUI prompt {prompt_id} completed without downloadable output"
        )

    def _check_endpoint(self) -> dict[str, Any]:
        response = requests.get(
            f"{self.endpoint}{self._path('history_probe_path', '/history/')}",
            headers=self._request_headers(),
            timeout=self.timeout,
        )
        return {
            "passed": response.ok or response.status_code == 404,
            "status_code": response.status_code,
        }

    def _get_json(self, path: str) -> dict[str, Any]:
        response = requests.get(
            f"{self.endpoint}{self._path_for_builtin(path)}",
            headers=self._request_headers(),
            timeout=self.timeout,
        )
        response.raise_for_status()
        payload = response.json()
        if not isinstance(payload, dict):
            raise ComfyUIReadinessError(f"ComfyUI {path} did not return an object")
        return payload

    @staticmethod
    def _extract_artifacts(outputs: dict[str, Any]) -> list[dict[str, Any]]:
        artifacts: list[dict[str, Any]] = []
        for node_output in outputs.values():
            if not isinstance(node_output, dict):
                continue
            for key in ("images", "gifs", "videos", "audio"):
                for item in node_output.get(key, []):
                    if isinstance(item, dict) and item.get("filename"):
                        artifacts.append(item)
        return artifacts

    def _verify_download(self, artifact: dict[str, Any]) -> dict[str, Any]:
        query = urlencode(
            {
                "filename": artifact["filename"],
                "subfolder": artifact.get("subfolder", ""),
                "type": artifact.get("type", "output"),
            }
        )
        response = requests.get(
            f"{self.endpoint}{self._path('view_path', '/view')}?{query}",
            headers=self._request_headers(),
            timeout=self.timeout,
            stream=True,
        )
        response.raise_for_status()
        first_chunk = next(response.iter_content(chunk_size=64 * 1024), b"")
        if not first_chunk:
            raise ComfyUIReadinessError(
                f"ComfyUI artifact download returned no bytes: {artifact['filename']}"
            )
        content_length = response.headers.get("Content-Length")
        return {
            "filename": artifact["filename"],
            "subfolder": artifact.get("subfolder", ""),
            "type": artifact.get("type", "output"),
            "downloadable": True,
            "status_code": response.status_code,
            "bytes_verified": len(first_chunk),
            "content_length": int(content_length) if content_length and content_length.isdigit() else None,
        }

    def _history_path(self, prompt_id: str) -> str:
        template = str(
            self.connection.get("history_path_template")
            or self.connection.get("status_path_template")
            or "/history/{job_id}"
        )
        if not template.startswith("/"):
            template = "/" + template
        return template.replace("{job_id}", quote(prompt_id, safe=""))

    def _path_for_builtin(self, default_path: str) -> str:
        if default_path == "/system_stats":
            return self._path("system_stats_path", "/system_stats")
        if default_path == "/object_info":
            return self._path("object_info_path", "/object_info")
        return self._path("", default_path)

    def _path(self, key: str, default: str) -> str:
        value = str(self.connection.get(key) or default).strip()
        if not value.startswith("/"):
            value = "/" + value
        return value

    def _request_headers(self) -> dict[str, str]:
        headers: dict[str, str] = {}
        raw_headers = self.connection.get("headers")
        if isinstance(raw_headers, dict):
            for key, value in raw_headers.items():
                if isinstance(key, str) and key.strip() and value is not None:
                    headers[key.strip()] = str(value).strip()

        token = str(
            self.connection.get("api_key")
            or self.connection.get("token")
            or self.connection.get("credential")
            or ""
        ).strip()
        connector = str(self.connection.get("connector") or "").strip().lower()
        auth_type = str(self.connection.get("auth_type") or "").strip().lower()

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
            header_name = str(self.connection.get("auth_header") or "").strip()
            if header_name:
                prefix = str(self.connection.get("auth_prefix") or "").strip()
                headers.setdefault(header_name, f"{prefix}{token}")

        return headers