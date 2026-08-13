import json

import pytest

from providers.capability_loader import load_capability_descriptors
from providers.discovery import (
    discover_providers,
    scaffold_capability_descriptor,
    update_capability_descriptor,
)


def test_discovery_reports_bundled_provider_descriptors():
    providers = discover_providers()

    assert {provider["provider_type"] for provider in providers} == {
        "comfyui",
        "nova_reel",
        "veo",
    }
    assert all(provider["descriptor_status"] == "ready" for provider in providers)


def test_scaffold_and_update_descriptor(tmp_path, monkeypatch):
    package_dir = tmp_path / "testproviders"
    package_dir.mkdir()
    (package_dir / "__init__.py").write_text("", encoding="utf-8")
    capabilities_dir = package_dir / "capabilities"
    capabilities_dir.mkdir()
    schema_path = capabilities_dir / "schema.json"
    schema_path.write_text("{}", encoding="utf-8")
    monkeypatch.syspath_prepend(str(tmp_path))

    destination = scaffold_capability_descriptor("new_provider", package_name="testproviders")
    assert destination.exists()

    update_capability_descriptor(
        "new_provider",
        {
            "models": [
                {
                    "model_id": "model-1",
                    "execution_topology": {
                        "mode": "external_adapter",
                        "comfyui_required": False,
                    },
                    "structures": ["text_to_image"],
                    "input_constraints": {"mime_types": ["image/png"]},
                    "output_constraints": {"mime_types": ["image/png"]},
                }
            ]
        },
        package_name="testproviders",
    )

    descriptor = load_capability_descriptors("testproviders")["new_provider"]
    assert descriptor["models"][0]["model_id"] == "model-1"


def test_update_requires_matching_provider_version():
    with pytest.raises(ValueError, match="provider version mismatch"):
        update_capability_descriptor(
            "veo",
            {"models": []},
            expected_provider_version="not-the-current-version",
        )
