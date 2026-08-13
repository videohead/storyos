"""Load versioned provider capability descriptors from JSON files."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any


CAPABILITY_SCHEMA_VERSION = "1.0"


def _capability_directory(package_name: str) -> Path:
    """Return the capability directory for an importable provider package."""
    import importlib

    package = importlib.import_module(package_name)
    package_paths = getattr(package, "__path__", None)
    if not package_paths:
        raise ValueError(f"provider package is not a package: {package_name}")
    return Path(next(iter(package_paths))) / "capabilities"


def _validate_descriptor(descriptor: dict[str, Any], source: Path) -> None:
    """Validate the fields required by the capability descriptor contract."""
    required = {
        "provider_type",
        "provider_version",
        "capability_schema_version",
        "discovery",
        "operations",
        "models",
    }
    missing = sorted(required - descriptor.keys())
    if missing:
        raise ValueError(f"{source}: missing required fields: {', '.join(missing)}")

    if descriptor["capability_schema_version"] != CAPABILITY_SCHEMA_VERSION:
        raise ValueError(
            f"{source}: unsupported capability schema version "
            f"{descriptor['capability_schema_version']}"
        )

    provider_type = descriptor["provider_type"]
    if not isinstance(provider_type, str) or not provider_type.strip():
        raise ValueError(f"{source}: provider_type must be a non-empty string")

    discovery = descriptor["discovery"]
    if not isinstance(discovery, dict) or discovery.get("mode") not in {
        "static",
        "runtime",
        "connection",
        "workflow",
    }:
        raise ValueError(f"{source}: discovery.mode is invalid")

    operations = descriptor["operations"]
    if not isinstance(operations, dict):
        raise ValueError(f"{source}: operations must be an object")
    for operation in ("generate", "download_artifacts"):
        if not isinstance(operations.get(operation), bool):
            raise ValueError(f"{source}: operations.{operation} must be boolean")

    models = descriptor["models"]
    if not isinstance(models, list) or not models:
        raise ValueError(f"{source}: models must be a non-empty array")

    for index, model in enumerate(models):
        if not isinstance(model, dict):
            raise ValueError(f"{source}: models[{index}] must be an object")
        for field in (
            "model_id",
            "execution_topology",
            "structures",
            "input_constraints",
            "output_constraints",
        ):
            if field not in model:
                raise ValueError(f"{source}: models[{index}] missing {field}")
        if not isinstance(model["model_id"], str) or not model["model_id"].strip():
            raise ValueError(f"{source}: models[{index}].model_id must be a non-empty string")
        if not isinstance(model["structures"], list):
            raise ValueError(f"{source}: models[{index}].structures must be an array")
        topology = model["execution_topology"]
        if not isinstance(topology, dict) or topology.get("mode") not in {
            "comfy_native",
            "comfy_partner",
            "external_adapter",
            "hybrid",
        }:
            raise ValueError(f"{source}: models[{index}].execution_topology.mode is invalid")
        if not isinstance(topology.get("comfyui_required"), bool):
            raise ValueError(
                f"{source}: models[{index}].execution_topology.comfyui_required must be boolean"
            )
        if not isinstance(model["input_constraints"], dict):
            raise ValueError(f"{source}: models[{index}].input_constraints must be an object")
        if not isinstance(model["output_constraints"], dict):
            raise ValueError(f"{source}: models[{index}].output_constraints must be an object")


def load_capability_descriptors(package_name: str = "providers") -> dict[str, dict[str, Any]]:
    """Load every provider capability JSON descriptor in a package."""
    directory = _capability_directory(package_name)
    if not directory.is_dir():
        raise FileNotFoundError(f"provider capability directory not found: {directory}")

    descriptors: dict[str, dict[str, Any]] = {}
    for source in sorted(directory.glob("*.json")):
        if source.name == "schema.json":
            continue
        try:
            descriptor = json.loads(source.read_text(encoding="utf-8"))
        except json.JSONDecodeError as exc:
            raise ValueError(f"{source}: invalid JSON: {exc}") from exc

        if not isinstance(descriptor, dict):
            raise ValueError(f"{source}: descriptor root must be an object")
        _validate_descriptor(descriptor, source)

        provider_type = descriptor["provider_type"].strip().lower()
        if provider_type in descriptors:
            raise ValueError(f"duplicate capability descriptor for provider: {provider_type}")
        descriptors[provider_type] = descriptor

    return descriptors


def load_capability_descriptor(provider_type: str, package_name: str = "providers") -> dict[str, Any]:
    """Load one provider capability descriptor by provider type."""
    key = provider_type.strip().lower()
    descriptors = load_capability_descriptors(package_name)
    try:
        return descriptors[key]
    except KeyError as exc:
        raise KeyError(f"capability descriptor not found for provider: {provider_type}") from exc
