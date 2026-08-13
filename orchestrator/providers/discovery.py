"""Provider implementation and capability descriptor discovery tools."""

from __future__ import annotations

import copy
import json
import re
import tempfile
from pathlib import Path
from typing import Any, Optional

from providers.base import ProviderTypeInterface
from providers.capability_loader import (
    CAPABILITY_SCHEMA_VERSION,
    _capability_directory,
    _validate_descriptor,
    load_capability_descriptors,
)
from providers.loader import _provider_classes


_PROVIDER_TYPE_PATTERN = re.compile(r"^[a-z0-9][a-z0-9_-]*$")


def discover_providers(package_name: str = "providers") -> list[dict[str, Any]]:
    """Inventory executable providers and their JSON descriptor state."""
    import importlib
    import inspect
    import pkgutil

    package = importlib.import_module(package_name)
    descriptors = load_capability_descriptors(package_name)
    discovered: list[dict[str, Any]] = []

    for module_info in sorted(pkgutil.iter_modules(package.__path__), key=lambda item: item.name):
        if module_info.name.startswith("_") or module_info.name in {
            "base",
            "capability_loader",
            "discovery",
            "loader",
            "registry",
        }:
            continue
        module = importlib.import_module(f"{package_name}.{module_info.name}")
        for provider_cls in _provider_classes(module):
            provider_type = provider_cls.provider_type.strip().lower()
            descriptor = descriptors.get(provider_type)
            discovered.append(
                {
                    "provider_type": provider_type,
                    "module": module.__name__,
                    "provider_class": provider_cls.__name__,
                    "provider_version": provider_cls.provider_version,
                    "descriptor_present": descriptor is not None,
                    "descriptor_schema_version": (
                        descriptor.get("capability_schema_version") if descriptor else None
                    ),
                    "descriptor_model_count": len(descriptor.get("models", [])) if descriptor else 0,
                    "descriptor_status": "ready" if descriptor else "missing",
                }
            )

    return discovered


def scaffold_capability_descriptor(
    provider_type: str,
    provider_version: str = "0.1.0",
    package_name: str = "providers",
    overwrite: bool = False,
) -> Path:
    """Create a conservative descriptor scaffold for a discovered provider."""
    provider_type = provider_type.strip().lower()
    if not _PROVIDER_TYPE_PATTERN.fullmatch(provider_type):
        raise ValueError(f"invalid provider type: {provider_type}")

    directory = _capability_directory(package_name)
    directory.mkdir(parents=True, exist_ok=True)
    destination = directory / f"{provider_type}.json"
    if destination.exists() and not overwrite:
        raise FileExistsError(f"capability descriptor already exists: {destination}")

    descriptor = {
        "provider_type": provider_type,
        "provider_version": provider_version,
        "capability_schema_version": CAPABILITY_SCHEMA_VERSION,
        "discovery": {
            "mode": "connection",
            "endpoint": None,
            "refresh_interval_seconds": None,
            "notes": "Complete this descriptor from the provider's official API documentation and connection capabilities.",
        },
        "operations": {
            "generate": False,
            "download_artifacts": False,
            "poll": False,
            "cancel": False,
        },
        "models": [
            {
                "model_id": "connection-defined",
                "status": "instance_defined",
                "execution_topology": {
                    "mode": "external_adapter",
                    "comfyui_required": False,
                    "external_api_required": True,
                    "dependencies": ["connection-defined"],
                },
                "structures": [],
                "input_constraints": {
                    "mime_types": [],
                    "max_image_count": None,
                    "max_video_count": None,
                    "max_audio_inputs": None,
                    "prompt_max_characters": None,
                },
                "output_constraints": {
                    "mime_types": [],
                    "max_outputs_per_request": None,
                },
            }
        ],
        "sources": [],
    }
    _write_descriptor(destination, descriptor)
    return destination


def update_capability_descriptor(
    provider_type: str,
    updates: dict[str, Any],
    package_name: str = "providers",
    expected_provider_version: Optional[str] = None,
) -> Path:
    """Deep-merge validated updates into one descriptor using an atomic write."""
    provider_type = provider_type.strip().lower()
    if not _PROVIDER_TYPE_PATTERN.fullmatch(provider_type):
        raise ValueError(f"invalid provider type: {provider_type}")
    if not isinstance(updates, dict):
        raise TypeError("descriptor updates must be an object")
    if updates.get("provider_type", provider_type).strip().lower() != provider_type:
        raise ValueError("provider_type cannot be changed by an update")

    directory = _capability_directory(package_name)
    destination = directory / f"{provider_type}.json"
    descriptors = load_capability_descriptors(package_name)
    if provider_type not in descriptors:
        raise FileNotFoundError(f"capability descriptor not found: {destination}")

    descriptor = copy.deepcopy(descriptors[provider_type])
    if expected_provider_version is not None and descriptor["provider_version"] != expected_provider_version:
        raise ValueError(
            f"provider version mismatch: expected {expected_provider_version}, "
            f"found {descriptor['provider_version']}"
        )
    _deep_merge(descriptor, updates)
    _validate_descriptor(descriptor, destination)
    _write_descriptor(destination, descriptor)
    return destination


def _deep_merge(target: dict[str, Any], updates: dict[str, Any]) -> None:
    for key, value in updates.items():
        if isinstance(value, dict) and isinstance(target.get(key), dict):
            _deep_merge(target[key], value)
        else:
            target[key] = value


def _write_descriptor(destination: Path, descriptor: dict[str, Any]) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(
        "w",
        encoding="utf-8",
        dir=destination.parent,
        prefix=f".{destination.stem}.",
        suffix=".tmp",
        delete=False,
    ) as temporary:
        temporary.write(json.dumps(descriptor, indent=2, sort_keys=False))
        temporary.write("\n")
        temporary_path = Path(temporary.name)

    temporary_path.replace(destination)


def descriptor_for_provider(
    provider_cls: type[ProviderTypeInterface], package_name: str = "providers"
) -> dict[str, Any]:
    """Return JSON capabilities, falling back to the class descriptor."""
    provider_type = provider_cls.provider_type.strip().lower()
    descriptors = load_capability_descriptors(package_name)
    return copy.deepcopy(descriptors.get(provider_type, provider_cls.capability_descriptor()))
