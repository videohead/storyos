# StoryOS Roadmap

> Build Your Story Once. Create Everywhere.

This roadmap describes StoryOS as a WordPress storytelling operating system.
WordPress owns the Story Graph, editorial workflows, AI abilities, generation
records, and assets. ComfyUI workflows are accessed through MCP.

**Roadmap status:** Phases 1-4, 7, and 8 are complete. Phases 5, 6, and 9 are
on hold as of 2026-08-17.

## Guiding Vision

StoryOS provides one place to manage:

- Story development and structured world building.
- Script and screenplay data.
- AI-assisted writing and editorial analysis.
- ComfyUI media workflows.
- Storyboarding and production planning.
- Editorial and NLE exchange.
- Story Graph intelligence.

The Story Graph is the canonical source of truth in every phase. External tools
and MCP clients read approved context or exchange artifacts through explicit
WordPress integrations.

## Current Architecture

```text
Creators and MCP Clients
          |
          v
WordPress + StoryOS Plugin
  CPTs, SCF, REST, Abilities, AI Editor
          |
          v
Story Graph and WordPress Media Library
          |
   +------+------+
   |             |
   v             v
AI abilities   ComfyUI MCP
and LLMs       Cloud or local client
   |             |
   +------+------+
          v
Production, scripts, editorial, and asset workflows
```

The standard deployment is WordPress, MariaDB, and the StoryOS plugin. An API
connected LLM and ComfyUI are optional connections, not required for core story
management.

## Phase 1: Story Core - Complete

### Objective

Establish the canonical Story Graph and WordPress content model.

### Delivered

- Projects and story worlds.
- Characters, locations, props, scenes, shots, and assets.
- Storyboard frames and editorial artifacts.
- WordPress custom post type architecture.
- Structured Content Fields and taxonomies.
- Relationship storage and graph traversal.
- WordPress REST API foundations.
- Schema and metadata alignment for interoperability.

### Source of Truth

`wordpress/wp-content/plugins/storyos/`

## Phase 2: Generation Core - Complete

### Objective

Connect Story Graph context to reusable generative media workflows.

### Delivered

- Comfy Cloud MCP connection.
- Optional local ComfyUI workflow through an MCP-capable client.
- WordPress generation records.
- Prompt and template configuration.
- Generation provenance and asset linkage.
- Character, environment, concept-art, lookbook, and storyboard use cases.
- ComfyUI connection settings and capability information.

### Current Boundary

WordPress prepares and records generation requests. ComfyUI owns workflow
execution. Generated media returns to the WordPress media and Story Graph asset
pipeline.

See [GENERATION_ENGINE.md](plugins/GENERATION_ENGINE.md) and
[Deployment and Connections](Deployment_and_Connections.md).

## Phase 3: Filmmaking Abilities - Complete

### Objective

Make StoryOS expertise available as typed, permission-aware WordPress
Abilities for the editor and MCP-compatible clients.

### Delivered

- Plugin-owned filmmaking ability definitions.
- Story Graph context retrieval.
- Story, prompt, production, editorial, and technical assistance profiles.
- WordPress Abilities API registration.
- Permission callbacks and structured input/output schemas.
- MCP metadata for tools, resources, and prompts.

### Current Boundary

Abilities execute through WordPress services and configured LLM connections.
They are not a separate application or execution runtime. Ability callbacks
must reuse the same context, continuity, search, and generation services used by
the WordPress UI and REST API.

## Phase 4: Storyboarding and Production - Complete

### Objective

Support production planning through StoryOS data and WordPress extensions.

### Delivered

- Storyboard frames and shot relationships.
- Asset-to-scene and asset-to-shot mapping.
- Production metadata stored with Story Graph entities.
- WordPress plugin integration points for scheduling and production tools.
- Media library access for generated and uploaded assets.

### Extension Approach

Production scheduling, call sheets, and specialized planning interfaces may be
provided by WordPress plugins. They should query Story Graph data through
WordPress APIs and preserve StoryOS relationships as the canonical record.

## Phase 5: Script Ecosystem - On Hold

### Objective

Support screenplay import, export, and synchronization with writing tools.

### Delivered

Celtx synchronization is operational through the `storyos-celtx` plugin:

- Projects, characters, locations, scenes, and shots.
- Bidirectional ID mapping in post metadata.
- Celtx GEM API synchronization.
- WordPress settings and authentication support.
- Sync REST endpoints under `storyos-celtx/v1`.

### Deferred

- Fountain import and export.
- Final Draft FDX import.
- Fade In and Highland import.
- Story Architect and Markdown import.
- Screenplay and shooting-script export.
- Script parsing and Story Graph entity extraction.
- Duplicate detection and import preview.
- Additional synchronization providers.

Further script work remains deferred while the current Celtx integration is
maintained.

## Phase 6: Editorial Ecosystem - On Hold

### Objective

Exchange Story Graph production data with editorial and NLE workflows.

### Delivered

- EDL export.
- CMX 3600 ASCII and SMPTE 436m XML support.
- Drop-frame timecode support for common NTSC rates.
- Frame handles.
- Multi-track video and audio support.
- Clip naming suitable for common NLE workflows.
- Compatibility testing for Premiere Pro, DaVinci Resolve, Avid, Final Cut Pro,
  and Unreal Engine workflows.

### Deferred

- Timeline metadata surface.
- Formal scene and shot mapping export contract.
- Asset reference exchange.
- AAF and OMF export.
- NLE-specific panels and plugins.
- Direct media linking with deployment-specific paths.

EDL functionality remains available while the broader editorial roadmap is on
hold.

## Phase 7: Story Graph Intelligence - Complete

### Objective

Make Story Graph data useful by meaning, consistency, and relationship.

### Delivered

- WordPress search enhancement with StoryOS entity filters.
- Keyword and optional semantic search modes with fallback behavior.
- Continuity checks on relevant entity saves and manual actions.
- Severity-based continuity issue storage.
- Relationship traversal and graph summaries.
- Character co-occurrence and entity connectivity analytics.
- Isolated entity detection.
- WordPress admin panels for continuity and analytics.
- WordPress REST and Abilities integration points.

### Current Boundary

Search, continuity rules, and relationship calculations run against WordPress
entities, SCF values, post metadata, and canonical relationships. Results are
stored and displayed through WordPress. Optional LLM assistance may explain or
help explore findings, but deterministic WordPress data remains authoritative.

See [Phase_7_Story_Graph_Intelligence.md](Phase_7_Story_Graph_Intelligence.md).

### Future Improvements

- Better cache invalidation for large Story Graphs.
- Incremental search indexing.
- Search ranking and relevance benchmarks.
- Interactive graph visualization.
- Larger-scale performance testing.
- Optional narrative reasoning abilities.

## Phase 8: AI Editor - Complete

### Objective

Bring Story Graph-aware AI assistance into the WordPress content editor.

### Delivered

- Gutenberg AI Editor sidebar.
- Story Graph context builder.
- Local and hosted LLM connection settings.
- Chat, analysis, generation, and continuity REST routes.
- WordPress Abilities API integration.
- Four tool abilities:
  - `storyos/chat`
  - `storyos/analyze`
  - `storyos/generate`
  - `storyos/continuity-check`
- Three context resources:
  - `storyos/post-context`
  - `storyos/character-context`
  - `storyos/scene-context`
- Two prompt abilities:
  - `storyos/story-review-prompt`
  - `storyos/continuity-prompt`
- Permission-aware schemas and MCP metadata.
- AI settings for OpenAI, Claude, and OpenAI-compatible endpoints.

### Next Work

- Complete live MCP Adapter discovery tests.
- Complete browser and accessibility coverage.
- Add durable audit records for accepted AI edits.
- Improve context and response caching.
- Add explicit editor insertion actions.
- Label and preserve AI-generated content provenance.
- Continue security and input/output audits.

See [Phase_8_AI_Editor.md](Phase_8_AI_Editor.md).

## Phase 9: Community Platform - On Hold

### Objective

Build an ecosystem around StoryOS extensions and reusable creative resources.

### Deferred

- Plugin marketplace.
- Workflow and template marketplace.
- Ability and integration directory.
- Educational resources.
- Contributor programs.
- Community governance and review process.

The community phase should begin after the WordPress integration contracts,
security model, and extension documentation are stable.

## Cross-Phase Priorities

### WordPress Integrity

- Keep the Story Graph canonical.
- Use registered CPT, SCF, taxonomy, and relationship definitions.
- Preserve permissions and auditability across every integration.
- Keep generated and imported assets linked to their source context.

### MCP and External Connections

- Treat Comfy Cloud MCP as the supported server-side media connection.
- Treat local ComfyUI MCP as an optional creator workflow.
- Use WordPress Abilities as the public AI and MCP capability contract.
- Never place secrets in source control, JavaScript, prompts, or context data.

### Quality

- Prefer narrow, deterministic WordPress services.
- Validate input and escape output.
- Provide keyword or manual fallbacks when optional AI services are unavailable.
- Test permissions, empty states, failures, and large Story Graphs.
- Update the relevant specification when a public REST, Ability, or MCP
  contract changes.

## Success Metrics

### Product

- Projects and Story Graphs created.
- Story Graph entities managed.
- Assets generated, imported, and linked with provenance.
- Continuity issues detected and resolved.
- AI Editor sessions that produce accepted editorial changes.

### Reliability

- Successful Comfy Cloud MCP workflow completion.
- Successful asset registration in WordPress.
- Search fallback coverage when optional semantic services are unavailable.
- Continuity check accuracy reviewed by creators.
- Ability and REST permission violations prevented.

### Ecosystem

- WordPress integrations using documented Story Graph contracts.
- MCP clients using StoryOS abilities.
- Community plugins and templates.
- Contributors and maintained extensions.

## Long-Term Goal

StoryOS is not another isolated media-generation service. It is an open
storytelling operating system where structured stories, AI assistance,
generative workflows, production planning, and editorial assets meet in one
WordPress-owned Story Graph.

**The future of storytelling is structured.**

**The future of storytelling is open.**