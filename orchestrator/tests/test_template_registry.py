import json

from structures.registry import StructureRegistry
from templates.registry import TemplateRegistry, TemplateRegistryError
from templates.validator import (
    TemplateValidationError,
    load_schema,
    validate_catalog,
    validate_template,
)


def test_registry_loads_and_lists_generation_templates():
    registry = TemplateRegistry()

    assert registry.has("character-sheet")
    assert registry.has("storyboard-frame", "1.0")
    assert {item["template_id"] for item in registry.list()} == {
        "character-sheet",
        "scene-still",
        "storyboard-frame",
    }


def test_registry_returns_isolated_template_copies():
    registry = TemplateRegistry()
    template = registry.get("character-sheet")
    template["label"] = "changed locally"

    assert registry.get("character-sheet")["label"] == "Character Sheet"


def test_registry_rejects_duplicate_template_versions(tmp_path):
    catalog = {
        "templates": [
            {
                "template_id": "dup",
                "version": "1.0",
                "label": "First",
                "structure": {"structure_id": "image_to_video"},
                "configuration": {},
            },
            {
                "template_id": "dup",
                "version": "1.0",
                "label": "Second",
                "structure": {"structure_id": "image_to_video"},
                "configuration": {},
            },
        ]
    }
    path = tmp_path / "catalog.json"
    path.write_text(json.dumps(catalog), encoding="utf-8")

    try:
        TemplateRegistry(path)
    except TemplateRegistryError as error:
        assert "dup@1.0" in str(error)
    else:
        raise AssertionError("duplicate template version should fail")


def test_registry_resolves_template_with_structure():
    registry = TemplateRegistry()
    resolved = registry.resolve("character-sheet")

    assert resolved["structure"]["structure_id"] == "text_to_image"
    assert resolved["structure"]["input"]["modalities"] == ["text"]
    assert resolved["configuration"]["parameters"]["prompt"].startswith("Character reference sheet")
    assert resolved["configuration"]["scf_fields"]["prompt"] == "positive_prompt"


def test_resolve_fills_structure_parameter_defaults(tmp_path):
    structure_catalog = {
        "structures": [
            {
                "structure_id": "t2i",
                "version": "1.0",
                "label": "T2I",
                "input": {"modalities": ["text"]},
                "output": {"modalities": ["image"]},
                "parameters": {
                    "prompt": {"type": "string", "required": True},
                    "seed": {"type": "integer", "required": False, "default": 42},
                },
            }
        ]
    }
    structure_path = tmp_path / "structures.json"
    structure_path.write_text(json.dumps(structure_catalog), encoding="utf-8")

    template_catalog = {
        "templates": [
            {
                "template_id": "t",
                "version": "1.0",
                "label": "T",
                "structure": {"structure_id": "t2i"},
                "configuration": {"parameters": {"prompt": "x"}},
            }
        ]
    }
    template_path = tmp_path / "templates.json"
    template_path.write_text(json.dumps(template_catalog), encoding="utf-8")

    registry = TemplateRegistry(template_path, StructureRegistry(structure_path))
    resolved = registry.resolve("t")

    assert resolved["configuration"]["parameters"]["prompt"] == "x"
    assert resolved["configuration"]["parameters"]["seed"] == 42


def test_validate_template_accepts_valid_template():
    template = {
        "template_id": "valid-template",
        "version": "1.0",
        "label": "Valid Template",
        "structure": {"structure_id": "text_to_image"},
        "configuration": {
            "parameters": {"prompt": "A foggy harbor"},
            "scf_fields": {"prompt": "positive_prompt"},
        },
    }

    normalized = validate_template(template)
    assert normalized["template_id"] == "valid-template"
    assert normalized["structure"]["structure_id"] == "text_to_image"


def test_validate_template_rejects_unknown_parameter():
    template = {
        "template_id": "bad-params",
        "version": "1.0",
        "label": "Bad Parameters",
        "structure": {"structure_id": "text_to_image"},
        "configuration": {"parameters": {"not_a_parameter": "x"}},
    }

    try:
        validate_template(template)
    except TemplateValidationError as error:
        assert "not_a_parameter" in str(error)
    else:
        raise AssertionError("unknown parameter should fail validation")


def test_validate_template_rejects_unregistered_structure():
    template = {
        "template_id": "bad-structure",
        "version": "1.0",
        "label": "Bad Structure",
        "structure": {"structure_id": "does_not_exist"},
        "configuration": {},
    }

    try:
        validate_template(template)
    except TemplateValidationError as error:
        assert "does_not_exist" in str(error)
    else:
        raise AssertionError("unregistered structure should fail validation")


def test_validate_template_rejects_bad_version_format():
    template = {
        "template_id": "bad-version",
        "version": "1.0.0",
        "label": "Bad Version",
        "structure": {"structure_id": "text_to_image"},
        "configuration": {},
    }

    try:
        validate_template(template)
    except TemplateValidationError as error:
        assert "version" in str(error)
    else:
        raise AssertionError("invalid version format should fail validation")


def test_validate_template_rejects_reference_role_not_in_structure():
    template = {
        "template_id": "bad-role",
        "version": "1.0",
        "label": "Bad Role",
        "structure": {"structure_id": "text_to_image"},
        "configuration": {
            "references": [
                {"role": "start_frame", "source": "scf_field", "scf_field": "image_asset"}
            ]
        },
    }

    try:
        validate_template(template)
    except TemplateValidationError as error:
        assert "start_frame" in str(error)
    else:
        raise AssertionError("undeclared reference role should fail validation")


def test_validate_template_rejects_reference_binding_missing_source_field():
    template = {
        "template_id": "bad-binding",
        "version": "1.0",
        "label": "Bad Binding",
        "structure": {"structure_id": "image_to_video"},
        "configuration": {
            "references": [
                {"role": "content", "source": "scf_field"}
            ]
        },
    }

    try:
        validate_template(template)
    except TemplateValidationError as error:
        assert "scf_field" in str(error)
    else:
        raise AssertionError("reference binding missing scf_field should fail validation")


def test_validate_template_rejects_bad_scf_mapping():
    template = {
        "template_id": "bad-scf",
        "version": "1.0",
        "label": "Bad SCF",
        "structure": {"structure_id": "text_to_image"},
        "configuration": {"scf_fields": {"prompt": ""}},
    }

    try:
        validate_template(template)
    except TemplateValidationError as error:
        assert "scf_fields" in str(error)
    else:
        raise AssertionError("empty SCF mapping should fail validation")


def test_validate_catalog_covers_example_templates():
    identities = validate_catalog()
    assert {item["template_id"] for item in identities} == {
        "character-sheet",
        "scene-still",
        "storyboard-frame",
    }


def test_schema_document_is_well_formed():
    schema = load_schema()

    assert schema["$id"].endswith("generation-template-1.0.json")
    assert "template_id" in schema["required"]
    assert "structure" in schema["properties"]
    assert "reference_binding" in schema["$defs"]
