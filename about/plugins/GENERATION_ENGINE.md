# World Graph Studio Generation Engine

> **Delivery status:** complete for the current release. See
> [Delivery Status](../Delivery_Status.md) for the repository-wide status
> contract. Provider availability, credentials, installed models, and network
> access are deployment conditions rather than unfinished application work.

## Purpose

The Generation Engine connects Story Graph content to configured media
providers. WordPress remains the control plane and source of truth for:

- provider Connections;
- reusable generation Templates;
- provider-neutral representative-media recipes and intents;
- read-only plans, durable batches, and queued generation records;
- prompts, parameters, source-post links, and provider provenance;
- imported WordPress media, retained text results, and linked Asset records; and
- permissions, nonces, cancellation, status, and operator logs.

No separate Python orchestrator or application queue is required. Long-running
work is submitted and polled by WP-Cron.

```text
Story Graph item or Project
              |
              v
 representative workflow plan
              |
              v
 active Template + Connection per output
              |
              v
 durable batch + child worldgraph_gen records
              |
              v
         WP-Cron batch worker
              |
              v
 ComfyUI / fal / ElevenLabs / Suno / VideoDraft / OpenRouter adapter
              |
              v
 WordPress attachments / text results + provenance
```

## Current generation shapes

`WorldGraph\Utils\Generation_Modality` is the canonical registry. The current
release registers these modalities:

| Modality | Output | Required input | Optional input | Primary adapter |
| --- | --- | --- | --- | --- |
| `text_to_image` | image | `prompt` | `negative_prompt` | ComfyUI or a compatible fal Template |
| `image_to_image` | image | `image` | `prompt`, `negative_prompt` | Compatible ComfyUI or VideoDraft Template |
| `image_text_to_image` | image | `image`, `prompt` | `negative_prompt` | Compatible ComfyUI or VideoDraft Template |
| `text_to_video` | video | `prompt` | `negative_prompt` | VideoDraft or OpenRouter |
| `text_image_to_video` | video | `prompt`, `image` | `negative_prompt` | VideoDraft or OpenRouter |
| `video_to_video` | video | `start_frame` | `prompt`, `negative_prompt`, `end_frame` | Compatible ComfyUI Template |
| `video_with_audio` | video | `audio` | `prompt`, `negative_prompt`, `video` | VideoDraft |
| `text_to_speech` | audio | `prompt` | none | ElevenLabs |
| `text_to_dialogue` | audio | `prompt` | none | ElevenLabs |
| `text_to_sound_effect` | audio | `prompt` | none | ElevenLabs |
| `text_to_music` | audio | `prompt` | none | ElevenLabs or Suno |
| `text_to_voice` | audio | `prompt` | none | ElevenLabs voice design |
| `text_to_lyrics` | text | `prompt` | none | Suno |

The media-import boundary can accept image, video, and audio provider results,
and Assets can describe broader media. That storage capability does not make
every media shape a registered generation modality. Custom or future adapters
must register and validate their own executable contract rather than relying on
unused modality names.

The story-post **World Graph Studio Assets** metabox retains its direct image
generation path and also exposes the Story Graph item's representative-media
plan. A Shot plan can therefore require both an image-output Template and a
video-output Template. The generic generation REST endpoint accepts `image`,
`video`, `audio`, or `text` as a requested result type, but the selected active
Template and provider adapter still determine whether a request can run.

## Story-aware prompts and representative workflows

Project, Story World, Character, Prop, Location, Shot, Scene, and Episode have
an optional `generation_prompt` SCF textarea for media-specific instructions.
When no author-edited base prompt is supplied, the prompt composer combines:

1. the content type and title;
2. non-duplicate excerpt and post content;
3. labeled, type-appropriate SCF details, including long-form descriptions,
   appearance, world rules, scripts, dialogue, production notes, and camera
   details where applicable;
4. dependent Story Graph context for Scene and Episode filmstrips;
5. the selected output intent's creative objective;
6. `generation_prompt`; and
7. common continuity, detail, and no-watermark constraints.

Markup is removed, select values and relationship titles are made readable,
and the final provider prompt has a global 2,400-word bound. An item-scoped
`base_prompt` adds author direction without removing saved Story Graph context
or `generation_prompt`. The `worldgraph_generate_asset_prompt` filter runs last
and receives the prompt, source post, and intent.

`WorldGraph\Utils\Generation_Workflows` defines the delivered representative
recipes:

| Content type | Workflow | Outputs |
| --- | --- | --- |
| Project | `project-key-art` | `project-key-art` image |
| Story World | `world-key-art` | `world-key-art` image |
| Character | `character-look-set` | full, front, three-quarter, profile, back, and close-up images |
| Prop | `prop-look-set` | full, front, three-quarter, profile, back, and close-up images |
| Location | `location-look-set` | full establishing, front, three-quarter, profile, reverse, and detail close-up images |
| Shot | `shot-still-and-video` | `shot-representative-still` image and `shot-video` video |
| Scene | `scene-filmstrip` | one filmstrip image informed by ordered Shot descriptions |
| Episode | `episode-bookend-filmstrip` | one filmstrip image informed by the opening and final Scenes |

Each output is independently queued and records its stable intent slug. An item
plan contains one content item's recipe. Project scope walks canonical ownership
edges from the Project and plans every supported descendant once. The default
maximum is 5,000 child tasks, filterable with
`worldgraph_generation_batch_max_tasks`.

## Connections and provider adapters

A `worldgraph_conn` post binds a provider type to an environment, endpoints,
credential value or reference, model restrictions, status, and optional limits.
A `worldgraph_template` post binds an executable provider template or local
workflow to that Connection.

The shipped generation adapters are:

### Local ComfyUI

Local ComfyUI is reached through its HTTP API. The Connection's `endpoint_url`
must be reachable from the WordPress runtime. The adapter uses:

- `POST /prompt` to submit a workflow;
- `GET /history/{prompt_id}` to poll it;
- `POST /upload/image` for bound media inputs;
- `GET /object_info` for nodes and installed model choices; and
- `GET /view` for generated outputs.

World Graph Studio creates a managed text-to-image Template during local setup.
That Template can use the built-in text-to-image graph or operator-supplied
ComfyUI API-format workflow JSON. The readiness panel checks for the graph's
nodes and checkpoint before a job is queued.

A separate Comfy MCP endpoint is optional. A bare ComfyUI HTTP endpoint does
not speak MCP, so do not append `/mcp` to port `8188`. When an actual MCP
server is configured, it can advertise templates and handle model-download
requests.

### Comfy Cloud MCP

Comfy Cloud uses Streamable HTTP JSON-RPC at
`https://cloud.comfy.org/mcp`. `Comfy_Cloud_MCP` establishes a session, calls
advertised tools, normalizes JSON or event-stream responses, and correlates
logs with the owning Connection.

Execution uses `run_template` and `get_job_status`. Catalog support is probed
from `tools/list`; discovery and provisioning are offered only when the server
advertises `list_templates`, `get_template`, and `download_models` as needed.

### fal MCP

The fal adapter connects to `https://mcp.fal.ai/mcp`. On save or connection
test, catalog provisioning inspects allowed endpoint schemas and maintains
paired active Templates. `Model Access` is the endpoint allowlist when set;
otherwise the preferred `Model` or a provider-selected current text-to-image
endpoint is used.

### ElevenLabs

The ElevenLabs adapter uses `https://api.elevenlabs.io/v1`. Catalog sync reads
available speech models and voices and provisions endpoint-specific Templates
for the five registered audio modalities. Completed audio, including voice
design previews, is imported into the WordPress media library before the job is
marked complete.

### Suno REST and MCP

One `suno` Connection holds the SunoAPI.org REST endpoint and the AceData Cloud
Suno MCP endpoint, but their bearer tokens remain in separate
`credential_reference` and `mcp_credential_reference` fields. These are
independent third-party providers; credentials and model names are not
interchangeable.

Catalog sync provisions six transport-specific Templates: prompt music,
custom music, and lyrics for REST, plus the same three operations for MCP.
SunoAPI.org jobs use a token-protected callback only to schedule polling and
are reconciled through the provider record-info endpoint. MCP jobs use their
returned task ID and `suno_get_task`. Music completion imports both returned
tracks before the generation is marked complete. See [Suno
Integration](SUNO.md) for the operator and transport contracts.

### VideoDraft hosted MCP

A `videodraft` Connection calls `https://app.videodraft.ai/api/mcp` directly
from WordPress with a VideoDraft personal access token. Connection testing
reads `tools/list`, verifies the generation and Project tools used by the
integration, and provisions active Templates from the live input schemas for
image, video, audio, voiceover, music, and sound-effect generation.

Image and video tools return asynchronous jobs that the batch worker polls
with `check_generation_status`; completed image, video, and audio URLs are
imported through the normal media and provenance path. Bound local media uses
VideoDraft's presigned upload flow. The Node CLI is the protocol reference,
not a WordPress runtime dependency. See [VideoDraft Connection and
Sync](VIDEODRAFT.md).

### Manually managed providers

Connections may record other provider types, and users can always generate in
an external web application and attach the downloaded result. Provider names
present in the Connection schema are not a promise of a direct executable
adapter. Hosted services can impose their own prices, credits, quotas,
moderation, licenses, and availability; World Graph Studio does not override
those terms.

## Templates and input bindings

An active Template records the provider, Connection, modality, provider
template ID, optional workflow JSON, model/checkpoint information, and default
configuration. Templates are WordPress configuration records, not permission
to run arbitrary server code.

For a direct image, an author can still edit the suggested prompt, select an
active runnable image Template, choose featured-media and linked-Asset behavior,
and queue one job. For representative generation, the author first reviews the
read-only plan and its image/video counts, chooses optional Template overrides,
and starts the durable item or Project batch.

Runnable Template lists exclude disabled Connections, mismatched output types,
and Templates whose required bindings cannot be resolved for every applicable
task. `Template_Bindings` resolves declared media slots from a featured image,
the post's asset gallery, or an SCF/post-meta field. Invalid combinations fail
before provider execution.

For each task, Template resolution follows this cascade:

1. an explicit image or video Template supplied with the batch request;
2. per-post `_worldgraph_generation_template_{intent}` metadata;
3. the intent mapping in `worldgraph_generation_preferences_v1`;
4. the output-type mapping in that option;
5. the managed local ComfyUI text-to-image Template for image output; and
6. the first runnable compatible Template.

Every candidate is revalidated against the source post, output type, input
bindings, and Connection availability. See [Generate Preferences and
Generation Intents](GENERATE_PREFERENCES.md) for the option shape and intent
contract.

## Catalogs, manifests, and readiness

ComfyUI catalog and requirement work is delivered:

- catalogs are cached per Connection in `comfy_template_catalog`;
- operator choices are stored separately in `enabled_templates`;
- MCP-capable Connections discover provider templates by registered task type;
- HTTP-only local Connections synthesize entries from the registered modality
  list and inspect `/object_info`;
- enabled entries can be materialized into `worldgraph_template` posts;
- requirement manifests derive node classes and model files from the workflow;
- the Template editor and REST API can validate requirements; and
- provider-advertised model URLs can be sent to an MCP
  `download_models` tool when that tool is available.

Model downloads are never performed by arbitrary shell execution inside
WordPress. If no download tool or source URL is available, the operator installs
the requirement in ComfyUI and rechecks readiness. Custom nodes remain an
operator-managed ComfyUI concern.

See [Comfy Template Catalog](COMFY_TEMPLATE_CATALOG.md) for the complete current
catalog flow.

## Job lifecycle

Generation records are stored as internal `worldgraph_gen` posts. Submission
persists the record before external execution and schedules
`worldgraph_process_generation_batch`.

Representative-media batches are also durable `worldgraph_gen` posts, marked
with `_worldgraph_gen_batch_kind = representative_media`. The parent stores the
root post, `item` or `project` scope, requester, optional idempotency key, a
snapshot containing each source/intent/type/Template and prompt hash, child
IDs, total, and aggregate status. Child jobs store
`_worldgraph_gen_batch_id` and `_worldgraph_gen_intent`. This separation lets a
Project batch run for hours or days while every child remains independently
observable through the ordinary worker lifecycle.

Planning performs no writes. Starting validates edit permission for every
source and resolves all required Templates before creating the batch. A
requester-scoped non-empty idempotency key returns the prior batch for the same
root instead of creating duplicate provider work. Batch summaries derive their
counts from persisted child states and report `active`, `completed`,
`completed_with_errors`, or `pending`.

The worker:

1. polls up to ten submitted jobs;
2. submits up to five queued jobs;
3. records the provider's remote job identifier;
4. reschedules itself after 60 seconds while work remains; and
5. imports completed media before recording success.

The durable states are:

| State | Meaning |
| --- | --- |
| `queued` | Persisted and waiting for the batch worker |
| `submitted` | Accepted by an asynchronous provider and being polled |
| `completed` | Provider work and required media import succeeded |
| `failed` | Validation, provider execution, or media import failed |
| `cancelled` | Cancelled in World Graph Studio |

Cancelling a representative batch cancels child jobs that remain `queued`;
already submitted or terminal children retain their real state and continue to
contribute to the aggregate summary. The response reports how many queued jobs
were stopped.

ElevenLabs and VideoDraft audio may return completed results synchronously.
ComfyUI, fal, Suno, and VideoDraft image/video tools can return asynchronous
jobs. A local ComfyUI Connection with an MCP endpoint can
fall back to the local HTTP adapter when MCP submission fails. Suno REST and
MCP Templates do not fall back across transports because their credentials and
provider contracts are different.

## Result import and provenance

Completed image, video, and audio outputs are downloaded through WordPress,
validated by type and size, and inserted as media attachments. Multiple image
or audio results are retained when the provider returns them. Depending on the
originating request, the primary attachment can become the post's featured
media and a linked `worldgraph_asset` record can be created.

Text-output jobs such as Suno lyrics retain their normalized provider result on
the generation record and do not create a media attachment.

Generation metadata retains the source post, Template, provider, Connection,
workflow/provider-template reference, prompt, parameters, timestamps, remote
job ID, result attachment IDs, and terminal status. Raw synchronous audio bytes
are removed before the provider result is persisted.

Recent diagnostic events are available under the Generation Log admin page.
Logs and generation records must not contain authorization headers or secret
keys.

## REST surface

The canonical REST base is `/wp-json/worldgraph/v1/`.

| Method and route | Purpose |
| --- | --- |
| `GET /assets/generate/prompt?post_id={id}` | Suggested prompt plus runnable image Templates |
| `POST /assets/generate` | Queue an image for a Story Graph post |
| `GET /assets/generate/plan?post_id={id}&scope=item\|project` | Preview representative outputs, prompts, runnable Templates, defaults, and the latest batch |
| `POST /assets/generate/batches` | Validate and start a durable item or Project representative-media batch |
| `GET /assets/generate/batches/{id}` | Read aggregate batch progress and child jobs |
| `POST /assets/generate/batches/{id}/cancel` | Cancel cancellable children and return refreshed batch status |
| `POST /generation` | Create a Template-backed generation record |
| `GET /generation/{id}` | Read job status and identity |
| `POST /generation/{id}/cancel` | Mark a job cancelled |
| `GET /generation/asset/{asset_id}/history` | Read generation history for an Asset |
| `GET /generation/templates/{id}/requirements` | Read and optionally validate a Template manifest |
| `GET/POST /connections` | List or create provider Connections |
| `GET/PUT/DELETE /connections/{id}` | Manage one Connection |
| `GET /connections/{id}/resolve` | Read normalized Connection configuration |
| `POST /connections/{id}/test` | Run the adapter health check |
| `POST /connections/sync` | Refresh the provider capability snapshot |

Catalog enable, materialize, and download controls are current admin actions,
not public catalog REST routes. They require an edit-capable administrator and
a `worldgraph_conn_configurator` nonce.

## WordPress Abilities and MCP exposure

On WordPress versions that provide `wp_register_ability`, World Graph Studio
registers generation-related abilities including:

- `worldgraph/templates-manifest`;
- `worldgraph/template-requirements`;
- `worldgraph/suggest-asset-prompt`; and
- `worldgraph/generate-asset`.

An installed WordPress MCP adapter may expose public abilities to external MCP
clients. The in-editor filmmaking advisors do not autonomously invoke these
abilities; their current LLM requests use `tool_choice: none`. See
[ComfyUI and Prompt Guidance](COMFY_AND_PROMPT_AGENTS.md).

## Security and operating boundaries

- Generation and Connection operations use WordPress capability checks and
  nonces or REST authentication.
- Secrets belong in deployment configuration where supported; never commit
  them or place them in client-side JavaScript.
- A local ComfyUI without authentication must be protected by the deployment
  network boundary.
- Treat provider catalogs, workflow descriptions, URLs, and error text as
  untrusted input.
- WP-Cron must be triggered reliably in production.
- World Graph Studio remains useful for writing, planning, analysis, and asset
  management with no generation Connection.

## Implementation map

- [Provider registry](../../wordpress/wp-content/plugins/worldgraph/includes/utils/connection-adapters.php)
- [Generation modalities](../../wordpress/wp-content/plugins/worldgraph/includes/utils/generation-modality.php)
- [Generation worker](../../wordpress/wp-content/plugins/worldgraph/includes/utils/generation-batch.php)
- [Representative workflows and batches](../../wordpress/wp-content/plugins/worldgraph/includes/utils/class-generation-workflows.php)
- [Generation REST controller](../../wordpress/wp-content/plugins/worldgraph/includes/rest-api/generation-controller.php)
- [Assets metabox controller](../../wordpress/wp-content/plugins/worldgraph/includes/rest-api/asset-generation-controller.php)
- [Asset import and provenance](../../wordpress/wp-content/plugins/worldgraph/includes/utils/class-asset-generator.php)
- [ComfyUI catalog](../../wordpress/wp-content/plugins/worldgraph/includes/utils/comfy-catalog.php)
- [ComfyUI manifests](../../wordpress/wp-content/plugins/worldgraph/includes/utils/comfy-manifest.php)
- [Setup and Connections](../Deployment_and_Connections.md)
- [Suno Integration](SUNO.md)
