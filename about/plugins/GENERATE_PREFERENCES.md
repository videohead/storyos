# Generate Preferences and Generation Intents

> **Status: optional extension design note.** A Generation Intent registry and
> site-wide Generate Preferences screen are not part of the current release and
> are not active delivery commitments. The current release is complete; its
> delivered Template-first workflow is documented below and in
> [Delivery Status](../Delivery_Status.md).

## Current delivered workflow

World Graph Studio currently exposes a **World Graph Studio Assets** metabox on
the supported story and asset-editing screens. It provides:

- a prompt textarea prefilled from the current post;
- an active Template dropdown;
- **Set as featured asset** and **Create linked Asset record** controls;
- **Generate image** and **Suggest prompt** actions; and
- queued-job feedback.

`GET /wp-json/worldgraph/v1/assets/generate/prompt?post_id={id}` returns the
suggested prompt, runnable image Templates, and default Template ID.

`POST /wp-json/worldgraph/v1/assets/generate` requires the source `post_id`
and an explicit active `template_id`. It can also receive `prompt`,
`set_featured`, and `create_asset`.

The Template list is filtered to records that:

- are published and have `status = active`;
- produce an image according to the registered modality;
- use an available Connection; and
- can resolve every required media binding from the current post.

The managed local ComfyUI text-to-image Template is preferred when available;
otherwise the first runnable image Template is selected.

This workflow is complete as delivered. It does not depend on the extension
concepts below.

## Current prompt behavior

`Asset_Generator::build_prompt()` composes one text-to-image prompt from:

1. the Story Graph type and post title;
2. excerpt or post content;
3. descriptive metadata available on that content type; and
4. a cinematic concept-art suffix.

The `worldgraph_generate_asset_prompt` filter runs last. Sites that need
different recipes can customize this output today without adding a preferences
system.

## Current Template semantics

The runtime uses these Template concerns:

| Field | Current meaning |
| --- | --- |
| `modality` | Registered input/output shape, currently text-to-image or an ElevenLabs audio modality |
| `generation_structure` | Human/configuration label; managed Templates currently write the modality output type |
| `connection_id` | Owning provider Connection |
| `provider_template_id` | Provider workflow, endpoint, or voice identifier |
| `input_bindings` | JSON sources for required media slots |
| `configuration_json` / `default_values` | Provider and workflow defaults |
| `status` | Whether the Template is selectable for generation |

There is no `worldgraph_generate_preferences` option, no
`Generation_Intent` class, no intent REST route, and no intent value stored on
generation or Asset provenance in the current release.

## Why this extension idea exists

A Template is an operator-facing execution choice. An author may instead think
in creative outcomes such as “portrait,” “establishing image,” or “storyboard
frame.” A Generation Intent layer could translate those author-facing outcomes
into a compatible modality and Template while keeping workflow details behind
an advanced disclosure.

That can be valuable for a site with many similar Templates. It is not required
for the current Template-first experience and should not be presented as work
the core project has promised to deliver.

## Possible extension vocabulary

An extension may define these three layers:

| Layer | Question answered | Example |
| --- | --- | --- |
| Intent | What creative result does the author want? | Character portrait |
| Modality | What registered input/output shape is required? | `text_to_image` |
| Template | Which provider configuration runs it? | A selected ComfyUI or fal Template |

Example intent slugs could include:

- `character-portrait`;
- `location-establishing`;
- `prop-reference`;
- `scene-key-frame`;
- `shot-storyboard-frame`; and
- `generic-image`.

These names are illustrative. They are not reserved core identifiers.

## Compatible extension contract

An optional preferences implementation should build on the delivered runtime
rather than replace it.

### Resolution

A reasonable Template-resolution cascade is:

1. explicit per-request or per-post override;
2. site preference for the content type and intent;
3. site default for the registered modality; and
4. first active, compatible Template on an available Connection.

Every resolved Template must still pass the same core checks for status,
provider/Connection agreement, output type, and required input bindings.

### Storage

Site preferences belong in a versioned WordPress option owned by the extension.
Per-post overrides belong in namespaced post meta. The extension must tolerate
a missing or partial option and fall back to the current explicit Template
path.

It must not reinterpret existing `generation_structure` values or migrate them
without an explicit versioned migration. Current sites may use that field as a
free-form label or output-type marker.

### UI

An intent-oriented UI may replace the Template dropdown with creative labels at
the simple disclosure level, while retaining an advanced Template choice.
Unavailable choices should explain whether the blocker is:

- no active Template for the modality;
- a disabled or missing Connection; or
- an unresolved required binding.

The current image-only metabox should remain the no-extension fallback.

### REST and provenance

An extension may add an `intent` parameter or an intent-discovery route under
`/wp-json/worldgraph/v1/`, but it must preserve the existing
`/assets/generate` and `/assets/generate/prompt` contracts.

If an intent influences execution, record its slug on the generation record and
the resulting Asset so provenance explains both the creative purpose and the
executed Template.

### Prompt recipes

Intent-specific prompt recipes may choose fields, prefixes, suffixes, and
framing defaults. They should compose over the existing post title/content and
must leave `worldgraph_generate_asset_prompt` available as the final site
override.

## Boundaries

This design note does not expand the current modality registry. In particular,
video intent examples must not appear in a core UI unless a real registered
video modality and runnable adapter are installed.

An extension must also preserve:

- WordPress capability and nonce checks;
- explicit confirmation before spending provider budget;
- Connection allowlists and availability;
- Template input validation;
- generation-job and media provenance; and
- the ability to work with no generation provider.

## Implementation references

- [Assets metabox](../../wordpress/wp-content/plugins/worldgraph/includes/admin/asset-generator-metabox.php)
- [Assets REST controller](../../wordpress/wp-content/plugins/worldgraph/includes/rest-api/asset-generation-controller.php)
- [Asset generation service](../../wordpress/wp-content/plugins/worldgraph/includes/utils/class-asset-generator.php)
- [Template bindings](../../wordpress/wp-content/plugins/worldgraph/includes/utils/template_bindings.php)
- [Modality registry](../../wordpress/wp-content/plugins/worldgraph/includes/utils/generation-modality.php)
- [Generation Engine](GENERATION_ENGINE.md)
