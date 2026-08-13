"""Lifecycle events emitted while a provider generation job executes."""

from __future__ import annotations

import logging
import time
from dataclasses import asdict, dataclass, field
from enum import StrEnum
from typing import Any, Callable

logger = logging.getLogger(__name__)


class ProviderEventType(StrEnum):
    REQUEST_RECEIVED = "provider.request.received"
    SUBMISSION_STARTED = "provider.submission.started"
    SUBMITTED = "provider.submitted"
    POLL_STARTED = "provider.poll.started"
    POLL_UPDATED = "provider.poll.updated"
    ARTIFACTS_AVAILABLE = "provider.artifacts.available"
    ARTIFACT_DOWNLOADED = "provider.artifact.downloaded"
    ASSET_INGESTED = "provider.asset.ingested"
    COMPLETED = "provider.completed"
    FAILED = "provider.failed"


@dataclass(frozen=True)
class ProviderEvent:
    """A serializable event describing one provider lifecycle transition."""

    event_type: str
    job_id: str
    provider_type: str
    connection_id: int | None = None
    remote_job_ref: str | None = None
    status: str | None = None
    progress: float | None = None
    payload: dict[str, Any] = field(default_factory=dict)
    occurred_at: float = field(default_factory=time.time)

    def as_dict(self) -> dict[str, Any]:
        return asdict(self)


EventHandler = Callable[[ProviderEvent], None]


class ProviderEventBus:
    """Publish lifecycle events to registered handlers and the logger."""

    def __init__(self) -> None:
        self._handlers: list[EventHandler] = []

    def subscribe(self, handler: EventHandler) -> None:
        if handler not in self._handlers:
            self._handlers.append(handler)

    def publish(self, event: ProviderEvent) -> None:
        logger.info(
            "provider_event type=%s job_id=%s provider_type=%s status=%s",
            event.event_type,
            event.job_id,
            event.provider_type,
            event.status,
        )
        for handler in tuple(self._handlers):
            try:
                handler(event)
            except Exception:
                logger.exception("Provider event handler failed for %s", event.event_type)


provider_event_bus = ProviderEventBus()


def emit_provider_event(
    event_type: ProviderEventType,
    *,
    job_id: str,
    provider_type: str,
    connection_id: int | None = None,
    remote_job_ref: str | None = None,
    status: str | None = None,
    progress: float | None = None,
    payload: dict[str, Any] | None = None,
) -> ProviderEvent:
    """Create and publish a lifecycle event."""
    event = ProviderEvent(
        event_type=event_type.value,
        job_id=job_id,
        provider_type=provider_type,
        connection_id=connection_id,
        remote_job_ref=remote_job_ref,
        status=status,
        progress=progress,
        payload=payload or {},
    )
    provider_event_bus.publish(event)
    return event
