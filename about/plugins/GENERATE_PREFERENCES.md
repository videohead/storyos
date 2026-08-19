# Generate Preferences and Generation Intents

> Status: specification. Companion to `about/plugins/COMFY_TEMPLATE_CATALOG.md`.
> That document covers how ComfyUI templates are discovered and provisioned.
> This one covers how a story CPT actually generates an asset without the author
> ever seeing a workflow graph.
>
> Companion: `about/plugins/COMFY_AND_PROMPT_AGENTS.md` covers the Prompt
> Designer agent, which explains unavailable intents and their input
> requirements conversationally.

## 1. The Problem

Today, `Asset_Generator_MetaBox` renders the same panel on all twelve story
CPTs: a prompt textarea, a flat **Template** dropdown listing every active
Template in the site, and a hardcoded **Generate image** button.

That surface has four defects:

1. **The Template dropdown is a workflow chooser wearing a content-authoring
   hat.** It lists Templates by name across every modality. An author editing a
   Character sees video templates. Nothing about a Character says "portrait".
2. **The CPT contributes nothing.** `runnable_templates()` filters only by
   whether required media slots resolve and whether the Connection is available.
   A Shot and a Story World get identical options.
3. **The button lies.** It says "Generate image" even when the selected Template
   has a video modality.
4. **There is no default.** Every generation is a manual template pick. There is
   no "just generate the obvious thing for this Character" path.

This is exactly the complexity spiral you describe: ComfyUI's power leaks
upward into the authoring surface, and authors go looking for a simple
text-to-image tool instead.

## 2. The Missing Concept, Already Named

The codebase and docs already carry a vocabulary for the missing layer — it was
specified and never implemented:

- `storyos_connection` meta `enabled_structures` is documented in
  `includes/cpts/connection.php` as *"JSON array of generation structures
  enabled for this connection, e.g. `["character-sheet","scene-image"]`"*.
- `about/REST_API_Specification.md` and the plugin's own `ARCHITECTURE.md` and
  `SETUP_GUIDE.md` all show `"workflow": "character-sheet"`.
- `README.md` lists *"Templates: base, character-sheet, environment,
  storyboard"*.
- `storyos_template` meta `generation_structure` exists on the CPT and in the
  Abilities API.

But nothing defines what a "structure" is. `Comfy_Bootstrap` writes
`generation_structure = Generation_Modality::output_type(...)`, degrading the
field to `image` | `video` — a duplicate of the modality's output type,
carrying no authorial meaning.

**A "generation structure" is the intent layer.** This spec names it
**Generation Intent**, defines the registry, and makes `generation_structure`
and `enabled_structures` mean what the docs always said they meant.

## 3. The Three Layers

| Layer | Answers | Owned by | Example |
| --- | --- | --- | --- |
| **Intent** | *What am I making?* | StoryOS, per CPT | "Character portrait" |
| **Modality** | *What shape is the job?* | `Generation_Modality` | `text_to_image` |
| **Template** | *Which graph runs it?* | ComfyUI / Connection | `flux_txt2img_basic` |

Authors work at the Intent layer and never leave it. Modality is inferred.
Template is resolved by preference and only surfaced under "Advanced".

## 4. Generation Intent Registry

New class `StoryOS\Utils\Generation_Intent` in
`includes/utils/generation-intent.php`, mirroring the established
`Generation_Modality` / `Model_Family` static-registry pattern.

Each intent:

```php
'character-portrait' => [
    'label'       => 'Portrait',
    'description' => 'Head-and-shoulders reference image.',
    'post_types'  => [ 'storyos_character' ],
    'modality'    => Generation_Modality::TEXT_TO_IMAGE,
    'prompt_recipe' => [
        'fields' => [ 'appearance', 'physical_description', 'age', 'wardrobe' ],
        'prefix' => 'Character portrait, head and shoulders,',
        'suffix' => 'neutral background, soft key light, cinematic.',
    ],
    'defaults'    => [ 'width' => 896, 'height' => 1152 ],
    'aspect'      => 'portrait',
],
```

### 4.1 Starting set

Deliberately small. One obvious intent per CPT, plus a few that earn their keep.

| Intent slug | Post types | Modality | Output |
| --- | --- | --- | --- |
| `character-portrait` | character | text_to_image | image |
| `character-full-body` | character | text_to_image | image |
| `character-turnaround` | character | text_to_image | image |
| `location-establishing` | location, story_world | text_to_image | image |
| `prop-reference` | prop | text_to_image | image |
| `organization-emblem` | organization | text_to_image | image |
| `scene-key-frame` | scene | text_to_image | image |
| `shot-storyboard-frame` | shot, storyboard_frame | text_to_image | image |
| `shot-animatic` | shot | text_image_to_video | video |
| `episode-poster` | episode, project | text_to_image | image |
| `generic-image` | *all* | text_to_image | image |
| `generic-video` | *all* | text_to_video | video |

`generic-image` / `generic-video` are the escape hatch and the answer to "users
just want a simple text-to-image tool" — they are always available, take the
prompt as-is, and require no configuration.

### 4.2 API

```php
Generation_Intent::all(): array
Generation_Intent::get( string $slug ): ?array
Generation_Intent::for_post_type( string $post_type ): array   // ordered
Generation_Intent::for_post( int $post_id ): array             // + availability
Generation_Intent::modality( string $slug ): string
Generation_Intent::sanitize( string $slug ): string
Generation_Intent::build_prompt( string $slug, int $post_id ): string
```

`for_post_type()` returns intents whose `post_types` contains the type or is
`*`. Extensible via a `storyos_generation_intents` filter so sub-plugins can
register their own without patching the registry.

### 4.3 Prompt recipes replace the one-size prompt builder

`Asset_Generator::build_prompt()` currently mines a fixed list of meta keys
(`description`, `appearance`, `visual_style`, `shot_type`, …) and appends one
cinematic suffix for every CPT and every purpose. A turnaround and an
establishing shot get the same prompt shape.

`Generation_Intent::build_prompt()` supersedes it: intent-specific field list,
prefix, and suffix, composed over the existing title/excerpt base and the
existing `Asset_Generator::project_media_profile()` sizing. Keep
`build_prompt()` as a thin delegate to `generic-image` so nothing breaks, and
keep the `storyos_generate_asset_prompt` filter firing last.

## 5. Generate Preferences

Preferences bind an intent to a Template. Resolution is a cascade, most
specific first:

1. **Per-post override** — post meta `_storyos_intent_template_{intent}`. Set
   only when an author uses Advanced. Rare.
2. **Per-post-type preference** — the main configuration surface.
3. **Global default** — one Template per modality.
4. **Automatic** — first `active` Template whose modality matches the intent and
   whose Connection is available. Ensures generation works with zero config.

Layer 4 is what makes the feature usable immediately after
`Comfy_Bootstrap::ensure_template()` provisions the default text-to-image
Template. **No configuration is ever required to generate.** Preferences only
override.

### 5.1 Storage

Option `storyos_generate_preferences`:

```json
{
  "version": 1,
  "post_types": {
    "storyos_character": {
      "enabled_intents": ["character-portrait", "character-full-body"],
      "default_intent": "character-portrait",
      "intent_templates": { "character-portrait": 412 },
      "auto_set_featured": true,
      "auto_create_asset": true
    }
  },
  "global": {
    "default_templates": { "text_to_image": 412, "text_to_video": 418 },
    "disclosure_level": "simple"
  }
}
```

An option, not post meta: this is site configuration, not content. Read through
a `Generate_Preferences` accessor class with sane defaults so a missing or
partial option never fatals.

### 5.2 Admin screen

**StoryOS → Generate Preferences** (`includes/admin/generate-preferences.php`).

One collapsible section per story CPT:

- Checkbox list of intents available to that CPT, with the enabled subset
  checked and one marked as default.
- Per enabled intent, a Template select showing only Templates whose modality
  matches that intent, plus "Automatic". Each option shows its Connection and a
  readiness chip drawn from the catalog spec's validation state.
- Per-CPT toggles for "Set as featured" and "Create linked Asset record",
  currently hardcoded defaults in the metabox.

Global section:

- Default Template per modality.
- **Disclosure level**: `simple` | `standard` | `advanced` (§6).

Nothing here is mandatory. The screen opens fully populated with automatic
resolution already working.

## 6. The Metabox, Rebuilt

Progressive disclosure, controlled by the global disclosure level. This is the
direct answer to workflow complexity leaking into authoring.

**Simple** — the default:

> **Generate**
> [ Portrait ] [ Full body ] [ Turnaround ]
> *prompt textarea, pre-filled from the intent recipe*
> [ Generate ] [ Suggest prompt ]

Intents render as a chip/segmented control, not a dropdown. The default intent
is preselected. No Template control at all. The action button label comes from
the intent's output type — **Generate image** or **Generate video** — fixing the
current mislabel.

**Standard** — adds a collapsed **Advanced** disclosure containing the Template
select (filtered to the chosen intent's modality), the featured/asset
checkboxes, and a size override.

**Advanced** — Advanced is expanded by default and additionally exposes seed,
steps, and negative prompt.

Unavailable intents (no matching Template, or required media slots unresolved
per `Template_Bindings::missing_required()`) render disabled with the reason
inline — "Needs a featured image" — rather than vanishing from the list. Silent
disappearance is the current behaviour and it is unexplainable to an author.

## 7. Template ↔ Intent Binding

Give `generation_structure` its real meaning: a Template declares which intents
it can serve.

- Rename the field's *semantics*, not the key: `generation_structure` becomes a
  JSON array of intent slugs. Migrate existing `image` / `video` scalar values
  to the matching generic intent on upgrade.
- Add an **Intents** multi-select to the Template Details metabox, listing
  intents whose modality matches the Template's modality.
- Default when empty: the Template serves every intent matching its modality.
  Fail-open here, because a Template with no declared intents is more useful as
  a generic fallback than as a dead record.

Connection `enabled_structures` then works as documented — an allow-list of
intent slugs a Connection may serve — and finally means something. Enforce it in
resolution: an intent whose only Templates sit on a Connection that excludes it
is unavailable.

## 8. REST and Data Flow

| Route | Method | Purpose |
| --- | --- | --- |
| `assets/generate/intents` | `GET` | Intents for `post_id`, each with availability, reason, resolved template, prefilled prompt. Replaces the template list in the prompt endpoint. |
| `assets/generate` | `POST` | Accepts `intent` alongside the existing `template_id`. |
| `generate-preferences` | `GET`/`POST` | Read/write the preferences option. `manage_options`. |

`assets/generate/prompt` keeps returning `templates` for one release so the
existing JS keeps working, then drops it.

`Asset_Generator::queue_for_post()` gains an `intent` arg. When `template_id` is
absent it resolves via the §5 cascade; when present the explicit pick wins. The
job record stores `_storyos_generation_intent` next to the existing workflow and
connection meta, so provenance answers *why* this asset exists, not just which
graph made it. Carry it onto the created `storyos_asset` record too.

## 9. Why This Shape

- *Expose modality directly in the metabox* — rejected. "Image to image" is a
  pipeline term. "Turnaround" is what an author wants.
- *One Template per CPT, hardcoded* — rejected. Too rigid; a Character needs
  several distinct images.
- *Free-form per-post template picking (today's behaviour)* — kept, but demoted
  behind Advanced. It is a power-user affordance, not a default workflow.
- **Chosen: intent-first with a resolution cascade and fail-open automatic
  resolution.** The simple path requires zero configuration, the CPT
  meaningfully shapes what is offered, and ComfyUI complexity stays in the
  Template and Catalog surfaces where operators — not authors — work.

## 10. Implementation Order

1. `Generation_Intent` registry with the §4.1 starting set and `build_prompt()`.
2. `Generate_Preferences` accessor + option schema + resolution cascade, with
   layer 4 automatic resolution working before any UI exists.
3. `assets/generate/intents` endpoint; `intent` arg through
   `Asset_Generator::queue_for_post()` and onto the job/asset records.
4. Metabox rebuild: intent chips, correct button label, disabled-with-reason
   states, Advanced disclosure.
5. Generate Preferences admin screen.
6. Template Intents multi-select + `generation_structure` migration.
7. Enforce Connection `enabled_structures` in resolution.

Steps 1–3 are shippable on their own: with automatic resolution and correct
labels, the existing metabox improves without any new configuration surface.
Step 4 is where authors feel the change.
