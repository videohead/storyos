"""Registry for reusable StoryOS generation templates."""

from __future__ import annotations

import copy
import json
from pathlib import Path
from typing import Any, Optional

from structures.registry import StructureRegistry


class TemplateRegistryError(ValueError):
    """Raised when a generation template catalog is invalid."""


class TemplateRegistry:
    """Load and index versioned generation templates from JSON.

    Templates are StoryOS-owned editorial configuration. They reference a
    registered generation structure and may carry default parameters, SCF
    field mappings, and reference bindings. They never encode provider API
    payloads; provider adapters own that translation.
    """

    def __init__(
        self,
        catalog_path: Optional[Path] = None,
        structure_registry: Optional[StructureRegistry] = None,
    ):
        self.catalog_path = catalog_path or Path(__file__).parent / "examples.json"
        self.structure_registry = structure_registry or StructureRegistry()
        self._templates: dict[tuple[str, str], dict[str, Any]] = {}
        self.reload()

    def reload(self) -> None:
        """Reload the template catalog from disk."""
        try:
            catalog = json.loads(self.catalog_path.read_text(encoding="utf-8"))
        except FileNotFoundError as exc:
            raise TemplateRegistryError(f"template catalog not found: {self.catalog_path}") from exc
        except json.JSONDecodeError as exc:
            raise TemplateRegistryError(f"invalid template catalog JSON: {exc}") from exc

        templates = catalog.get("templates") if isinstance(catalog, dict) else None
        if not isinstance(templates, list):
            raise TemplateRegistryError("template catalog must contain a templates array")

        loaded: dict[tuple[str, str], dict[str, Any]] = {}
        for template in templates:
            self._validate_identity(template)
            key = (template["template_id"], template["version"])
            if key in loaded:
                raise TemplateRegistryError(
                    f"duplicate generation template: {template['template_id']}@{template['version']}"
                )
            loaded[key] = copy.deepcopy(template)

        self._templates = loaded

    def get(self, template_id: str, version: Optional[str] = None) -> dict[str, Any]:
        """Return a template by ID, using the newest version when omitted."""
        matches = [
            (template_version, template)
            for (registered_id, template_version), template in self._templates.items()
            if registered_id == template_id
        ]
        if not matches:
            raise KeyError(f"generation template not registered: {template_id}")

        if version is not None:
            try:
                return copy.deepcopy(self._templates[(template_id, version)])
            except KeyError as exc:
                raise KeyError(f"generation template not registered: {template_id}@{version}") from exc

        selected_version, selected = max(matches, key=lambda item: self._version_key(item[0]))
        del selected_version
        return copy.deepcopy(selected)

    def has(self, template_id: str, version: Optional[str] = None) -> bool:
        """Return whether a template ID/version is registered."""
        if version is not None:
            return (template_id, version) in self._templates
        return any(registered_id == template_id for registered_id, _ in self._templates)

    def list(self) -> list[dict[str, str]]:
        """Return registered template identities."""
        return [
            {"template_id": template_id, "version": version}
            for template_id, version in sorted(self._templates)
        ]

    def resolve(self, template_id: str, version: Optional[str] = None) -> dict[str, Any]:
        """Return a template merged with its referenced structure.

        The resolved object is a normalized, provider-neutral request
        definition: the structure's input/output contracts plus the
        template's default parameters, reference bindings, and SCF mappings.
        """
        template = self.get(template_id, version)
        structure_ref = template["structure"]
        structure = self.structure_registry.get(
            structure_ref["structure_id"], structure_ref.get("version")
        )

        configuration = template.get("configuration", {})
        parameters = dict(configuration.get("parameters", {}))
        for name, definition in structure.get("parameters", {}).items():
            if name not in parameters and definition.get("default") is not None:
                parameters[name] = definition["default"]

        resolved = copy.deepcopy(template)
        resolved["structure"] = structure
        resolved["configuration"]["parameters"] = parameters
        return resolved

    @staticmethod
    def _validate_identity(template: Any) -> None:
        if not isinstance(template, dict):
            raise TemplateRegistryError("each generation template must be an object")
        for field in ("template_id", "version", "label"):
            if not template.get(field):
                raise TemplateRegistryError(f"generation template missing {field}")
        if not isinstance(template.get("structure"), dict) or not template["structure"].get("structure_id"):
            raise TemplateRegistryError("generation template structure must reference a structure_id")
        if not isinstance(template.get("configuration"), dict):
            raise TemplateRegistryError("generation template configuration must be an object")

    @staticmethod
    def _version_key(version: str) -> tuple[int, int]:
        try:
            major, minor = version.split(".", 1)
            return int(major), int(minor)
        except ValueError as exc:
            raise TemplateRegistryError(f"invalid template version: {version}") from exc
