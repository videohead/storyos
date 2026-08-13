"""Registry for provider-neutral StoryOS generation structures."""

from __future__ import annotations

import copy
import json
from pathlib import Path
from typing import Any, Optional


class StructureRegistryError(ValueError):
    """Raised when a generation structure catalog is invalid."""


class StructureRegistry:
    """Load and index versioned generation structures from JSON."""

    def __init__(self, catalog_path: Optional[Path] = None):
        self.catalog_path = catalog_path or Path(__file__).parent / "examples.json"
        self._structures: dict[tuple[str, str], dict[str, Any]] = {}
        self.reload()

    def reload(self) -> None:
        """Reload the structure catalog from disk."""
        try:
            catalog = json.loads(self.catalog_path.read_text(encoding="utf-8"))
        except FileNotFoundError as exc:
            raise StructureRegistryError(f"structure catalog not found: {self.catalog_path}") from exc
        except json.JSONDecodeError as exc:
            raise StructureRegistryError(f"invalid structure catalog JSON: {exc}") from exc

        structures = catalog.get("structures") if isinstance(catalog, dict) else None
        if not isinstance(structures, list):
            raise StructureRegistryError("structure catalog must contain a structures array")

        loaded: dict[tuple[str, str], dict[str, Any]] = {}
        for structure in structures:
            self._validate_identity(structure)
            key = (structure["structure_id"], structure["version"])
            if key in loaded:
                raise StructureRegistryError(
                    f"duplicate generation structure: {structure['structure_id']}@{structure['version']}"
                )
            loaded[key] = copy.deepcopy(structure)

        self._structures = loaded

    def get(self, structure_id: str, version: Optional[str] = None) -> dict[str, Any]:
        """Return a structure by ID, using the newest version when omitted."""
        matches = [
            (structure_version, structure)
            for (registered_id, structure_version), structure in self._structures.items()
            if registered_id == structure_id
        ]
        if not matches:
            raise KeyError(f"generation structure not registered: {structure_id}")

        if version is not None:
            try:
                return copy.deepcopy(self._structures[(structure_id, version)])
            except KeyError as exc:
                raise KeyError(f"generation structure not registered: {structure_id}@{version}") from exc

        selected_version, selected = max(matches, key=lambda item: self._version_key(item[0]))
        del selected_version
        return copy.deepcopy(selected)

    def has(self, structure_id: str, version: Optional[str] = None) -> bool:
        """Return whether a structure ID/version is registered."""
        if version is not None:
            return (structure_id, version) in self._structures
        return any(registered_id == structure_id for registered_id, _ in self._structures)

    def list(self) -> list[dict[str, str]]:
        """Return registered structure identities."""
        return [
            {"structure_id": structure_id, "version": version}
            for structure_id, version in sorted(self._structures)
        ]

    @staticmethod
    def _validate_identity(structure: Any) -> None:
        if not isinstance(structure, dict):
            raise StructureRegistryError("each generation structure must be an object")
        for field in ("structure_id", "version", "input", "output"):
            if not structure.get(field):
                raise StructureRegistryError(f"generation structure missing {field}")
        if not isinstance(structure["input"], dict) or not isinstance(structure["output"], dict):
            raise StructureRegistryError("generation structure input and output must be objects")

    @staticmethod
    def _version_key(version: str) -> tuple[int, int]:
        try:
            major, minor = version.split(".", 1)
            return int(major), int(minor)
        except ValueError as exc:
            raise StructureRegistryError(f"invalid structure version: {version}") from exc
