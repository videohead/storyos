"""Provider type interface for StoryOS orchestrator."""

from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any, Optional


class ProviderTypeError(Exception):
    """Base exception for provider type failures."""


class ProviderInterface(ABC):
    """Internal contract implemented by supported provider adapters."""

    provider_type: str = ""
    provider_version: str = "0.1.0"
    capability_schema_version: str = "1.0"

    @classmethod
    @abstractmethod
    def capability_descriptor(cls) -> dict[str, Any]:
        """Return a machine-readable capability descriptor."""

    @abstractmethod
    def submit(self, request: dict[str, Any], connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        """Submit a generation request to the remote provider."""

    @abstractmethod
    def poll(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        """Poll for provider-side job state."""

    @abstractmethod
    def cancel(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> bool:
        """Cancel a provider-side job if supported."""

    @abstractmethod
    def download_artifacts(self, remote_job_ref: str, connection: Optional[dict[str, Any]] = None) -> list[dict[str, Any]]:
        """Download or enumerate generated artifacts for ingestion."""

    def health_check(self, connection: Optional[dict[str, Any]] = None) -> dict[str, Any]:
        """Return non-destructive provider and connection health evidence."""
        return {
            "status": "unknown",
            "provider_type": self.provider_type,
            "reason": "provider health check is not implemented",
        }


# Compatibility name retained for architecture documents and existing adapters.
ProviderTypeInterface = ProviderInterface
