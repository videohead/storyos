"""Provider type registry and discovery for StoryOS orchestrator."""

from __future__ import annotations

import copy
from typing import Any

from providers.base import ProviderInterface
from providers.capability_loader import load_capability_descriptors


class ProviderRegistry:
    """Runtime registry for provider type implementations."""

    def __init__(self):
        self._providers: dict[str, type[ProviderInterface]] = {}

    def register(self, provider_cls: type[ProviderInterface]) -> None:
        provider_type = getattr(provider_cls, "provider_type", "").strip().lower()
        if not provider_type:
            raise ValueError("provider class must define non-empty provider_type")
        self._providers[provider_type] = provider_cls

    def has(self, provider_type: str) -> bool:
        return provider_type.strip().lower() in self._providers

    def get(self, provider_type: str) -> type[ProviderInterface]:
        key = provider_type.strip().lower()
        if key not in self._providers:
            raise KeyError(f"provider_type not registered: {provider_type}")
        return self._providers[key]

    def list_descriptors(self) -> list[dict[str, Any]]:
        descriptors: list[dict[str, Any]] = []
        capability_descriptors = load_capability_descriptors()
        for provider_type, provider_cls in self._providers.items():
            descriptor = capability_descriptors.get(provider_type)
            if descriptor is None:
                descriptor = provider_cls.capability_descriptor()
            else:
                descriptor = copy.deepcopy(descriptor)
            descriptor.setdefault("provider_type", provider_type)
            descriptor.setdefault("provider_version", getattr(provider_cls, "provider_version", "0.1.0"))
            descriptor.setdefault(
                "capability_schema_version",
                getattr(provider_cls, "capability_schema_version", "1.0"),
            )
            descriptors.append(descriptor)
        descriptors.sort(key=lambda item: item["provider_type"])
        return descriptors

    def supports_operation(self, provider_type: str, operation: str) -> bool:
        """Return whether a provider explicitly declares an operation."""
        descriptor = next(
            (
                item
                for item in self.list_descriptors()
                if item.get("provider_type") == provider_type.strip().lower()
            ),
            None,
        )
        return bool(descriptor and descriptor.get("operations", {}).get(operation, False))

    def health_check(
        self, provider_type: str, connection: dict[str, Any] | None = None
    ) -> dict[str, Any]:
        """Run a provider's non-destructive health check."""
        provider_cls = self.get(provider_type)
        provider = provider_cls()
        return provider.health_check(connection or {})
