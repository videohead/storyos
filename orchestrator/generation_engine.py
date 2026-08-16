"""Provider-neutral Epic 5 generation request preparation."""

from __future__ import annotations

import copy
import hashlib
import json
import uuid
from datetime import datetime, timezone
from typing import Any, Optional

from pydantic import BaseModel, ConfigDict, Field

from providers.registry import ProviderRegistry
from templates.registry import TemplateRegistry


class GenerationIntent(BaseModel):
    """Editor-authored input to the pre-submit phase."""

    entity_type: str = Field(min_length=1)
    entity_id: int = Field(ge=1)
    template_id: str = Field(min_length=1)
    template_version: Optional[str] = None
    provider_type: str = Field(min_length=1)
    connection_id: int = Field(ge=1)
    scf_values: dict[str, Any] = Field(default_factory=dict)
    story_graph_context: dict[str, Any] = Field(default_factory=dict)
    connection: dict[str, Any] = Field(default_factory=dict)
    user_overrides: dict[str, Any] = Field(default_factory=dict)


class GenerationRequest(BaseModel):
    """Normalized, provider-neutral request sent to an adapter."""

    entity_type: str
    entity_id: int
    provider_type: str
    connection_id: int
    template_id: str
    template_version: str
    structure_id: str
    structure_version: str
    parameters: dict[str, Any] = Field(default_factory=dict)
    references: list[dict[str, Any]] = Field(default_factory=list)
    output: dict[str, Any] = Field(default_factory=dict)
    provenance: dict[str, Any] = Field(default_factory=dict)

    model_config = ConfigDict(frozen=True)


class ValidationIssue(BaseModel):
    code: str
    message: str
    field: Optional[str] = None


class PreparationResult(BaseModel):
    """Result of preparation, suitable for preview or durable job storage."""

    status: str
    snapshot_id: str
    request: Optional[GenerationRequest] = None
    issues: list[ValidationIssue] = Field(default_factory=list)
    provenance: dict[str, Any] = Field(default_factory=dict)


class GenerationPreparationService:
    """Compile editorial configuration without causing provider side effects."""

    def __init__(self, template_registry: Optional[TemplateRegistry] = None, provider_registry: Optional[ProviderRegistry] = None):
        self.templates = template_registry or TemplateRegistry()
        self.providers = provider_registry or ProviderRegistry()
        self._snapshots: dict[str, PreparationResult] = {}

    def prepare(self, intent: GenerationIntent, *, persist: bool = True) -> PreparationResult:
        issues: list[ValidationIssue] = []
        try:
            template = self.templates.resolve(intent.template_id, intent.template_version)
        except (KeyError, ValueError) as exc:
            return self._blocked([ValidationIssue(code="template_not_found", message=str(exc))], persist)

        structure = template["structure"]
        configuration = template.get("configuration", {})
        template_parameters = copy.deepcopy(configuration.get("parameters", {}))
        scf_values = {**intent.story_graph_context, **intent.scf_values}
        mapped = {
            parameter: scf_values[field]
            for parameter, field in configuration.get("scf_fields", {}).items()
            if field in scf_values and scf_values[field] is not None
        }
        parameters = {**template_parameters, **mapped}
        connection_policy = self._public_connection(intent.connection.get("policy", intent.connection))
        parameters = {**parameters, **connection_policy, **intent.user_overrides}

        references, reference_issues = self._resolve_references(configuration.get("references", []), scf_values)
        issues.extend(reference_issues)
        issues.extend(self._validate_parameters(structure, parameters))
        issues.extend(self._validate_provider(intent, structure, parameters))

        provenance = {
            "prepared_at": datetime.now(timezone.utc).isoformat(),
            "template": {"id": template["template_id"], "version": template["version"]},
            "structure": {"id": structure["structure_id"], "version": structure["version"]},
            "provider_type": intent.provider_type.lower(),
            "connection_id": intent.connection_id,
            "sources": ["structure_defaults", "template_parameters", "scf_values", "connection_policy", "user_overrides"],
        }
        request = GenerationRequest(
            entity_type=intent.entity_type,
            entity_id=intent.entity_id,
            provider_type=intent.provider_type.lower(),
            connection_id=intent.connection_id,
            template_id=template["template_id"],
            template_version=template["version"],
            structure_id=structure["structure_id"],
            structure_version=structure["version"],
            parameters=parameters,
            references=references,
            output=copy.deepcopy(structure["output"]),
            provenance=provenance,
        )
        result = PreparationResult(
            status="blocked" if issues else "ready",
            snapshot_id=self._snapshot_id(request),
            request=request if not issues else None,
            issues=issues,
            provenance=provenance,
        )
        if persist:
            self._snapshots[result.snapshot_id] = result.model_copy(deep=True)
        return result

    def get_snapshot(self, snapshot_id: str) -> PreparationResult:
        try:
            return self._snapshots[snapshot_id].model_copy(deep=True)
        except KeyError as exc:
            raise KeyError(f"generation snapshot not found: {snapshot_id}") from exc

    def _blocked(self, issues: list[ValidationIssue], persist: bool) -> PreparationResult:
        result = PreparationResult(status="blocked", snapshot_id=str(uuid.uuid4()), issues=issues)
        if persist:
            self._snapshots[result.snapshot_id] = result.model_copy(deep=True)
        return result

    @staticmethod
    def _public_connection(connection: dict[str, Any]) -> dict[str, Any]:
        return {key: value for key, value in connection.items() if key not in {"api_key", "api_secret", "token", "password", "secret"}}

    @staticmethod
    def _resolve_references(bindings: list[dict[str, Any]], values: dict[str, Any]) -> tuple[list[dict[str, Any]], list[ValidationIssue]]:
        references: list[dict[str, Any]] = []
        issues: list[ValidationIssue] = []
        for binding in bindings:
            field = binding.get("scf_field")
            value = values.get(field)
            if value is None:
                if binding.get("required"):
                    issues.append(ValidationIssue(code="missing_reference", field=field, message=f"Required reference field is missing: {field}"))
                continue
            references.append({"role": binding.get("role"), "value": value, "source": "scf_field", "field": field})
        return references, issues

    @staticmethod
    def _validate_parameters(structure: dict[str, Any], parameters: dict[str, Any]) -> list[ValidationIssue]:
        return [
            ValidationIssue(code="missing_parameter", field=name, message=f"Required parameter is missing: {name}")
            for name, definition in structure.get("parameters", {}).items()
            if definition.get("required") and (name not in parameters or parameters[name] in (None, ""))
        ]

    def _validate_provider(self, intent: GenerationIntent, structure: dict[str, Any], parameters: dict[str, Any]) -> list[ValidationIssue]:
        if not self.providers.has(intent.provider_type):
            return [ValidationIssue(code="provider_not_found", field="provider_type", message=f"Provider is not registered: {intent.provider_type}")]
        descriptor = next(item for item in self.providers.list_descriptors() if item["provider_type"] == intent.provider_type.lower())
        issues: list[ValidationIssue] = []
        if not intent.connection.get("available", True):
            issues.append(ValidationIssue(code="connection_unavailable", field="connection_id", message="Provider connection is unavailable"))
        if not descriptor.get("operations", {}).get("generate", False):
            issues.append(ValidationIssue(code="operation_unsupported", message="Provider does not support generation"))
        models = descriptor.get("models", [])
        model_id = parameters.get("model_id") or intent.connection.get("model_id")
        selected_model = None
        if model_id and models and model_id not in {model.get("model_id") for model in models}:
            issues.append(ValidationIssue(code="model_unsupported", field="model_id", message=f"Provider does not support model: {model_id}"))
        if models:
            selected_model = next((model for model in models if model.get("model_id") == model_id), None)
            if selected_model is None and not model_id:
                selected_model = models[0]
        if selected_model and structure["structure_id"] not in selected_model.get("structures", []):
            issues.append(ValidationIssue(code="structure_unsupported", field="structure_id", message=f"Provider does not support structure: {structure['structure_id']}"))
        if selected_model:
            aspect_ratios = selected_model.get("aspect_ratios", [])
            aspect_ratio = parameters.get("aspect_ratio")
            if aspect_ratio and aspect_ratios and aspect_ratio not in aspect_ratios:
                issues.append(ValidationIssue(code="aspect_ratio_unsupported", field="aspect_ratio", message=f"Provider does not support aspect ratio: {aspect_ratio}"))
            durations = selected_model.get("video", {}).get("duration_seconds", [])
            duration = parameters.get("duration_seconds")
            if duration is not None and durations and duration not in durations:
                issues.append(ValidationIssue(code="duration_unsupported", field="duration_seconds", message=f"Provider does not support duration: {duration}"))
            max_prompt = selected_model.get("input_constraints", {}).get("prompt_max_characters")
            prompt = parameters.get("prompt")
            if max_prompt and prompt and len(str(prompt)) > max_prompt:
                issues.append(ValidationIssue(code="prompt_too_long", field="prompt", message=f"Prompt exceeds provider limit of {max_prompt} characters"))
        return issues

    @staticmethod
    def _snapshot_id(request: GenerationRequest) -> str:
        payload = json.dumps(request.model_dump(), sort_keys=True, default=str).encode()
        return hashlib.sha256(payload).hexdigest()