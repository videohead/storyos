Mickey Mouse

# StoryOS Generation Engine - AI Development Task List

**Project:** StoryOS Generation Engine
**Status:** In Progress
**Priority:** High

## Epic 1: Decouple From ComfyUI

### Current Migration Update
- Generation submission, status, and cancellation in WordPress now target a ComfyUI MCP contract (`submit`, `status`, `cancel`, `artifacts`) instead of direct orchestrator queue routes.
- Generation Engine settings now prioritize a dedicated ComfyUI MCP server URL, while retaining legacy orchestrator URL fallback for backward compatibility.

### Goal
Transform the existing generation plugin into a provider-agnostic generation platform.

### Tasks
- [x] Rename plugin architecture from `comfy-generate-button` to `storyos-generation-engine`
- [x] Remove provider-specific business logic from core plugin
- [x] Create Provider abstraction layer
- [x] Create Provider Registry
- [x] Create Provider Loader
- [x] Support dynamic provider discovery
- [x] Create provider capability framework
- [x] Define provider lifecycle events
- [x] Migrate existing ComfyUI logic into provider implementation

### Provider Lifecycle Events

The initial lifecycle contract is intentionally centered on the core generation outcome:

`Generation Request -> Provider Submission -> Remote Polling -> Artifact Download -> Asset Ingestion -> Completed`

Events are emitted by `orchestrator/provider_events.py` through the shared `ProviderEventBus`. Current event types are:

- `provider.request.received`
- `provider.submission.started`
- `provider.submitted`
- `provider.poll.started`
- `provider.poll.updated`
- `provider.artifacts.available`
- `provider.artifact.downloaded`
- `provider.asset.ingested`
- `provider.completed`
- `provider.failed`

Each event includes the job ID, provider type, optional connection ID, optional remote job reference, lifecycle status, progress, timestamp, and a sanitized payload. The payload may contain normalized artifact metadata such as filename, MIME type, byte size, SHA-256, WordPress media ID, and downloadable source URL. It must not contain credentials or unredacted provider responses.

The current prompt-to-downloadable-asset path is instrumented for the Veo, Nova Reel, and ComfyUI workers. Celery remains the orchestration layer, while ComfyUI submission, polling, cancellation, and artifact URL construction are owned by the provider adapter.

### Provider Execution Topology

ComfyUI is a major execution environment in StoryOS, but it is not the universal provider boundary. A provider may be available through one or more distinct integration modes:

| Integration mode | Provider execution location | Example | ComfyUI requirement |
| --- | --- | --- | --- |
| `comfy_native` | ComfyUI workflow and installed model/node set | Local diffusion or video workflow | Required for the connection |
| `comfy_partner` | ComfyUI partner node or managed integration | A hosted provider exposed as a ComfyUI node | Required for the connection |
| `external_adapter` | Orchestrator provider adapter calls the provider API directly | Google Veo or Amazon Nova Reel | Not required |
| `hybrid` | Orchestrator and ComfyUI both participate in one generation flow | Preprocessing in ComfyUI followed by an external provider | Required only for the selected workflow |

Provider type, provider connection, and execution topology must remain separate:

- A Provider Type describes the adapter contract and provider behavior.
- A Provider Connection describes credentials, endpoint, region, account, and enabled models.
- An Execution Topology describes whether the selected connection requires ComfyUI, an external API, or both.

Capability descriptors must therefore declare topology and dependency requirements at the model or connection level. A capability appearing in the ComfyUI documentation or node catalog does not mean that every StoryOS connection must route through ComfyUI. Conversely, a ComfyUI connection may expose capabilities that are only valid for its installed models, custom nodes, workflow templates, and local hardware.

The initial provider strategy intentionally begins with external adapters for Google Veo and Amazon Nova Reel. Their official APIs provide the authoritative submission, polling, and artifact contracts, and neither should require ComfyUI merely because ComfyUI may later gain a partner-node integration. ComfyUI remains a first-class provider execution environment for workflows where it is the actual runtime, not a mandatory proxy for all providers.

### ComfyUI Connector Proof

ComfyUI connectivity must not be considered ready merely because `/history` responds. Before allowing a ComfyUI generation connection to be used, StoryOS should collect explicit evidence for:

- ComfyUI endpoint reachability
- Runtime and device availability
- Every node class required by the selected workflow
- At least one output node capable of producing an artifact
- Successful workflow submission
- Completed history output
- Successful retrieval through ComfyUI's `/view` artifact route, including verified response bytes

The readiness implementation is `orchestrator/comfyui_readiness.py` and is exposed through:

`POST /providers/comfyui/readiness`

The endpoint supports two proof levels:

- Static readiness checks dependencies without running generation.
- Opt-in end-to-end smoke testing submits the rendered workflow and proves that at least one output can be downloaded.

An HTTP health response is not sufficient proof of asset delivery. A connector must report missing nodes, missing output nodes, submission failures, polling timeouts, empty outputs, or failed downloads as actionable readiness failures. This is specifically intended to prevent a user from selecting ComfyUI successfully and then receiving no downloadable asset.

### Provider Capability Framework

Provider capabilities are stored as versioned JSON descriptors in the Orchestrator under:

`orchestrator/providers/capabilities/`

The shared descriptor contract is defined by `schema.json`. Each provider descriptor contains:

- Provider type and adapter version
- Capability schema version
- Discovery mode
- Model-specific structures and constraints
- Input and output media constraints
- Dimensions, aspect ratios, duration, and frame rates
- Prompt, reference, and audio capabilities
- Geographic or runtime availability
- Official documentation sources and retrieval date

Provider descriptors are data, not provider execution code. Provider adapters remain responsible for authentication, request submission, polling, cancellation, and artifact retrieval.

### Provider Discovery Tool

Provider discovery has two responsibilities:

1. Discover executable provider adapters from the Orchestrator provider package.
2. Discover and manage the JSON capability descriptor associated with each adapter.

The discovery service is implemented in `orchestrator/providers/discovery.py` and reports adapter/descriptor drift through:

`GET /providers/discovery`

Descriptor lifecycle:

- A provider adapter is discovered by the dynamic provider loader.
- The discovery service reports whether its JSON descriptor is present and valid.
- A missing descriptor can be scaffolded with a `connection-defined` model.
- An existing descriptor can be updated through a validated deep merge.
- Updates are written atomically and may require an expected provider version.
- Credentials and provider API responses are never written into capability descriptors.

Discovery modes:

- `static`: capabilities come from maintained provider documentation.
- `runtime`: capabilities are refreshed from a provider API.
- `connection`: capabilities vary by account, region, or connection configuration.
- `workflow`: capabilities are resolved from a workflow, installed model, or custom-node runtime, as with ComfyUI.

For provider research, official API and model documentation is the source of truth. Current descriptors have been researched for ComfyUI, Google Veo, and Amazon Nova Reel and include source URLs with retrieval dates. Provider changes should update the JSON descriptor and its source metadata before changing validation behavior.

### Deployment Dependency Tracking

Media discovery has an operating-system dependency path in addition to the Python dependency path. FFmpeg and FFprobe are system binaries and must not be added to `requirements.txt`. The deployment matrix, installation commands, version policy, and shared-host fallback are tracked in:

`orchestrator/SYSTEM_DEPENDENCIES.md`

The initial media tooling requirement is:

- `ffprobe` for media inspection and metadata discovery
- `ffmpeg` for normalization, thumbnails, frame extraction, audio extraction, and transcoding

The `ffmpeg` operating-system package normally supplies both binaries. Local, Docker, VPS, and shared-host deployments must verify binary availability separately. A provider can advertise a valid output capability while the deployment still lacks local media-processing capability, so these checks remain distinct.

The current delivery priority is local ComfyUI execution and reliable asset return. VPS and shared-host dependency portability is deliberately a later deployment phase and must not delay connector proof, ComfyUI API integration, or local artifact ingestion.

## Epic 2: Generation Provider Framework

### Goal
Support multiple AI generation platforms through a common internal orchestration contract.

This is not a new public provider API, hosted generation API, compatibility promise, or support aegis for third-party platforms. StoryOS owns only the internal boundary between its control plane, Orchestrator, and provider adapters. Each provider adapter remains responsible for translating that internal request into the provider's official API or ComfyUI workflow and for handling provider-specific behavior.

StoryOS must not:

- Re-publish or imitate third-party provider APIs
- Promise that every provider supports every StoryOS generation structure
- Hide provider-specific limitations behind a falsely universal contract
- Claim support for a provider or integration topology that has not passed its connector readiness and asset-delivery checks

Provider support means that StoryOS maintains and tests a specific adapter, capability descriptor, execution topology, and asset-ingestion path for the supported integration. It does not transfer responsibility for the provider's external service, API availability, pricing, regions, models, or terms of use to StoryOS.

Every supported integration must explicitly declare these user-visible operations in its capability descriptor:

- `generate`: the adapter can submit the supported generation request.
- `download_artifacts`: the adapter can retrieve the generated result and hand it to the asset pipeline.

Both operations are mandatory for support. A provider that can submit work but cannot return a downloadable artifact is not a supported generation integration. Polling and cancellation are additional lifecycle capabilities, but they do not substitute for generation and artifact download.

### Tasks
- [x] Create ProviderInterface
- [ ] Create authentication abstraction
- [ ] Create provider health checks
- [x] Create capability discovery endpoints
- [x] Create provider configuration UI
- [x] Create API credential storage system
- [ ] Create provider status dashboard

### Providers
- [x] ComfyUI Provider
- [ ] Google Veo Provider
- [ ] Nova Reel Provider
- [ ] Runway Provider
- [ ] Kling Provider
- [ ] MiniMax Provider
- [ ] Luma Provider
- [ ] Seedance Provider
- [ ] Wan Provider
- [ ] Hunyuan Provider

## Epic 3: Generation Structure Framework

### Goal
Support extensible generation modalities.

Generation Structures are provider-neutral story-intent contracts stored in the Orchestrator under `orchestrator/structures/`. They describe the editorial goal, available input modalities, desired output modalities, references, and optional creative hints without exposing a provider's API or workflow format. They are not provider requirement schemas. Providers own generation parameters, hard limits, payload shape, model selection, and compatibility decisions. Provider adapters map a resolved structure to ComfyUI, Veo, Nova Reel, or another supported execution topology.

StoryOS responsibilities are limited to:

- Preserving story context and editorial intent
- Resolving entities, references, templates, and user choices
- Sending a normalized intent to the Orchestrator
- Asking provider capabilities whether that intent is supported
- Tracking jobs, artifacts, lineage, and failures

The WordPress dynamic UI uses explicit `scf_field` mappings when a structure field should be guided or prefilled from Story Graph content. Stored SCF/post-meta values provide initial editorial input; users can review and override them before submission. The UI does not convert SCF fields into provider-specific requirements.

Providers remain responsible for:

- Defining valid generation parameters and defaults
- Translating intent into provider API requests or ComfyUI workflows
- Enforcing model and account limitations
- Reporting provider-specific failures and output contracts

Agents remain available to help users resolve prompt, workflow, capability, and troubleshooting questions. They must not become an alternate provider implementation or silently invent provider requirements.

### Tasks
- [x] Create Generation Structure schema
- [x] Create Structure Registry
- [x] Create Structure Validator
- [x] Create dynamic UI generation

### Structures
- [ ] Text to Image
- [ ] Image to Image
- [ ] Text to Video
- [ ] Image to Video
- [ ] Video to Video
- [ ] Image Start Frame
- [ ] Image End Frame
- [ ] Multi Image Sequence
- [ ] Storyboard Generation
- [ ] Asset Variation Generation
- [ ] Audio Generation
- [ ] 3D Generation

## Epic 4: Template Registry

### Goal
Support reusable generation templates.

### Tasks
- [x] Create Template CPT
- [x] Create Template JSON schema
- [x] Create Template Registry
- [x] Create Template UI
- [x] Create Template Versioning
- [x] Create Template Categories

### Template Categories Strategy

Template categories are registered as a native WordPress taxonomy on the `storyos_template` CPT, so users can create, edit, and maintain them directly in the WordPress admin without custom code for category management.

### Template Versioning Strategy

Template versioning can be implemented through the SCF layer by treating each template revision as a timestamped, revision-aware content record. This preserves the existing WordPress pattern of version history, auditability, and publication timestamps without inventing a separate parallel versioning system.

Recommended model:

- `template_id` remains the canonical logical identity.
- `version` remains the semver-like release id (`major.minor`).
- SCF/post revision metadata provides `created_at`, `updated_at`, `published_at`, and revision history.
- A template record may be active while older revisions remain accessible for comparison and rollback.
- The registry resolves the latest valid revision for a given `template_id` while retaining historical entries for audit and rollback.

This keeps versioning compatible with the control-plane/editorial model and avoids storing version history outside WordPress metadata.

### Template JSON Schema

Generation templates are defined by a JSON schema at `orchestrator/templates/template.schema.json`
(`$id: https://storyos.dev/schemas/generation-template-1.0.json`). A template is a
provider-neutral editorial configuration that references a registered generation
structure and binds its parameters to SCF fields.

- **Identity** — `template_id` (slug) + `version` (`major.minor`) form the unique
  identity, mirroring the structure identity convention.
- **Structure reference** — `structure` points at a registered structure
  (`structure_id` + optional `version` pin). The registry resolves the full
  structure at load time and merges its parameter defaults into the template.
- **Configuration** — `parameters` (must be a subset of the structure's declared
  parameters), `references` (role/source bindings validated against the
  structure's declared reference roles), `scf_fields` (parameter → SCF field
  mapping), and an optional `workflow` block for provider-specific overrides.
- **Provider neutrality** — templates never encode provider-specific limits
  (max steps, resolution caps, etc.); those remain owned by the provider
  capability descriptor.

The schema is loaded and enforced by `orchestrator/templates/registry.py`
(`TemplateRegistry`) and `orchestrator/templates/validator.py`
(`validate_template` / `validate_catalog`), with example templates in
`orchestrator/templates/examples.json` and coverage in
`orchestrator/tests/test_template_registry.py`.

## Epic 5: SCF + JSON Configuration Engine

### Goal
Compile editor-authored configuration into an immutable, validated Generation Request before a provider receives work.

Epic 4 defines reusable template records and their editorial metadata. Epic 5 is a Celery-aligned pre-submit phase that resolves those definitions with live SCF values, the selected Provider Connection, and explicit user overrides.

### Pre-Submit Contract

The preparation stage receives a generation intent containing the target Story Graph entity, template identity and revision, Provider Connection ID, and user overrides. It produces either:

- A resolved Generation Request snapshot that is valid to submit to a provider, or
- A structured validation result explaining why submission is blocked.

The snapshot is immutable for the lifetime of its Generation Job. Later edits to SCF values, templates, or connection configuration must affect only newly prepared jobs.

### Discrete Runtime Steps

1. **Receive intent** - Accept the target entity, template revision, Provider Connection, and user overrides; create the durable Generation Job.
2. **Load configuration** - Resolve the template JSON, referenced Generation Structure, SCF/post-meta values, Story Graph context, and synchronized Provider Connection record.
3. **Map SCF fields** - Apply the template's explicit `scf_fields` mappings to turn editorial values into structure parameters and references.
4. **Merge configuration** - Combine structure defaults, template parameters, mapped SCF values, connection policy, and user overrides using the documented precedence order.
5. **Normalize request** - Build one provider-neutral Generation Request with resolved prompt data, media references, output requirements, and provenance metadata.
6. **Validate compatibility** - Check the normalized request against the selected provider capability descriptor, connection availability, enabled model, geography, and account constraints.
7. **Persist the snapshot** - Store the resolved request, template/structure/capability versions, validation result, and non-secret provenance under the Generation Job.
8. **Dispatch or block** - Enqueue provider submission only when validation succeeds; otherwise mark the job as blocked with actionable validation errors.

### Configuration Precedence

From lowest to highest priority:

1. Generation Structure defaults
2. Template JSON parameters
3. Mapped SCF and Story Graph values
4. Provider Connection policy and enabled-model constraints
5. Explicit user overrides

Provider capability descriptors are validation constraints, not a configuration source. They may reject a resolved value but must not silently rewrite editorial intent.

### Celery Alignment

The first worker phase is `prepare_generation_request`. It owns steps 2 through 7 and has no provider side effects. A successfully prepared request moves to `submit_provider_job`; a failed preparation never reaches a provider adapter.

The initial implementation may keep preparation and submission inside one top-level Celery task to preserve the existing single task ID. If preparation later needs independent retry, routing, or monitoring, it can become the first task in a Celery chain while the durable StoryOS Generation Job remains the user-facing parent record.

### Tasks
- [ ] Define Generation Intent and resolved Generation Request schemas
- [ ] Create SCF field mapper
- [ ] Create template and structure configuration loader
- [ ] Create configuration merge service and precedence rules
- [ ] Create normalized request builder
- [ ] Create capability and connection validation engine
- [ ] Persist immutable resolved-request snapshots and provenance
- [ ] Create preview endpoint using the same preparation path without dispatch
- [ ] Add pre-submit and validation lifecycle states

This is not a duplicate of template storage. Templates are the authoring layer; this engine is the execution-time resolution and compatibility layer.

## Epic 6: StoryOS Provider CPT

### CPT
`storyos_provider`

### Tasks
- [ ] Create CPT
- [ ] Create SCF fields
- [ ] Create admin UI
- [ ] Create test connection UI
- [ ] Create capability discovery sync

## Epic 7: Generation Job Engine

### CPT
`storyos_generation_job`

### Tasks
- [ ] Create Generation Job CPT
- [ ] Create Job Queue
- [ ] Create Retry Logic
- [ ] Create Background Processing
- [ ] Create Job Monitoring
- [ ] Create Cost Tracking
- [ ] Create Job Analytics

## Epic 8: Asset Pipeline

### Goal
Treat outputs as first-class Story Graph assets.

### Tasks
- [ ] Create Asset Service
- [ ] Create Asset Metadata System
- [ ] Create Attachment Mapping
- [ ] Create Asset Lineage Tracking
- [ ] Create Version Management

## Epic 9: Story Graph Integration

### Tasks
- [ ] Create generation hooks
- [ ] Create template assignment system
- [ ] Create entity context builder
- [ ] Create graph traversal context retrieval

### Supported Entities
- [ ] Character
- [ ] Location
- [ ] Prop
- [ ] Organization
- [ ] Episode
- [ ] Scene
- [ ] Shot
- [ ] Storyboard Frame

## Epic 10: REST API

The StoryOS REST API exposes StoryOS-owned control-plane resources such as jobs, assets, templates, connections, and readiness reports. It is not a re-hosted or compatibility-preserving version of any provider's API. Provider-specific API behavior remains behind the Orchestrator adapter boundary.

### Tasks
- [ ] Providers API
- [ ] Templates API
- [ ] Jobs API
- [ ] Assets API
- [ ] Generate API
- [ ] Story Graph Integration API

Endpoints:
- `/api/storyos/v1/providers`
- `/api/storyos/v1/templates`
- `/api/storyos/v1/jobs`
- `/api/storyos/v1/assets`
- `/api/storyos/v1/generate`

## Epic 11: MAF Integration

### Tasks
- [ ] Create Generation Agent
- [ ] Create Prompt Agent
- [ ] Create Storyboard Agent
- [ ] Create Production Agent
- [ ] Create Editorial Agent
- [ ] Create Agent Tool Registry

## Epic 12: Enterprise Features

### Tasks
- [ ] Multi-user permissions
- [ ] Team workspaces
- [ ] Provider quotas
- [ ] Billing integration
- [ ] Asset governance
- [ ] Audit trails
- [ ] Compliance logging

## Target Architecture

Story Graph → Generation Intent → Pre-Submit Resolution → Capability Validation → Resolved Request Snapshot → Generation Job → Provider Submission → Asset Pipeline → Story Graph Update → Production & Editorial Workflows

## Definition of Done (MVP)

- [ ] Provider framework implemented
- [ ] ComfyUI migrated to provider model
- [ ] Template registry operational
- [ ] SCF + JSON merge engine operational
- [ ] Generation job system operational
- [ ] Story Graph integration operational
- [ ] REST API operational
- [ ] Extensible provider onboarding documented
- [ ] Text-to-Image support
- [ ] Image-to-Image support
- [ ] Text-to-Video support
- [ ] Image-to-Video support
- [ ] ComfyUI provider support
- [ ] Veo provider support
- [ ] Runway provider support

## Strategic Goal

Transform a ComfyUI-specific plugin into a StoryOS generation orchestration platform capable of integrating any current or future AI generation provider.


---

# Architecture Correction: Provider Types vs Provider Connections

## Decision

Provider Types and Provider Connections are separate concepts and must not be modeled as the same entity.

### Provider Type (Code)

Provider Types are executable implementations shipped with the plugin.

Examples:

- ComfyUiProvider
- VeoProvider
- RunwayProvider
- KlingProvider
- MiniMaxProvider
- LumaProvider
- SeedanceProvider
- WanProvider
- HunyuanProvider
- NovaReelProvider

Responsibilities:

- Authentication workflows
- Request validation
- Payload generation
- Job submission
- Status polling
- Result retrieval
- Output normalization

### Provider Connection (Configuration)

Provider Connections are configured instances of a Provider Type.

Examples:

- Local ComfyUI
- Development ComfyUI
- Production ComfyUI
- Studio Runway Account
- Research Veo Project

Responsibilities:

- Endpoint configuration
- Credentials
- Environment selection
- Quotas
- Rate limits
- Enabled models
- Enabled capabilities

## Backlog Change

Replace Epic 6: StoryOS Provider CPT with:

### Epic 6: Provider Connection Architecture

#### CPT

`storyos_connection`

#### Tasks

- [ ] Create storyos_connection CPT
- [ ] Create connection repository
- [ ] Create connection management UI
- [ ] Create capability synchronization
- [ ] Create connection testing tools
- [ ] Create environment management
- [ ] Create quota management

#### Fields

- Connection Name
- Provider Type
- Environment
- Status
- Endpoint URL
- API Key / OAuth
- Model Access
- Enabled Structures
- Rate Limits
- Cost Controls

## New Epic 2A: Provider Type Framework

### Tasks

- [x] Create ProviderTypeInterface
- [x] Create Provider Registry
- [x] Create Provider Discovery System
- [x] Create Capability Framework
- [x] Create Provider Lifecycle Hooks
- [ ] Register provider implementations at runtime

## Generation Jobs

Generation jobs must reference both:

- Provider Type
- Provider Connection

Example:

```json
{
  "provider_type": "comfyui",
  "connection_id": 32
}
```

## Connection Strategies

Future enterprise features:

- [ ] Manual Selection
- [ ] Least Busy
- [ ] Lowest Cost
- [ ] Round Robin
- [ ] Weighted Distribution
- [ ] Failover Routing
- [ ] Geographic Routing

## Revised Architecture

```text
Provider Type (Code)
        ↓
Provider Connection (Configuration)
        ↓
Generation Template
        ↓
Generation Structure
        ↓
Generation Job
        ↓
Provider API
        ↓
Asset Pipeline
        ↓
Story Graph
```


---

# Architecture Correction: Capability Registry & Validation Engine

## Decision

Capability validation must operate against a resolved generation request and machine-readable provider capability descriptors.

High-level labels such as `text_to_video` or `image_to_video` are not sufficient for compatibility checks.

## Capability Descriptor Requirements

Every Provider Type must publish a versioned capability descriptor.

Example metadata:

- provider_type
- provider_version
- capability_schema_version

## Capability Categories

### Input Constraints

- Supported MIME types
- Maximum image count
- Maximum video count
- Maximum audio inputs
- Maximum file size

### Output Constraints

- Supported output MIME types

### Dimension Constraints

- Minimum width
- Maximum width
- Minimum height
- Maximum height

### Aspect Ratios

- Supported aspect ratios

### Video Constraints

- Minimum duration
- Maximum duration
- Supported frame rates

### Prompt Constraints

- Maximum prompt length
- Negative prompt support
- Seed support

### Reference Support

- Character references
- Style references
- Image references
- Video references
- Start-frame support
- End-frame support

### Audio Capabilities

- Audio input support
- Audio output support

### Geographic Availability

- Region restrictions
- Account restrictions

### Commercial Metadata

- Estimated cost range
- Estimated latency range

## Validation Flow

Incorrect:

UI Fields -> Capability Validation

Correct:

Template + SCF + Provider Connection + User Overrides
        ↓
Resolved Generation Request
        ↓
Capability Validator
        ↓
Compatibility Result

## Resolved Generation Request

A normalized request object should include:

- Input media counts
- Input media types
- Output type
- Dimensions
- Aspect ratio
- Duration
- Frame rate
- Prompt metadata
- Seed usage
- Negative prompt usage
- Reference assets
- Audio requirements

## New Epic: Capability Registry & Validation Engine

### Tasks

- [x] Create Capability Descriptor Schema
- [x] Create Capability Schema Versioning
- [x] Create Capability Registry
- [x] Create Capability Discovery Service
- [ ] Create Capability Validation Engine
- [ ] Create Compatibility Report Generator
- [ ] Create Cost Estimation Engine
- [ ] Create Latency Estimation Engine
- [ ] Create Geographic Availability Validation
- [ ] Create Model Feature Comparison Service

### Deliverables

- CapabilityDescriptor
- CapabilityRegistry
- CapabilityValidator
- CompatibilityReport
- CostEstimator
- LatencyEstimator

## Architecture Update

Provider Type
      ↓
Capability Descriptor
      ↓
Provider Connection
      ↓
Generation Template
      ↓
Resolved Generation Request
      ↓
Capability Validation
      ↓
Generation Job
      ↓
Provider API
      ↓
Asset Pipeline
      ↓
Story Graph


---

# Architecture Correction: Orchestrator Queue & Artifact Management

## Decision

All generation activity must be executed through the StoryOS Orchestrator Queue.

Providers must not be called directly from:

- UI actions
- REST requests
- Agent actions
- CPT actions

Every generation request becomes an orchestrated job.

## Async-First Architecture

Most modern providers are asynchronous:

- Google Veo
- Runway
- Kling
- Luma
- Seedance
- Nova Reel
- Hunyuan

The architecture must assume:

Submit → Queue → Poll/Webhook → Download → Process

## New Epic: Orchestrator Queue Integration

### Tasks

- [ ] Create QueueManager
- [ ] Create JobDispatcher
- [ ] Create WorkerPool
- [ ] Create RetryManager
- [ ] Create DeadLetterQueue
- [ ] Create Job Priority System
- [ ] Create Job Cancellation Support
- [ ] Create Job Resumption Support
- [ ] Create Queue Monitoring Dashboard
- [ ] Create Queue Metrics API

## Generation Job Lifecycle

- [ ] Pending
- [ ] Queued
- [ ] Preparing Request
- [ ] Validation Blocked
- [ ] Validated
- [ ] Dispatched
- [ ] Provider Submitted
- [ ] Remote Running
- [ ] Downloading Artifacts
- [ ] Processing Artifacts
- [ ] Asset Indexing
- [ ] Completed
- [ ] Failed
- [ ] Retry Pending
- [ ] Cancelled
- [ ] Timed Out

## Provider Contract Update

Provider types must expose:

- submit()
- poll()
- cancel()
- downloadArtifacts()

Provider implementations should return remote job references instead of assets.

## New Epic: Artifact Retrieval Service

### Tasks

- [ ] Create ArtifactDownloader
- [ ] Create Download Manager
- [ ] Create MIME Validation
- [ ] Create File Size Validation
- [ ] Create SHA256 Hashing
- [ ] Create Retry Downloads
- [ ] Create Partial Download Recovery
- [ ] Create Storage Abstraction Layer
- [ ] Create Artifact Collections

## Multi-Artifact Support

Generation jobs may return:

- Video files
- Images
- Thumbnails
- Metadata JSON
- Captions
- Audio tracks
- Alternative renders

## Asset Ingestion Pipeline

Remote Artifact
↓
Temporary Storage
↓
Validation
↓
Media Library
↓
Asset Record
↓
Story Graph Linkage

## MAF Integration Rule

Agents must never communicate directly with provider APIs.

Agents create generation requests through the Orchestrator Queue and monitor status through orchestration APIs.

## Updated Reference Architecture

Story Graph
↓
Generation Request
↓
Capability Validation
↓
Generation Job
↓
Orchestrator Queue
↓
Worker
↓
Provider Type
↓
Provider Connection
↓
Remote Generation Platform
↓
Artifact Retrieval
↓
Asset Pipeline
↓
Media Library
↓
Story Graph Update


---

# Architecture Correction: Secret Management & Credential Security

## Decision

Provider Connections must never store raw credentials.

Provider Connections store credential references only.

WordPress content entities are not secret stores.

## Security Architecture

Provider Type (Code)
↓
Provider Connection (Configuration)
↓
Credential Reference
↓
Secret Resolver
↓
Secret Backend
↓
Provider API

## Provider Connection Update

Replace direct credential storage with:

- credential_reference
- secret_backend
- environment
- endpoint

Example:

`secret://runway/production`

or

`env://RUNWAY_API_KEY`

## Secret Resolver Framework

### Tasks

- [ ] Create SecretResolver service
- [ ] Create SecretReference schema
- [ ] Create SecretBackend interface
- [ ] Create permission-aware secret resolution
- [ ] Create runtime secret caching

## Supported Secret Backends

### Environment Variables

- [ ] env:// support

### Encrypted Local Storage

- [ ] wpsecret:// support
- [ ] encryption versioning

### Managed Secret Services

- [ ] Azure Key Vault
- [ ] AWS Secrets Manager
- [ ] Google Secret Manager

## Encryption at Rest

### Requirements

- [ ] Encrypt locally stored secrets
- [ ] Encryption versioning
- [ ] Encryption key rotation
- [ ] Secret integrity validation

## Key Management

### Requirements

Keys must exist outside WordPress content storage.

Supported sources:

- Environment variables
- Azure Key Vault / KMS
- AWS KMS
- Google KMS

Never stored in:

- CPT fields
- post_meta
- wp_options
- REST responses

## Credential Display Rules

### Admin UI

- [ ] Mask all secrets
- [ ] Show status only
- [ ] Show validation date
- [ ] Show backend type

Secrets are never displayed after initial entry.

## Permission Model

Create separate capabilities:

- [ ] storyos_use_provider_connection
- [ ] storyos_manage_provider_connection
- [ ] storyos_manage_credentials
- [ ] storyos_view_secret_metadata

Users may use connections without viewing secrets.

## Credential Rotation

### Tasks

- [ ] Manual rotation
- [ ] Scheduled rotation
- [ ] Emergency rotation
- [ ] Rotation audit log
- [ ] Credential version tracking

## Logging & Error Protection

### Tasks

- [ ] Log redaction service
- [ ] Secret detection filters
- [ ] Error sanitization layer
- [ ] Queue log sanitization
- [ ] API trace sanitization

Secrets must never appear in:

- Logs
- Errors
- Queue records
- Monitoring output
- Debug traces

## Multisite Support

### Tasks

- [ ] Site-scoped secrets
- [ ] Network-scoped secrets
- [ ] Secret inheritance rules
- [ ] Network override support

## Export & Backup Rules

Exports must contain only references.

Allowed:

- credential_reference

Disallowed:

- API keys
- OAuth tokens
- Client secrets

## New Epic: Secret Management Framework

### Tasks

- [ ] Create SecretResolver
- [ ] Create SecretReference schema
- [ ] Create Encryption Service
- [ ] Create Environment Variable Backend
- [ ] Create Encrypted Storage Backend
- [ ] Create Azure Key Vault Backend
- [ ] Create AWS Secrets Manager Backend
- [ ] Create Google Secret Manager Backend
- [ ] Create Credential Rotation System
- [ ] Create Audit Trail
- [ ] Create Secret Access Logging
- [ ] Create Export Protection Rules


---

# Architecture Decision: WordPress As Orchestration Layer

## Definition of StoryOS Generation Engine

StoryOS Generation Engine is a WordPress-based orchestration and editorial platform.

WordPress owns:

- Editorial configuration
- Story Graph relationships
- Permissions
- Templates
- Asset metadata
- Workflow definitions
- Audit trails

Provider adapters remain code-based.

Executions are durable asynchronous workflows.

Operational runtime state should not be modeled primarily through CPT metadata.

## Control Plane vs Execution Plane

### Control Plane (WordPress)

Owns:

- Story Graph
- Templates
- Provider Connections
- Permissions
- Editorial configuration
- Asset relationships

### Execution Plane (Orchestrator)

Owns:

- Queues
- Workers
- Runtime state
- Provider execution
- Retries
- Webhooks
- Artifact transfers

## Backlog Rewrite Standard

Future tasks must define observable behaviors and acceptance criteria rather than component creation alone.

Format:

Given ...
When ...
Then ...

---

# Acceptance Criteria: Provider Type Framework

### Provider Registration

Given a valid Provider Type implementation
When StoryOS initializes
Then the provider is automatically registered and available for discovery.

### Capability Discovery

Given registered providers
When StoryOS requests capability information
Then versioned capability descriptors are returned.

### Provider Upgrade Compatibility

Given an upgraded provider adapter
When StoryOS reloads provider registrations
Then existing Provider Connections remain usable.

---

# Acceptance Criteria: Provider Connection Architecture

### Connection Validation

Given a new Provider Connection
When an administrator saves the connection
Then endpoint and credential validation complete before activation.

### Connection Isolation

Given multiple connections using the same Provider Type
When one connection fails
Then remaining connections continue operating.

---

# Acceptance Criteria: Template Resolution

### Normalized Request Generation

Given SCF values, template JSON, provider configuration, and user overrides
When a request is prepared
Then StoryOS produces a single normalized Generation Request.

### Provider Portability

Given a template targeting one provider
When another compatible provider exists
Then StoryOS can evaluate compatibility without changing editorial content.

---

# Acceptance Criteria: Capability Validation

### Feature Compatibility

Given a request specifying aspect ratio, frame rate, duration, references, and output format
When capability validation executes
Then only compatible providers are returned.

### Geographic Restrictions

Given a provider restricted by region or account
When validation executes
Then unsupported executions are blocked before submission.

---

# Acceptance Criteria: Orchestrator Queue

### Durable Job Creation

Given a validated generation request
When execution begins
Then a durable Generation Job is created and queued.

### Worker Recovery

Given a worker failure during execution
When a lease expires
Then another worker can safely resume processing.

### Runtime Recovery

Given a system restart
When services return online
Then queued and running jobs can be recovered.

### Remote Job Tracking

Given an asynchronous provider
When a request is submitted
Then the provider job identifier is stored and tracked.

---

# Acceptance Criteria: Artifact Retrieval

### Download Recovery

Given a failed artifact transfer
When retry logic executes
Then downloads resume or restart without orphaning the Generation Job.

### Artifact Validation

Given a downloaded artifact
When ingestion starts
Then MIME type, size, hash, and integrity checks succeed.

### Multi-Artifact Collections

Given multiple artifacts returned by a provider
When ingestion completes
Then all artifacts are linked to a single Asset Collection.

---

# Acceptance Criteria: Secret Management

### Secret Isolation

Given a Provider Connection
When that connection is returned by a REST API
Then secret values never appear in responses.

### Credential Rotation

Given rotated credentials
When new Generation Jobs execute
Then the updated credentials are used without changing templates.

### Export Protection

Given a platform export
When export files are generated
Then credential references are included and secret materials are excluded.


---

# Architecture Decision: WordPress Control Plane, Orchestrator Execution Plane

## Final Direction

WordPress is the authoritative control plane.

The Orchestrator is the authoritative execution plane.

Provider implementations SHALL exist only in the Orchestrator.

This decision eliminates duplicated provider logic across PHP and Python codebases.

## WordPress Responsibilities

### Provider Connection Administration

Acceptance Criteria:

Given an administrator with appropriate permissions
When a Provider Connection is created or updated
Then the administrator can manage endpoint configuration, credential references, quotas, enabled models, and environment settings from WordPress.

### Template Management

Given editorial users
When templates are created or modified
Then changes are stored without requiring Orchestrator code deployment.

### Story Graph Integration

Given Story Graph entities
When users generate assets
Then entity relationships remain managed entirely within WordPress.

## Orchestrator Responsibilities

### Provider Framework Ownership

Acceptance Criteria:

Given a newly implemented provider adapter
When the Orchestrator starts
Then the provider becomes available without provider-specific PHP code being added to WordPress.

### Runtime Execution Ownership

Given a Generation Job
When execution begins
Then all provider interaction, polling, retries, artifact retrieval, and state transitions occur within the Orchestrator.

## Provider Connection Synchronization

### Synchronization Behavior

Acceptance Criteria:

Given a Provider Connection saved in WordPress
When synchronization completes
Then the Orchestrator receives a normalized connection record.

Given a synchronized connection
When a Generation Job starts
Then execution does not require a runtime query back to WordPress.

### Connection Payload Requirements

The synchronization payload shall contain:

- Connection ID
- Provider Type
- Endpoint Configuration
- Credential Reference
- Environment
- Enabled Models
- Enabled Capabilities
- Quotas
- Operational Policies

The synchronization payload shall not contain:

- API Keys
- OAuth Tokens
- Client Secrets
- Decrypted Credentials

## Secret Resolution Requirements

Acceptance Criteria:

Given a synchronized Provider Connection
When a job executes
Then credentials are resolved by the Orchestrator Secret Resolver.

Given a worker process
When provider execution occurs
Then secrets are obtained through approved Secret Backends only.

Given audit logs, queue records, monitoring events, or REST responses
When operational data is recorded
Then credential values never appear.

## High-Priority Deliverables

### Deliverable: Connection Sync Service

Done When:

- [ ] Connections synchronize from WordPress to Orchestrator
- [ ] Updates propagate successfully
- [ ] Deletions are handled cleanly
- [ ] Version conflicts are detectable
- [ ] Synchronization events are auditable

### Deliverable: Provider Registration Framework

Done When:

- [ ] Provider adapters register only within the Orchestrator
- [ ] Capability descriptors are discoverable
- [ ] Provider upgrades do not require WordPress code changes

### Deliverable: Durable Queue Execution

Done When:

- [ ] Jobs survive service restarts
- [ ] Jobs survive WordPress outages
- [ ] Workers can recover in-progress jobs
- [ ] Provider submissions are not duplicated during recovery

### Deliverable: Credential Reference Architecture

Done When:

- [ ] Provider Connections contain credential references only
- [ ] Secret resolution occurs only in the Orchestrator
- [ ] Secrets never appear in exports
- [ ] Secrets never appear in REST responses

## Non-Functional Acceptance Criteria

### WordPress Availability Independence

Given an active Generation Job
When WordPress becomes unavailable
Then execution, polling, downloads, and asset ingestion continue.

### Provider Independence

Given a newly supported provider
When the adapter is deployed
Then no provider-specific changes are required inside WordPress.

### Horizontal Scalability

Given multiple workers
When queue load increases
Then additional workers may process jobs without modifying WordPress.

## Architectural Guardrail

Reject any feature proposal that requires:

- Provider-specific execution logic in WordPress
- Secret storage in CPT metadata
- Runtime execution state as primary CPT storage
- Duplicated provider implementations in PHP and Python

The preferred architecture is:

WordPress (Control Plane)
        ↓
Connection Sync
        ↓
Orchestrator (Execution Plane)
        ↓
Provider Framework
        ↓
External Generation Platforms
