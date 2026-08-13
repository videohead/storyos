"""Download and validate artifacts returned by generation providers."""

from __future__ import annotations

import hashlib
import mimetypes
import os
import tempfile
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

import requests


class ArtifactDownloadError(RuntimeError):
    """Raised when a provider artifact cannot be downloaded or validated."""


class ArtifactDownloader:
    """Materialize remote provider artifacts as validated local files."""

    def __init__(self, max_bytes: int | None = None, timeout: int = 120):
        self.max_bytes = max_bytes or int(os.getenv("STORYOS_MAX_ARTIFACT_BYTES", 2 * 1024 * 1024 * 1024))
        self.timeout = timeout

    def download(self, artifact: dict[str, Any]) -> dict[str, Any]:
        uri = str(artifact.get("uri", "")).strip()
        if not uri:
            raise ArtifactDownloadError("artifact URI is required")

        parsed = urlparse(uri)
        if parsed.scheme in ("http", "https"):
            path = self._download_http(uri, artifact)
        elif parsed.scheme == "s3":
            path = self._download_s3(uri)
        else:
            raise ArtifactDownloadError(f"unsupported artifact URI scheme: {parsed.scheme or 'none'}")

        result = dict(artifact)
        result.pop("headers", None)
        result["local_path"] = str(path)
        result["filename"] = result.get("filename") or Path(parsed.path).name or Path(path).name
        result["sha256"] = self._sha256(path)
        result["size_bytes"] = path.stat().st_size
        result["mime_type"] = result.get("mime_type") or mimetypes.guess_type(str(path))[0] or "application/octet-stream"
        return result

    def _download_http(self, uri: str, artifact: dict[str, Any]) -> Path:
        response = requests.get(
            uri,
            headers=artifact.get("headers") or {},
            stream=True,
            timeout=self.timeout,
        )
        response.raise_for_status()
        return self._write_stream(response.iter_content(chunk_size=1024 * 1024), response.headers.get("Content-Length"))

    def _download_s3(self, uri: str) -> Path:
        try:
            import boto3
        except Exception as exc:
            raise ArtifactDownloadError("boto3 is required to download S3 artifacts") from exc

        parsed = urlparse(uri)
        if not parsed.netloc or not parsed.path.strip("/"):
            raise ArtifactDownloadError("S3 artifact URI must include a bucket and key")

        bucket = parsed.netloc
        key = parsed.path.lstrip("/")
        with tempfile.NamedTemporaryFile(prefix="storyos-artifact-", delete=False) as handle:
            path = Path(handle.name)

        try:
            boto3.client("s3").download_file(bucket, key, str(path))
            self._validate_size(path.stat().st_size)
            return path
        except Exception as exc:
            path.unlink(missing_ok=True)
            if isinstance(exc, ArtifactDownloadError):
                raise
            raise ArtifactDownloadError(f"failed to download S3 artifact {uri}") from exc

    def _write_stream(self, chunks: Any, content_length: str | None) -> Path:
        if content_length and int(content_length) > self.max_bytes:
            raise ArtifactDownloadError("artifact exceeds configured size limit")

        with tempfile.NamedTemporaryFile(prefix="storyos-artifact-", delete=False) as handle:
            path = Path(handle.name)
            total = 0
            try:
                for chunk in chunks:
                    if not chunk:
                        continue
                    total += len(chunk)
                    self._validate_size(total)
                    handle.write(chunk)
            except Exception:
                path.unlink(missing_ok=True)
                raise

        return path

    def _validate_size(self, size: int) -> None:
        if size > self.max_bytes:
            raise ArtifactDownloadError("artifact exceeds configured size limit")

    @staticmethod
    def _sha256(path: Path) -> str:
        digest = hashlib.sha256()
        with path.open("rb") as handle:
            for chunk in iter(lambda: handle.read(1024 * 1024), b""):
                digest.update(chunk)
        return digest.hexdigest()