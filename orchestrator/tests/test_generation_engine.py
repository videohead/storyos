from generation_engine import GenerationIntent, GenerationPreparationService
from providers.loader import load_providers
from templates.registry import TemplateRegistry


def test_prepare_applies_precedence_and_redacts_connection_secrets():
    service = GenerationPreparationService(
        template_registry=TemplateRegistry(),
        provider_registry=load_providers(),
    )
    result = service.prepare(GenerationIntent(
        entity_type="character",
        entity_id=7,
        template_id="character-sheet",
        provider_type="veo",
        connection_id=3,
        scf_values={"positive_prompt": "SCF prompt"},
        connection={"model_id": "veo-3.1-generate-preview", "api_key": "do-not-store"},
        user_overrides={"prompt": "User prompt"},
    ))

    assert result.status == "blocked"
    assert any(issue.code == "structure_unsupported" for issue in result.issues)
    assert "api_key" not in result.provenance


def test_prepare_resolves_required_reference_and_snapshot_isolated():
    service = GenerationPreparationService(provider_registry=load_providers())
    result = service.prepare(GenerationIntent(
        entity_type="shot",
        entity_id=8,
        template_id="storyboard-frame",
        provider_type="comfyui",
        connection_id=4,
        scf_values={"frame_description": "A slow pan", "image_asset": "https://example.test/frame.png"},
        connection={"available": True},
    ))

    assert result.status == "ready"
    assert result.request is not None
    assert result.request.parameters["prompt"] == "A slow pan"
    assert result.request.references[0]["value"].endswith("frame.png")
    snapshot = service.get_snapshot(result.snapshot_id)
    assert snapshot.request is not result.request