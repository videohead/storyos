"""Validate StoryOS generation templates against the template contract."""

from __future__ import annotations

import copy
import json
import re
from pathlib import Path
from typing import Any

from structures.registry import StructureRegistry
from templates.registry import TemplateRegistry, TemplateRegistryError

_VERSION_PATTERN = re.compile(r"^[0-9]+\.[0-9]+$")
_ID_PATTERN = re.compile(r"^[a-z0-9][a-z0-9_-]*$")
_REFERENCE_ROLES = {
    "content",
    "style",
    "character",
    "pose",
    "start_frame",
    "end_frame",
    "audio",
    "mask",
}
_REFERENCE_SOURCES = {
    "scf_field": "scf_field",
    "story_graph": "entity_type",
    "asset": "asset_id",
    "fixed": "value",
}


class TemplateValidationError(ValueError):
    """Raised when a generation template violates the template contract."""


def load_schema(schema_path: Path | None = None) -> dict[str, Any]:
    """Load the template JSON Schema document."""
    path = schema_path or Path(__file__).parent / "template.schema.json"
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as exc:
        raise TemplateValidationError(f"template schema not found: {path}") from exc
    except json.JSONDecodeError as exc:
        raise TemplateValidationError(f"invalid template schema JSON: {exc}") from exc


def validate_template(
    template: dict[str, Any],
    structure_registry: StructureRegistry | None = None,
) -> dict[str, Any]:
    """Validate a template document and return a normalized copy.

    Checks the StoryOS-owned template contract: identity, version format,
    structure reference, parameter keys, reference roles, and SCF mapping
    shape. Provider-specific limits are intentionally not checked here;
    those belong to capability descriptors.
    """
    if not isinstance(template, dict):
        raise TemplateValidationError("generation template must be an object")

    structure_registry = structure_registry or StructureRegistry()

    template_id = str(template.get("template_id", "")).strip()
    if not _ID_PATTERN.match(template_id):
        raise TemplateValidationError(
            "template_id must be a lowercase slug (letters, digits, '-', '_')"
        )

    version = str(template.get("version", "")).strip()
    if not _VERSION_PATTERN.match(version):
        raise TemplateValidationError("version must use major.minor format, e.g. 1.0")

    label = str(template.get("label", "")).strip()
    if not label:
        raise TemplateValidationError("generation template requires a label")

    structure_ref = template.get("structure")
    if not isinstance(structure_ref, dict):
        raise TemplateValidationError("generation template requires a structure reference object")
    structure_id = str(structure_ref.get("structure_id", "")).strip()
    if not _ID_PATTERN.match(structure_id):
        raise TemplateValidationError("structure.structure_id must be a lowercase slug")
    structure_version = structure_ref.get("version")
    if structure_version is not None and not _VERSION_PATTERN.match(str(structure_version)):
        raise TemplateValidationError("structure.version must use major.minor format")

    try:
        structure = structure_registry.get(structure_id, structure_version)
    except KeyError as exc:
        raise TemplateValidationError(f"template references unregistered structure: {exc}") from exc

    provider_type = template.get("provider_type")
    if provider_type is not None and not _ID_PATTERN.match(str(provider_type)):
        raise TemplateValidationError("provider_type must be a lowercase slug or null")

    configuration = template.get("configuration")
    if not isinstance(configuration, dict):
        raise TemplateValidationError("generation template requires a configuration object")

    parameters = configuration.get("parameters", {})
    if not isinstance(parameters, dict):
        raise TemplateValidationError("configuration.parameters must be an object")
    declared_parameters = set(structure.get("parameters", {}))
    unknown_parameters = sorted(set(parameters) - declared_parameters)
    if unknown_parameters:
        raise TemplateValidationError(
            f"template declares parameters not defined by structure {structure_id}: "
            f"{', '.join(unknown_parameters)}"
        )

    declared_roles = {
        reference.get("role") for reference in structure.get("references", [])
    }
    bindings = configuration.get("references", [])
    if not isinstance(bindings, list):
        raise TemplateValidationError("configuration.references must be an array")
    for index, binding in enumerate(bindings):
        _validate_reference_binding(binding, index, declared_roles)

    scf_fields = configuration.get("scf_fields", {})
    if not isinstance(scf_fields, dict):
        raise TemplateValidationError("configuration.scf_fields must be an object")
    for field_name, scf_field in scf_fields.items():
        if not isinstance(scf_field, str) or not scf_field.strip():
            raise TemplateValidationError(
                f"configuration.scf_fields['{field_name}'] must be a non-empty SCF field name"
            )

    workflow = configuration.get("workflow")
    if workflow is not None:
        if not isinstance(workflow, dict):
            raise TemplateValidationError("configuration.workflow must be an object")
        workflow_id = str(workflow.get("workflow_id", "")).strip()
        if not workflow_id:
            raise TemplateValidationError("configuration.workflow requires workflow_id")

    normalized = copy.deepcopy(template)
    normalized["template_id"] = template_id
    normalized["version"] = version
    normalized["label"] = label
    normalized["structure"]["structure_id"] = structure_id
    return normalized


def _validate_reference_binding(
    binding: Any,
    index: int,
    declared_roles: set[str],
) -> None:
    if not isinstance(binding, dict):
        raise TemplateValidationError(f"configuration.references[{index}] must be an object")

    role = binding.get("role")
    if role not in _REFERENCE_ROLES:
        raise TemplateValidationError(
            f"configuration.references[{index}].role must be one of: {', '.join(sorted(_REFERENCE_ROLES))}"
        )
    if role not in declared_roles:
        raise TemplateValidationError(
            f"configuration.references[{index}].role '{role}' is not declared by the referenced structure"
        )

    source = binding.get("source")
    if source not in _REFERENCE_SOURCES:
        raise TemplateValidationError(
            f"configuration.references[{index}].source must be one of: {', '.join(sorted(_REFERENCE_SOURCES))}"
        )

    required_field = _REFERENCE_SOURCES[source]
    value = binding.get(required_field)
    if value is None or (isinstance(value, str) and not value.strip()):
        raise TemplateValidationError(
            f"configuration.references[{index}] with source '{source}' requires '{required_field}'"
        )


def validate_catalog(
    catalog_path: Path | None = None,
    structure_registry: StructureRegistry | None = None,
) -> list[dict[str, str]]:
    """Validate every template in a catalog and return its identities."""
    registry = TemplateRegistry(catalog_path, structure_registry)
    identities = registry.list()
    for identity in identities:
        validate_template(registry.get(identity["template_id"], identity["version"]), structure_registry)
    return identities
