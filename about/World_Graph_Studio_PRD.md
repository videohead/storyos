# World Graph Studio Product Requirements

> Your ideas. Your assets. No credits needed.

**Release status: complete.** This document defines the product delivered in
the current repository. [Delivery Status](Delivery_Status.md) is the source of
truth for implementation status.

## Product definition

World Graph Studio is a free, open-source, self-hosted creative production
platform for worldbuilding, storytelling, AI-assisted analysis, asset
generation, and production planning. It is built on WordPress, and its
canonical data model is the Story Graph.

The product keeps the durable parts of a creative project—ideas, entities,
relationships, source material, generated media, and production decisions—in
one connected system. Optional AI and generation providers can use that
context, but no provider becomes the owner or source of truth for the project.

## Product promise

World Graph Studio must let a creator:

- Build a connected world instead of maintaining disconnected documents and
  prompts.
- Move from story development through production planning without rebuilding
  the project model in each tool.
- Use local, open, or hosted AI services through explicit connections.
- Keep working on the Story Graph when every optional AI service is offline.
- Retain imported and generated assets in WordPress with their project context
  and provenance.
- Exchange current project data through delivered, documented formats without
  depending on a World Graph Studio cloud or credit balance.

## Intended users

- Filmmakers developing scripts, coverage, storyboards, shots, and editorial
  handoffs.
- Game creators designing worlds, characters, locations, props, and narrative
  relationships.
- Scriptwriters who want structured story context and AI-assisted review.
- Video producers organizing creative assets, sequences, sounds, and
  production metadata.
- Technical creators who want a self-hosted, extensible alternative to a
  single-provider creative platform.

## Product principles

### The Story Graph is canonical

Projects, worlds, characters, locations, props, organizations, episodes,
scenes, shots, sounds, storyboards, assets, editorial records, templates, and
connections are structured WordPress records. Relationships and Structured
Content Fields provide shared context across editing, intelligence,
generation, and interchange workflows.

### WordPress is the application and control plane

Authentication, permissions, content storage, REST APIs, background work,
administration, and media ownership remain in WordPress. World Graph Studio
does not require a separate orchestration application for its core features.

### AI is optional and human-directed

AI advisors may analyze, suggest, draft, and prepare generation requests. A
creator decides what is saved or published. Story Graph management,
continuity data, and asset organization remain available without an LLM or
generation provider.

### Connections are replaceable

Provider credentials, endpoints, capabilities, and templates are
configuration. The Story Graph and WordPress Media Library remain stable when
a creator changes providers. Hosted services may impose their own prices,
quotas, moderation, licenses, and availability; World Graph Studio does not
sell usage credits.

### Self-hosting means operator control

The operator chooses where WordPress runs and which services it can reach.
Privacy, access, backups, and publication still depend on WordPress and hosting
configuration; self-hosting alone does not make a site private.

## Delivered product requirements

### Connected creative workspace

The current release provides:

- Fifteen Story Graph content types and nine taxonomies.
- Structured Content Fields with portable local JSON definitions.
- Canonical relationships, relationship traversal, and REST exposure.
- Project and world management for characters, locations, props,
  organizations, episodes, scenes, shots, sounds, storyboards, assets,
  editorial records, templates, and provider connections.
- Production, editorial, asset, and administration panels in WordPress.

Detailed fields and relationships are defined in the
[Content Model Specification](Content_Model_Specification.md) and
[CPT and SCF Schema](CPT_and_SCF_Schema.md).

### Story intelligence

The current release provides keyword and optional semantic assistance,
continuity checks, relationship analytics, Story Graph summaries, and
permission-aware admin and API surfaces. Deterministic WordPress data remains
authoritative when an optional model contributes an explanation or analysis.

### AI-assisted editing

The current release provides a Gutenberg AI Editor with bounded Story Graph
context, configured local or hosted LLM access, chat and analysis actions,
generation assistance, continuity actions, health and settings endpoints,
WordPress Abilities, and more than 50 specialist creative advisor profiles.
Suggestions do not silently overwrite canonical content.

See [AI Editor](AI_Editor.md) for its delivered interface and operating
boundaries.

### Generative production

The current release provides:

- Connection and template records.
- Provider-neutral request validation and input binding.
- Queued generation jobs processed through WP-Cron.
- Job state, cancellation, error reporting, and generation logs.
- Returned-media import into WordPress with source linkage and provenance.
- Adapters for Comfy Cloud MCP, local ComfyUI HTTP workflows, fal MCP,
  ElevenLabs, and manually managed external-generator workflows where
  configured.

Capabilities depend on the selected adapter, template, model, credentials, and
reachable service. The product may store media types for which the current
installation has no direct generator.

### Project interchange and publishing

The current release provides:

- World Graph Studio JSON import.
- Markdown screenplay and storyboard export.
- Optional outbound Celtx synchronization for supported entities, with
  persistent remote-ID mapping.
- Optional EDL parsing, preview, timecode, and format-generation utilities for
  CMX 3600 and SMPTE 436m XML. Project timeline persistence and live-data
  export are extension boundaries.
- A bundled Google Web Stories connector prototype, retained as extension
  source rather than a supported current workflow.

The JSON, Markdown, Celtx, and EDL utility surfaces are delivered workflows.
Their availability can still depend on an enabled integration, a compatible
external service, and valid credentials. Web Stories synchronization is not
part of the supported current release.

### Administration and API access

The current release provides a setup wizard, connection management, child
plugin controls, dashboards, import/export screens, and permission-aware REST
and admin actions. Public plugin routes use the `worldgraph/v1` REST namespace;
the WordPress text domain and machine namespace are `worldgraph`.

See the [REST API Specification](REST_API_Specification.md) and
[Deployment and Connections](Deployment_and_Connections.md).

## Primary user journeys

### Build and analyze a world

1. Create a project and story world.
2. Add characters, locations, props, scenes, shots, and relationships.
3. Use search, summaries, analytics, and continuity checks to inspect the
   connected story.
4. Ask a specialist advisor to analyze the current entity with approved Story
   Graph context.
5. Accept, revise, or discard the advisor's suggestions.

### Generate and retain an asset

1. Select a Story Graph entity, generation template, and compatible
   connection.
2. Review or refine the prompt and resolved inputs.
3. Queue the generation request.
4. Inspect job state or cancel the request when supported.
5. Import the completed media into WordPress and retain its source,
   connection, template, and generation provenance.

### Exchange a project

1. Import a validated World Graph Studio JSON document or build the project in
   WordPress.
2. Export the current screenplay or storyboard as Markdown.
3. Use outbound Celtx sync or the bundled EDL format helpers when that workflow
   is enabled.
4. Continue to treat WordPress and the Story Graph as the canonical record.

The [Example Workflow User Guide](example-workflow/USER_GUIDE.md) demonstrates
the product with a complete sample project.

## On hold: additional script formats

Additional file-based script import/export work is outside the current
release and is on hold:

- Final Draft FDX, Fade In, Highland, and Story Architect import.
- Automated screenplay parsing and Story Graph extraction for those formats.
- Format-specific preview, deduplication, and merge workflows.
- Professional screenplay and shooting-script exporters beyond the delivered
  Markdown exports.
- Additional script synchronization providers beyond Celtx.

This boundary does not change the delivered status of World Graph Studio JSON,
Markdown, outbound Celtx, or EDL-helper surfaces. See
[Script and Editorial Interchange](Script_EDL_Integration.md) for format-level
details.

## Extension points, not commitments

AAF and OMF exchange, provider-specific NLE panels, additional AI providers,
graph visualizations, marketplaces, and other integrations are possible
extensions. They are not current-release requirements or active roadmap
commitments.

## Quality requirements

- Enforce WordPress permissions for every admin, REST, and Ability action.
- Sanitize input, escape output, protect nonces, and keep credentials out of
  browser payloads, logs, prompts, and project exports.
- Bound AI context, request sizes, provider timeouts, and background work.
- Preserve useful fallback behavior when optional AI or generation services
  are unavailable.
- Keep public data contracts, Structured Content Fields, tests, and
  documentation synchronized.
- Preserve existing installations through the one-time legacy identifier
  migration while exposing only `worldgraph` as the current namespace.

## Release acceptance

The current release is accepted when a creator can build and traverse a Story
Graph, use its editing and intelligence tools, configure optional AI and
generation connections, retain generated or imported assets with context, and
use the delivered interchange workflows. Those capabilities are implemented
in this repository; optional third-party configuration is an operating
condition, not unfinished product work.

## Related documents

- [Marketing Overview](marketing/overview.md)
- [Delivery Status](Delivery_Status.md)
- [Architecture](World_Graph_Studio_Architecture.md)
- [Roadmap](ROADMAP_World_Graph_Studio.md)
- [Story Graph Specification](Story_Graph_Specification.md)
