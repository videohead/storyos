"""Workflow template loader with caching and validation."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any, Optional

logger = logging.getLogger(__name__)


class WorkflowTemplateError(Exception):
    """Raised when a workflow template is invalid or cannot be rendered."""


class WorkflowTemplate:
    """Represents a single ComfyUI workflow template."""

    def __init__(self, data: dict[str, Any]):
        self.name: str = data["name"]
        self.description: str = data.get("description", "")
        self.placeholders: list[str] = data.get("placeholders", [])
        self.nodes: dict[str, Any] = data["nodes"]
        self.output: dict[str, Any] = data.get("output", {"type": "image"})
        self.required_context_keys: list[str] = data.get(
            "required_context_keys", []
        )

        # Validate required fields
        if not self.name:
            raise WorkflowTemplateError("Template must have a 'name'")
        if not self.nodes:
            raise WorkflowTemplateError(
                f"Template '{self.name}' must have 'nodes'"
            )

    def render(self, context: dict[str, Any]) -> dict[str, Any]:
        """Render the template with the given context dict.

        Replaces __PLACEHOLDER__ tokens in node inputs with values from context.
        Also validates that all required context keys are present.
        """
        # Validate required context keys
        missing = [k for k in self.required_context_keys if k not in context]
        if missing:
            raise WorkflowTemplateError(
                f"Missing required context keys: {', '.join(missing)}"
            )

        # Deep copy nodes to avoid mutating the template
        rendered_nodes = self._deep_copy(self.nodes)
        rendered_nodes = self._substitute_placeholders(
            rendered_nodes, context
        )

        return {
            "prompt": rendered_nodes,
            "metadata": {
                "template_name": self.name,
                "description": self.description,
                "context_summary": {
                    k: context[k] for k in self.required_context_keys[:5]
                },
            },
        }

    def _substitute_placeholders(
        self, obj: Any, context: dict[str, Any]
    ) -> Any:
        """Recursively substitute __PLACEHOLDER__ tokens in a data structure."""
        if isinstance(obj, str):
            result = obj
            for key, value in context.items():
                placeholder = f"__{key.upper()}__"
                result = result.replace(placeholder, str(value))
            return result
        if isinstance(obj, dict):
            return {
                k: self._substitute_placeholders(v, context)
                for k, v in obj.items()
            }
        if isinstance(obj, list):
            return [
                self._substitute_placeholders(item, context) for item in obj
            ]
        return obj

    @staticmethod
    def _deep_copy(obj: Any) -> Any:
        """Simple deep copy using json round-trip."""
        import json

        return json.loads(json.dumps(obj))


class WorkflowTemplateLoader:
    """Loads and caches workflow templates from the templates directory."""

    def __init__(self, templates_dir: Optional[Path] = None):
        self.templates_dir = templates_dir or Path(__file__).parent / "templates"
        self._cache: dict[str, WorkflowTemplate] = {}
        self._load_all()

    def _load_all(self):
        """Load all JSON templates from the templates directory."""
        if not self.templates_dir.exists():
            logger.warning(
                "Templates directory not found: %s", self.templates_dir
            )
            return

        for path in sorted(self.templates_dir.glob("*.json")):
            try:
                with open(path, "r", encoding="utf-8") as f:
                    data = json.load(f)
                template = WorkflowTemplate(data)
                self._cache[template.name] = template
                logger.info(
                    "Loaded workflow template: %s (%s)",
                    template.name,
                    template.description,
                )
            except (json.JSONDecodeError, KeyError, WorkflowTemplateError) as e:
                logger.warning(
                    "Failed to load template %s: %s", path.name, e
                )

    def get(self, name: str) -> Optional[WorkflowTemplate]:
        """Get a template by name."""
        return self._cache.get(name)

    def list_templates(self) -> list[dict[str, str]]:
        """List all available templates with name and description."""
        return [
            {"name": t.name, "description": t.description}
            for t in self._cache.values()
        ]

    def reload(self):
        """Reload all templates from disk (useful for development)."""
        self._cache.clear()
        self._load_all()


# Global loader instance
_loader: Optional[WorkflowTemplateLoader] = None


def get_loader() -> WorkflowTemplateLoader:
    """Get or create the global workflow template loader."""
    global _loader
    if _loader is None:
        _loader = WorkflowTemplateLoader()
    return _loader


def build_workflow(
    template_name: str, context: dict[str, Any]
) -> dict[str, Any]:
    """Convenience function to build a rendered workflow from template name and context.

    This replaces the old hardcoded build_workflow() in tasks.py.
    """
    loader = get_loader()
    template = loader.get(template_name)
    if template is None:
        available = ", ".join(loader.list_templates())
        raise WorkflowTemplateError(
            f"Template '{template_name}' not found. Available: {available}"
        )
    return template.render(context)
