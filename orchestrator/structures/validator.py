"""Validate StoryOS generation intent against a registered structure."""

from __future__ import annotations

from typing import Any

from structures.registry import StructureRegistry


class StructureValidationError(ValueError):
    """Raised when a generation intent does not match its structure."""


def validate_intent(
    intent: dict[str, Any],
    registry: StructureRegistry | None = None,
) -> dict[str, Any]:
    """Validate StoryOS-owned intent shape and return a normalized copy."""
    if not isinstance(intent, dict):
        raise StructureValidationError("generation intent must be an object")

    registry = registry or StructureRegistry()
    structure_id = str(intent.get("structure_id", "")).strip()
    if not structure_id:
        raise StructureValidationError("generation intent requires structure_id")

    structure = registry.get(structure_id, intent.get("version"))
    normalized = dict(intent)
    normalized["structure_id"] = structure["structure_id"]
    normalized["version"] = structure["version"]

    input_data = normalized.get("input", {})
    output_data = normalized.get("output", {})
    if not isinstance(input_data, dict) or not isinstance(output_data, dict):
        raise StructureValidationError("generation intent input and output must be objects")

    expected_input_modalities = set(structure["input"].get("modalities", []))
    actual_input_modalities = set(input_data.get("modalities", []))
    if not expected_input_modalities.issubset(actual_input_modalities):
        missing = sorted(expected_input_modalities - actual_input_modalities)
        raise StructureValidationError(
            f"generation intent is missing input modalities: {', '.join(missing)}"
        )

    expected_output_modalities = set(structure["output"].get("modalities", []))
    actual_output_modalities = set(output_data.get("modalities", []))
    if not expected_output_modalities.issubset(actual_output_modalities):
        missing = sorted(expected_output_modalities - actual_output_modalities)
        raise StructureValidationError(
            f"generation intent is missing output modalities: {', '.join(missing)}"
        )

    parameters = normalized.get("parameters", {})
    if not isinstance(parameters, dict):
        raise StructureValidationError("generation intent parameters must be an object")
    for name, definition in structure.get("parameters", {}).items():
        if definition.get("required") and not parameters.get(name):
            raise StructureValidationError(f"generation intent requires parameter: {name}")

    references = normalized.get("references", [])
    if not isinstance(references, list):
        raise StructureValidationError("generation intent references must be an array")
    required_roles = {
        reference.get("role")
        for reference in structure.get("references", [])
        if reference.get("required")
    }
    actual_roles = {reference.get("role") for reference in references}
    missing_roles = sorted(required_roles - actual_roles)
    if missing_roles:
        raise StructureValidationError(
            f"generation intent is missing references: {', '.join(missing_roles)}"
        )

    return normalized
