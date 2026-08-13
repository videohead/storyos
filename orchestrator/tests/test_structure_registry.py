from structures.registry import StructureRegistry
from structures.validator import StructureValidationError, validate_intent


def test_registry_loads_and_lists_generation_structures():
    registry = StructureRegistry()

    assert registry.has("text_to_image")
    assert registry.has("image_to_video", "1.0")
    assert {item["structure_id"] for item in registry.list()} == {
        "image_to_video",
        "text_to_image",
    }


def test_registry_returns_isolated_structure_copies():
    registry = StructureRegistry()
    structure = registry.get("text_to_image")
    structure["label"] = "changed locally"

    assert registry.get("text_to_image")["label"] == "Text to Image"


def test_validator_checks_story_intent_without_provider_limits():
    intent = validate_intent(
        {
            "structure_id": "text_to_image",
            "input": {"modalities": ["text"]},
            "output": {"modalities": ["image"]},
            "parameters": {"prompt": "A foggy harbor", "width": 9999},
        }
    )

    assert intent["structure_id"] == "text_to_image"
    assert intent["parameters"]["width"] == 9999


def test_validator_rejects_missing_required_story_input():
    try:
        validate_intent(
            {
                "structure_id": "text_to_image",
                "input": {"modalities": ["text"]},
                "output": {"modalities": ["image"]},
                "parameters": {},
            }
        )
    except StructureValidationError as error:
        assert "prompt" in str(error)
    else:
        raise AssertionError("missing prompt should fail validation")
