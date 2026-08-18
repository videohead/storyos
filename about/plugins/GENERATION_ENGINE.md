# StoryOS Generation Engine

## Purpose

The Generation Engine connects StoryOS story data and editorial intent to
generative media workflows. WordPress is the application boundary and the
source of truth for configuration, generation records, and assets. ComfyUI is
the supported generation environment, accessed through the Model Context
Protocol (MCP).

The engine is intentionally small at the integration boundary:

```text
Story Graph in WordPress
        |
        v
Generation intent and template configuration
        |
        v
WordPress generation record and MCP request
        |
        v
Comfy Cloud MCP or an MCP-capable local client
        |
        v
Generated media registered as StoryOS assets
```

There is no separate execution application in this architecture. Keep
generation execution within WordPress and use MCP as the external workflow
boundary.

## Runtime Modes

### Comfy Cloud MCP

Comfy Cloud is the supported server-side generation connection.

- Endpoint: `https://cloud.comfy.org/mcp`
- Authentication: `STORYOS_COMFY_API_KEY` or the WordPress setting
  `storyos_comfy_api_key`
- Client: `StoryOS\\Utils\\Comfy_Cloud_MCP`
- Transport: Streamable HTTP using the MCP JSON-RPC protocol
- WordPress responsibility: validate the request, call the MCP tool, persist
  the generation record, and register returned media as StoryOS assets

The WordPress client establishes an MCP session and invokes the Comfy Cloud
workflow tool with the selected template, prompt, and approved parameters.
Credentials must never be written into generation records, templates, asset
metadata, logs, or capability snapshots.

### Local ComfyUI HTTP API

Local ComfyUI has a minimal WordPress HTTP client in
`includes/utils/local-comfyui.php`.

- Endpoint: a WordPress-container-reachable ComfyUI base URL, such as
  `http://host.docker.internal:8188` for a Lando host installation.
- Transport: `POST /prompt`, `GET /history/{prompt_id}`, `POST /upload/image`,
  `GET /object_info`, and `GET /view`.
- Authentication: none by default; an exposed local endpoint must be protected
  by the deployment network boundary.
- Configuration: a Template's modality plus either its pasted API-format
  workflow JSON or the built-in graph generated for that modality.
- WordPress responsibility: resolve and upload the Template's media inputs,
  refuse the job when ComfyUI is missing a required node or model, queue and
  poll the request, then import the returned image and/or video outputs as
  StoryOS assets.

## Template Modalities

A Template declares a `modality` that determines its inputs, its built-in
ComfyUI graph, and the requirements StoryOS validates. The registry is
`StoryOS\\Utils\\Generation_Modality` in
`includes/utils/generation-modality.php`.

| Modality | Output | Required inputs | Optional inputs |
| --- | --- | --- | --- |
| `text_to_image` | image | `prompt` | `negative_prompt` |
| `image_to_image` | image | `image` | `prompt`, `negative_prompt` |
| `image_text_to_image` | image | `image`, `prompt` | `negative_prompt` |
| `text_to_video` | video | `prompt` | `negative_prompt` |
| `text_image_to_video` | video | `image`, `prompt` | `negative_prompt` |
| `video_to_video` | video | `start_frame` | `end_frame`, `prompt`, `negative_prompt` |
| `video_with_audio` | video | `prompt`, `audio` | `negative_prompt` |

Every input slot is addressed in a workflow by a `{{slot}}` placeholder:
`{{prompt}}`, `{{negative_prompt}}`, `{{image}}`, `{{start_frame}}`,
`{{end_frame}}`, `{{video}}`, `{{audio}}`. Media slots accept a WordPress
attachment ID or a URL; the client uploads the file into ComfyUI's input
directory and substitutes the resulting filename before submission.

Built-in video graphs target the LTX-Video nodes shipped with ComfyUI
(`EmptyLTXVLatentVideo`, `LTXVConditioning`, `LTXVImgToVideo`, `LTXVAddGuide`,
`LTXVCropGuides`) and mux output through `CreateVideo` and `SaveVideo`. A
Template that needs a different model family pastes its own API-format graph;
requirement discovery then reads that graph instead.

## Requirement Manifests and Preflight

`StoryOS\\Utils\\Comfy_Manifest` in `includes/utils/comfy-manifest.php` derives
what a Template asks ComfyUI for and whether the connected instance can supply
it:

- Node requirements come from the workflow's `class_type` values, falling back
  to the modality's declared node list for built-in graphs.
- Model requirements come from recognized loader inputs (`ckpt_name`,
  `unet_name`, `vae_name`, `clip_name*`, `lora_name`, `control_net_name`,
  `style_model_name`, `clip_vision_name`, `gligen_name`, `upscale_model_name`)
  and map to the `models/` sub-directory each file belongs in.
- Validation reads `GET /object_info` (cached for five minutes) and compares
  each requirement against the node list and the loader's installed-file enum.
- A Template may declare download sources in its Model Requirements JSON:
  `[{"filename":"…","folder":"…","url":"…"}]`.

The Template editor shows this as a ComfyUI Requirements panel with a
**Check ComfyUI** action and an **Install missing models** action; the latter
forwards declared URLs to the MCP `download_models` tool. Generation submission
runs the same check and fails the job in StoryOS with a specific message rather
than letting ComfyUI raise an opaque execution error. An unreachable catalog is
treated as a connectivity problem and does not block submission.

## Reciprocal Discovery

Discovery works in both directions across MCP:

- **StoryOS to Comfy**: `Comfy_Cloud_MCP::available_tools()` reads `tools/list`
  and gates the template-system calls `list_templates`, `get_template`, and
  `download_models`, so an MCP server that does not implement a tool reports
  that directly instead of failing inside a job.
  `Comfy_Manifest::discover()` maps a StoryOS modality to a Comfy task type and
  returns candidate templates with their required nodes, models, and model URLs.
- **Comfy to StoryOS**: the `storyos/templates-manifest` resource and the
  `storyos/template-requirements` tool publish each Template's modality, input
  slots, required nodes, model files, and validation state.
- **REST**: `GET /storyos/v1/generation/templates/{id}/requirements` returns the
  same manifest, with `validate=false` to skip the live ComfyUI check.

This is deliberately not a generic ComfyUI workflow manager. It is a bridge for
a known workflow while the Connections-backed local workflow project is
developed.

### Major Planned Work: Connections-Backed Local Workflows

Local ComfyUI must not remain dependent on a global endpoint and free-form
workflow option. The `storyos_connection` CPT is the intended control-plane
record for the local endpoint, environment, secret reference, allowed models,
enabled structures, quota configuration, and verification state.

Before local ComfyUI can be described as a complete StoryOS generation
connection, implement all of the following:

1. A versioned workflow catalog linked to a specific Connection, including
   parameter schemas, input/output bindings, Story Graph mappings, validation,
   and compatibility metadata. *Partially delivered: Templates now declare a
   modality with typed input slots, and the built-in graph per modality.*
2. Dependency manifests for checkpoints, LoRAs, VAEs, custom nodes, and
   workflow assets, including source provenance, checksums, licenses, versions,
   and compatible ComfyUI versions. *Partially delivered: `Comfy_Manifest`
   derives node and model requirements and reads declared download sources;
   checksums, licenses, and version pinning are still outstanding.*
3. An administrator-approved dependency discovery and installation workflow.
   Downloads must use allowlisted sources, report storage requirements and
   progress, preserve audit data, and support retries and recovery.
   *Partially delivered: installation is routed through the MCP
   `download_models` tool from the Template editor; allowlisting, progress, and
   audit data are still outstanding.*
4. ComfyUI capability and installed-dependency synchronization, connection
   health checks, and preflight validation that prevents jobs from starting
   against an incompatible workflow or missing model. *Delivered for node and
   model availability through `GET /object_info`.*
5. Per-connection queue routing, cancellation/status semantics, artifact
   output selection, provenance capture, and realistic end-to-end coverage.

Do not add arbitrary remote download or shell-execution behavior as a shortcut
for this work. The final design must preserve WordPress permissions, explicit
administrator consent, auditability, and the existing asset-provenance model.

### No ComfyUI Connection

StoryOS remains useful without media generation. Users can write, manage the
Story Graph, use filmmaking abilities, plan production, run continuity work,
import and export scripts or EDL data, and register externally generated media.

## WordPress Ownership

WordPress owns the following concerns:

- Story Graph entities and their relationships.
- Generation templates and editorial configuration.
- Provider connection settings and secret references.
- Generation records and their user-visible state.
- Prompt, template, parameter, and Story Graph provenance.
- WordPress media attachments and StoryOS asset metadata.
- Permissions, nonces, audit information, and REST resources.
- Abilities exposed to MCP clients.

ComfyUI owns workflow execution and generation-specific behavior. StoryOS must
not imitate a ComfyUI API or claim that every ComfyUI workflow supports every
StoryOS generation structure.

## WordPress Abilities and MCP

The WordPress Abilities API is the interface for AI and MCP clients. The StoryOS
Abilities registration is in:

`wordpress/wp-content/plugins/storyos/includes/ai-editor/class-ai-abilities.php`

The WordPress MCP Adapter can expose public abilities as MCP tools, resources,
and prompts. StoryOS ability groups currently cover:

### Tools

- `storyos/chat` - Ask a StoryOS filmmaking ability for help with a story task.
- `storyos/analyze` - Analyze the current post or story content.
- `storyos/generate` - Prepare or request generation from WordPress context.
- `storyos/continuity-check` - Check continuity against Story Graph context.
- `storyos/template-requirements` - Report a Template's ComfyUI node and model
  requirements and whether the configured instance satisfies them.

### Resources

- `storyos/post-context`
- `storyos/character-context`
- `storyos/scene-context`
- `storyos/templates-manifest` - Discover active generation templates and
  their provider-neutral configuration schemas.

### Prompts

- `storyos/story-review-prompt`
- `storyos/continuity-prompt`

Every ability must define an input schema, output schema, permission callback,
and MCP metadata appropriate to its behavior. Use readonly, destructive, and
idempotent annotations accurately. A public ability is a deliberate external
contract, not an internal helper exposed for convenience.

The `storyos/templates-manifest` resource is read-only and requires the
WordPress `edit_posts` capability. It is exposed at
`storyos://templates-manifest` and returns published Templates CPT records
whose `status` is `active`. Each entry includes the template ID and slug,
display name, description, generation structure, modality, output type, input
slots, required ComfyUI nodes, model files, provider type, version,
configuration schema, and default values. Invalid JSON configuration is
returned as an empty object so discovery cannot make an invalid template
executable.

The manifest is discovery metadata, not a workflow execution surface. MCP
clients must still submit a validated request through the supported WordPress
generation path; credentials, raw provider responses, and arbitrary executable
ComfyUI workflow content are never included in the manifest.

The MCP Adapter is responsible for translating WordPress abilities into MCP
operations. StoryOS should register abilities through WordPress and should not
create a second MCP server for the same capabilities.

## Generation Request Flow

1. A user works from a Story Graph entity, template, or Gutenberg context.
2. WordPress resolves the selected template and explicit SCF/post-meta values.
3. WordPress assembles the prompt, references, output requirements, and
   provenance into a generation request.
4. WordPress validates required fields, permissions, connection configuration,
   and supported Comfy Cloud MCP parameters.
5. WordPress persists a generation record before external execution.
6. The WordPress MCP client invokes the selected Comfy Cloud workflow.
7. WordPress records the returned result and associates generated media with
   the Story Graph asset pipeline.
8. The user can inspect the generation record, source context, and resulting
   asset from WordPress.

The generation record is the durable user-facing record. It should include the
template identity, prompt provenance, selected parameters, initiating user,
target Story Graph entity, connection mode, result metadata, and sanitized
error details. It must not include API keys, authorization headers, or complete
unredacted remote responses.

## Templates and Configuration

Templates are WordPress editorial records, not executable code. They should
provide reusable defaults and explicit bindings to Story Graph or SCF values.

Each template should define, as applicable:

- Stable template identifier and revision.
- Human-readable title, description, and category.
- Intended media type and output expectations.
- Prompt and negative-prompt fields.
- Reference roles such as character, location, scene, or style.
- ComfyUI workflow or Comfy Cloud template name where required.
- Allowed user-editable parameters.
- SCF field mappings and provenance rules.

Configuration precedence is:

1. Template defaults.
2. Story Graph and SCF values.
3. Explicit user input.

User input must be validated before the request reaches MCP. Provider or
workflow constraints may reject an invalid value, but must not silently replace
the user's editorial intent.

Template revisions should use WordPress revision and metadata facilities so
that published configurations remain auditable and restorable. Do not create a
parallel version store outside WordPress.

## Remaining Epic: Asset-to-Template Request Packaging

The existing StoryOS Assets metabox and `storyos_template` CPT are currently
separate admin surfaces. The Assets metabox owns the featured attachment and
supporting gallery for a Story Graph post; the Templates CPT owns reusable
provider-neutral generation configuration. The metabox does not yet select a
template or produce a ComfyUI request package.

The target flow is:

1. The user selects an active template and revision for an asset-generating
  story item, without changing the featured-asset or gallery behavior.
2. WordPress resolves template defaults, Story Graph and SCF bindings, and
  explicit user values according to the configuration precedence above.
3. WordPress validates the generation structure, required inputs, references,
  output requirements, connection capabilities, and workflow compatibility.
4. WordPress persists a normalized request package and generation record before
  invoking ComfyUI MCP.
5. The MCP adapter maps the normalized package to an approved ComfyUI
  operation, receives the result, and sends returned media through the
  existing WordPress media and StoryOS asset-provenance pipeline.

The normalized package is a WordPress-owned contract. At minimum it contains:

- Template identity, revision, generation structure, and provider type.
- Resolved prompt, negative prompt, references, parameters, and SCF bindings.
- Target project or Story Graph entity and requested output type/format.
- Selected connection mode, workflow identity, and compatibility metadata.
- Initiating user, request timestamp, and provenance identifiers.

It must not contain API keys, authorization headers, unredacted remote
responses, or arbitrary executable workflow content. A ComfyUI API-format
workflow may be referenced or materialized by an approved connection-specific
adapter, but it is not the canonical template or request record.

This epic is incomplete until template selection is available from the
relevant Assets workflow, invalid combinations fail before queueing, the
normalized package can be mapped to the supported ComfyUI MCP operation, and
the resulting media is linked back to the initiating Story Graph item with
auditable provenance. Until then, documentation must describe the Assets
metabox and Templates CPT as separate capabilities.

## Comfy Cloud MCP Contract

The WordPress Comfy Cloud client is intentionally limited to the MCP contract.
Keep transport and authentication details in:

`wordpress/wp-content/plugins/storyos/includes/utils/comfy-cloud-mcp.php`

The client is responsible for:

- Establishing an MCP session.
- Sending JSON-RPC requests over Streamable HTTP.
- Calling approved Comfy Cloud tools.
- Decoding JSON and event-stream response bodies.
- Converting transport and tool failures into `WP_Error` values.
- Redacting credentials from all persisted or logged data.

Generation-specific policy belongs in the Generation Engine and WordPress
validation code, not in the low-level transport client. Keep the client small
and do not add unrelated provider abstractions to it.

## Capability Information

Capability information is a WordPress-managed snapshot for the supported
Comfy Cloud MCP connection. It helps the admin UI and request validation
present available media types and template execution options.

The current synchronization helper is:

`wordpress/wp-content/plugins/storyos/includes/utils/capability_sync.php`

Capability data may include:

- Connection type and display label.
- MCP endpoint identity without secrets.
- Supported media categories.
- Supported StoryOS template operations.
- Synchronization timestamp.

Capability snapshots are descriptive configuration, not executable provider
code. Do not reintroduce dynamic provider discovery or a generic adapter
registry. A new external generation service needs an explicit WordPress-owned
connector design and separate documentation.

## Generation Records and Assets

Generation records belong in WordPress and must be connected to the Story Graph
entity that motivated the request. Generated files become normal WordPress media
attachments and StoryOS assets with provenance.

Minimum provenance should identify:

- Source project, scene, shot, character, location, or other entity.
- Template and revision.
- Prompt and user-supplied creative inputs.
- ComfyUI or Comfy Cloud connection mode.
- Model or workflow information returned by the MCP operation.
- Creation time and initiating user.
- Media attachment and asset identifiers.

Asset metadata must distinguish generated media from external media that a user
uploads or registers manually. External URLs are references and must not be
treated as durable local media until the user explicitly imports the asset.

## REST and Admin Surfaces

StoryOS REST resources are WordPress-native and use the project namespace:

`/api/storyos/v1`

Generation-related resources may include:

- Templates and template revisions.
- Generation records.
- Assets and provenance.
- Comfy Cloud connection configuration.
- Capability snapshots.

REST controllers must use WordPress permissions and nonces where appropriate,
validate request schemas, sanitize input, escape output, and return actionable
`WP_Error` responses. The REST API should expose StoryOS resources, not proxy
raw ComfyUI or Comfy Cloud APIs.

The Generation Engine admin UI should make the following states clear:

- Comfy Cloud configured or not configured.
- Local MCP available as an optional creator workflow.
- Template available and valid or blocked by configuration errors.
- Generation record accepted, failed, or completed.
- Asset returned and linked or unavailable.

## Security and Reliability

- Prefer `STORYOS_COMFY_API_KEY` in deployed environments.
- Never commit API keys or place them in client-side JavaScript.
- Use capability checks and WordPress permissions for generation actions.
- Sanitize prompts and parameters before persistence and external requests.
- Escape all values rendered in admin screens or REST responses.
- Keep remote error messages sanitized and bounded.
- Use WordPress locks or equivalent guards for bounded background work.
- Do not block a web request while waiting for long-running media generation.
- Make repeated requests safe to identify and handle at the WordPress record
  level.

## Development Checklist

When changing the Generation Engine:

1. Read the existing WordPress implementation and the relevant Story Graph
   specification.
2. Keep the change inside the WordPress plugin, Abilities surface, MCP client,
   template records, or asset pipeline that owns it.
3. Preserve the distinction between Comfy Cloud MCP and optional local ComfyUI
   MCP workflows.
4. Validate permissions, schemas, secret handling, and asset provenance.
5. Test the narrowest affected WordPress path before broad integration tests.
6. Update this document and the deployment or REST specification when the
   public integration contract changes.

## Current Implementation Surfaces

- Main plugin bootstrap:
  `wordpress/wp-content/plugins/storyos/storyos.php`
- Comfy Cloud MCP client:
  `wordpress/wp-content/plugins/storyos/includes/utils/comfy-cloud-mcp.php`
- WordPress generation batch:
  `wordpress/wp-content/plugins/storyos/includes/utils/generation-batch.php`
- Capability snapshot helper:
  `wordpress/wp-content/plugins/storyos/includes/utils/capability_sync.php`
- AI and MCP abilities:
  `wordpress/wp-content/plugins/storyos/includes/ai-editor/class-ai-abilities.php`
- ComfyUI integration plugin:
  `wordpress/wp-content/plugins/storyos/plugins/comfy-generate/`
- Deployment and connection guidance:
  `about/Deployment_and_Connections.md`
- WordPress architecture:
  `about/StoryOS_Architecture.md`

## Definition of Done

- [x] Generation requests are created from valid Story Graph or editor context.
- [x] Templates and revisions are stored and audited in WordPress.
- [x] Comfy Cloud MCP credentials are protected and configurable.
- [x] The WordPress MCP client handles valid and invalid MCP responses.
- [x] Generation records persist sanitized request and result provenance.
- [x] Returned media is linked to StoryOS assets.
- [x] WordPress Abilities expose the supported AI and story context actions.
- [x] Optional local ComfyUI MCP workflows are documented without requiring
      server-side local ComfyUI access.
- [x] REST and admin surfaces enforce WordPress permissions and validation.
- [x] No external execution service is required by the Generation Engine.
- [ ] Live end-to-end validation of ComfyUI generation models is completed.
