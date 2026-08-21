# Generate Preferences and Generation Intents

> **Delivery status:** the provider-neutral representative-media registry,
> Template-resolution preferences, detailed Story Graph prompts, durable item
> and Project batches, and their REST operations are delivered. A dedicated
> graphical site-preferences editor is not required by this contract; sites can
> manage the versioned option through an integration or filter.

## Delivered authoring workflow

The **World Graph Studio Assets** Generate surface supports two related paths:

- direct image generation preserves the existing editable prompt, image
  Template, featured-image, and linked-Asset controls; and
- representative generation previews a complete item or Project plan before
  it starts a durable, potentially long-running batch.

Planning reports the number of image and video jobs, every source and creative
intent, prompt fingerprints, Templates runnable across the plan, resolved
defaults, and the latest batch. It performs no writes and spends no provider
budget.
Starting a batch revalidates permissions, Templates, Connections, and bindings
before any child job is queued.

The original endpoints remain compatible:

```http
GET  /wp-json/worldgraph/v1/assets/generate/prompt?post_id={id}
POST /wp-json/worldgraph/v1/assets/generate
```

## Detailed prompt contract

Project, Story World, Character, Prop, Location, Shot, Scene, and Episode expose
an optional SCF textarea named `generation_prompt`. It contains additional
media-generation instructions such as a house style, wardrobe or material
constraint, camera movement, or "no watermark." It is not a negative-prompt
transport field and does not replace the entity's authorial description.

The default composer reads this type-specific Story Graph context:

| Content type | Detailed fields included when populated |
| --- | --- |
| Project | description, genre, target medium, production stage, frame dimensions, aspect ratio, frame rate |
| Story World | synopsis, timeline, rules, themes, geography, references |
| Character | biography, age, appearance, personality, motivation, backstory, voice profile |
| Prop | description, purpose, notes |
| Location | description, environment type, geography, mood |
| Shot | number, type, camera angle, lens, duration, description, editorial notes |
| Scene | number, summary, script content, dialogue, location, time of day, emotional tone, production notes |
| Episode | number, synopsis |

When no author-edited base prompt is supplied, composition order is:

1. content type and title;
2. non-duplicate excerpt and post content;
3. labeled detailed fields;
4. dependent Scene-shot or Episode-bookend context where applicable;
5. optional item-scoped `base_prompt` author direction;
6. the representative intent's creative objective;
7. `generation_prompt`; and
8. common continuity, detail, and no-watermark constraints.

The composer removes markup, renders select values and relationships readably,
deduplicates repeated core/SCF text, and applies one global 2,400-word bound.
An item-scoped `base_prompt` adds instructions without removing the assembled
Story Graph context or saved `generation_prompt`. The
`worldgraph_generate_asset_prompt` filter runs last with the prompt, source
post, and intent.

## Delivered intent vocabulary

`WorldGraph\Utils\Generation_Workflows` owns the stable creative-intent slugs.
They describe what to make; the resolved Template and Connection decide how it
is executed.

| Content type | Workflow | Intent slugs and output types |
| --- | --- | --- |
| Project | `project-key-art` | `project-key-art` (image) |
| Story World | `world-key-art` | `world-key-art` (image) |
| Character | `character-look-set` | `character-full-view`, `character-front-view`, `character-three-quarter-view`, `character-profile-view`, `character-back-view`, `character-close-up` (images) |
| Prop | `prop-look-set` | `prop-full-view`, `prop-front-view`, `prop-three-quarter-view`, `prop-profile-view`, `prop-back-view`, `prop-close-up` (images) |
| Location | `location-look-set` | `location-full-view`, `location-front-view`, `location-three-quarter-view`, `location-profile-view`, `location-reverse-view`, `location-close-up` (images) |
| Shot | `shot-still-and-video` | `shot-representative-still` (image), `shot-video` (video) |
| Scene | `scene-filmstrip` | `scene-filmstrip` (image) |
| Episode | `episode-bookend-filmstrip` | `episode-bookend-filmstrip` (image) |

The first image in a recipe is eligible to become the source post's featured
image. Each view and each Shot output is an independent child job, so failures
and retries remain attributable. Scene filmstrips receive textual context from
ordered child Shots; Episode filmstrips receive context from the opening and
final Scenes. These composite prompts do not imply that the engine waits for or
automatically binds newly generated child images. Other generator-supported
post types retain the generic representative-image fallback.

## Template resolution and preferences

A Template must be published, have `status = active`, produce the required
output type, belong to an available Connection, and resolve all required media
bindings for the task. Representative generation resolves each task through:

1. an explicit `image_template_id` or `video_template_id` in the request;
2. per-post `_worldgraph_generation_template_{intent}` metadata;
3. a site preference for the source CPT and intent;
4. a site preference for the `image` or `video` output type;
5. the managed local ComfyUI text-to-image Template for image output; and
6. the first runnable compatible Template.

Site preferences use the versioned option
`worldgraph_generation_preferences_v1`. Its supported shape is:

```json
{
  "intents": {
    "worldgraph_shot": {
      "shot-representative-still": 101,
      "shot-video": 202
    }
  },
  "outputs": {
    "image": 101,
    "video": 202
  }
}
```

Values are `worldgraph_template` post IDs. Missing, partial, stale, or
incompatible mappings fall through to the next candidate. The
`worldgraph_generation_default_template_id` filter can alter a resolved
candidate, but the returned Template must still be suitable for the task.

## Plans and durable batches

The representative REST contract is:

```http
GET  /wp-json/worldgraph/v1/assets/generate/plan?post_id={id}&scope={item|project}
POST /wp-json/worldgraph/v1/assets/generate/batches
GET  /wp-json/worldgraph/v1/assets/generate/batches/{id}
POST /wp-json/worldgraph/v1/assets/generate/batches/{id}/cancel
```

`scope=item` expands the selected post's recipe. `scope=project` requires a
Project and walks canonical `contains` and `belongs_to` ownership edges to
include the Project and each supported descendant once. A plan returns:

- `workflow`, `sources`, `total_jobs`, and image/video `counts`;
- `tasks` with source identity, workflow, intent, label, type, featured flag,
  and `prompt_hash`, while omitting long provider prompts;
- `ready` and any Template `blockers`;
- `image_templates` and `video_templates` runnable across that plan;
- resolved `default_template_ids`; and
- `latest_batch`, when one exists for the same root and scope.

The start payload accepts `post_id`, `scope`, optional item `base_prompt`,
optional `image_template_id` and `video_template_id`, and the required
`idempotency_key` member. The server refuses to start unless the requester can
edit every source and every image/video task resolves a runnable Template.
Plans are limited to 5,000 jobs by default;
`worldgraph_generation_batch_max_tasks` may change that bound.

A non-empty idempotency key is scoped to the requester and root batch request.
Repeating it returns the existing batch. This protects clients from duplicate
provider spending after a timeout or lost response.

## Batch storage, status, and cancellation

A representative batch is a parent `worldgraph_gen` record with:

- `_worldgraph_gen_batch_kind = representative_media`;
- `_worldgraph_gen_batch_scope`;
- `_worldgraph_gen_batch_plan`, containing source, intent, output type,
  Template, and prompt hash for each child;
- `_worldgraph_gen_idempotency_key`;
- requester, creation time, total, child IDs, and aggregate status.

Each child remains an ordinary generation job and adds
`_worldgraph_gen_batch_id` and `_worldgraph_gen_intent`. Status responses report
the root and scope, aggregate status, total/active/completed/failed/cancelled
counts, per-state counts, creation time, and child source, intent, type, status,
attachment, and error details.

WP-Cron continues to submit and poll bounded numbers of child jobs, so a large
Project batch may run for hours or days without one HTTP request remaining
open. Cancellation changes only children that are still `queued` and reports
`stopped_queued` plus a human-readable `cancel_note`. Submitted or terminal
provider work retains its actual lifecycle state and remains in the aggregate
report.

## Security and operating boundaries

- Planning and batch operations use WordPress authentication, `upload_files`,
  and source-post edit capabilities.
- Starting a batch is the explicit budget-spending action; preview is read-only.
- Provider credentials remain on Connections or in environment references.
- Disabled Connections, output mismatches, and unresolved Template bindings
  remain hard blockers.
- An image-only installation can use Project, World, look-set, Scene, and
  Episode recipes, but a Shot batch cannot start until its required video
  output also has a runnable Template.
- World Graph Studio remains usable with no generation provider.

## Implementation references

- [Representative workflow registry](../../wordpress/wp-content/plugins/worldgraph/includes/utils/class-generation-workflows.php)
- [Assets metabox](../../wordpress/wp-content/plugins/worldgraph/includes/admin/asset-generator-metabox.php)
- [Assets REST controller](../../wordpress/wp-content/plugins/worldgraph/includes/rest-api/asset-generation-controller.php)
- [Asset generation service](../../wordpress/wp-content/plugins/worldgraph/includes/utils/class-asset-generator.php)
- [Template bindings](../../wordpress/wp-content/plugins/worldgraph/includes/utils/template_bindings.php)
- [Modality registry](../../wordpress/wp-content/plugins/worldgraph/includes/utils/generation-modality.php)
- [Generation Engine](GENERATION_ENGINE.md)
