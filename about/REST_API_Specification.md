# World Graph Studio REST API Specification v1.0

> Your ideas. Your assets. No credits needed.

## Status and Scope

The REST API described here is delivered in the current repository. Its core
namespace is `worldgraph/v1`, exposed by WordPress beneath:

```text
/wp-json/worldgraph/v1/
```

This document records the implemented contract rather than a prospective API.
See [Delivery Status](Delivery_Status.md) for the release boundary and
[Deployment and Connections](Deployment_and_Connections.md) for runtime and
credential setup.

The API covers Story Graph resources, relationships, production/editorial
views, JSON import, generation jobs, the AI Editor, search, and optional
integrations. Final Draft FDX is a delivered WordPress admin import workflow
that normalizes into the canonical JSON importer; it does not register a
`/scripts/*` REST route. Fountain targets the same admin/importer pattern but
has a current browser bootstrap blocker and is not a delivered workflow.
Further professional script-file adapters are extension opportunities rather
than part of the v1 API contract.

## Authentication and Permissions

World Graph Studio uses WordPress authentication and capabilities. Supported
deployment mechanisms include an authenticated WordPress session with REST
nonces and WordPress Application Passwords over HTTPS. Read, edit, create,
delete, generation, import, connection, and administrator operations use the
capability checks in their controller. The Search routes are the explicit
current exception and are registered without an authentication requirement.

World Graph Studio does not implement a separate OAuth, Microsoft Entra ID, or
service-account protocol. A site may add those mechanisms with a WordPress
authentication plugin, but that is an extension boundary rather than part of
this API.

Connection routes are administrator-only because `credential_reference` and
`mcp_credential_reference` may be `env://` pointers or credentials entered for
local evaluation. Treat their responses as sensitive control-plane data; do
not expose them to public clients or application logs. Suno uses the first for
SunoAPI.org REST and the second for AceData Cloud MCP; the values are distinct.

## Common Resource Contract

The primary Story Graph collection routes follow a shared pattern:

```http
GET    /wp-json/worldgraph/v1/{resource}
POST   /wp-json/worldgraph/v1/{resource}
GET    /wp-json/worldgraph/v1/{resource}/{id}
PUT    /wp-json/worldgraph/v1/{resource}/{id}
DELETE /wp-json/worldgraph/v1/{resource}/{id}
GET    /wp-json/worldgraph/v1/{resource}/{id}/graph
```

The implemented resource bases are:

| Resource | CPT key |
| --- | --- |
| `projects` | `worldgraph_project` |
| `storyworlds` | `worldgraph_world` |
| `characters` | `worldgraph_character` |
| `locations` | `worldgraph_location` |
| `props` | `worldgraph_prop` |
| `organizations` | `worldgraph_org` |
| `episodes` | `worldgraph_episode` |
| `scenes` | `worldgraph_scene` |
| `shots` | `worldgraph_shot` |
| `sounds` | `worldgraph_sound` |
| `storyboard-frames` | `worldgraph_board` |
| `assets` | `worldgraph_asset` |
| `editorial-artifacts` | `worldgraph_editorial` |

Collection responses support controller-specific filters. Resource responses
include the WordPress identity and lifecycle fields, SCF-backed `meta`, assigned
`taxonomies`, outgoing `relationships`, Schema.org mapping hints, featured
media, and the World Graph Studio asset gallery where applicable.

Project, Story World, Scene, and Character list filters include their relevant
taxonomy and relationship criteria. Scene and Shot ordering is also exposed:

```http
POST /wp-json/worldgraph/v1/scenes/reorder
POST /wp-json/worldgraph/v1/shots/reorder
```

### Sound Validation

Sounds are planned soundtrack cues, not audio file encodings. A Sound requires
a title, exactly one `worldgraph_sound_type`, and a Scene. An optional Shot must
belong to that Scene, and an optional rendered Asset must have the Audio asset
type.

Supported list filters include `scene`, `shot`, `sound_type`,
`production_status`, and the WordPress post `status`. Ordinary screenplay
dialogue remains structured Scene metadata; the reserved `dialogue` sound-type
slug cannot be assigned.

Example create payload:

```json
{
  "title": "Forest Path Song",
  "content": "A cautious traveling theme.",
  "meta": {
    "sound_type": "music",
    "production_status": "in-development",
    "lyrics": "Stay to the path through shadow and pine.",
    "start_timecode": "00:00:00:00",
    "duration": "PT18S",
    "diegetic": "non_diegetic",
    "scene": 827,
    "shot": 913,
    "character": 0,
    "asset": 0
  }
}
```

## Story Graph

The graph controller exposes entity discovery and canonical relationship
operations:

```http
GET    /wp-json/worldgraph/v1/graph/{id}
GET    /wp-json/worldgraph/v1/graph/entities
GET    /wp-json/worldgraph/v1/graph/relationships
POST   /wp-json/worldgraph/v1/graph/relationships
DELETE /wp-json/worldgraph/v1/graph/relationships/{from_id}/{to_id}
```

The resource-specific `/{resource}/{id}/graph` routes return the same graph
context around a typed entity. Relationship records carry source and target
IDs/types, a relationship type, and optional metadata. UI and API wording uses
Source for provenance and Linked for association.

Examples of canonical semantics:

- Project `contains` Story World or Episode.
- Episode `contains` Scene.
- Scene `contains` Shot.
- Character `appears_in` Scene.
- Sound `belongs_to` Scene or Shot and may link to a Character and audio Asset.
- Asset `derived_from` or `references` a Story Graph source.

## Sequences

Sequences are `worldgraph_sequence` taxonomy terms with ordering helpers:

```http
GET    /wp-json/worldgraph/v1/sequences
POST   /wp-json/worldgraph/v1/sequences
GET    /wp-json/worldgraph/v1/sequences/{id}
DELETE /wp-json/worldgraph/v1/sequences/{id}
POST   /wp-json/worldgraph/v1/sequences/reorder
POST   /wp-json/worldgraph/v1/sequences/{id}/shots
POST   /wp-json/worldgraph/v1/sequences/{id}/scenes
```

## World Graph Studio JSON Import

The delivered importer accepts a World Graph Studio JSON document. It validates
cross-references, creates or updates supported Story Graph entities, assigns
taxonomies, builds relationships, and reports the resolved counts.

```http
POST /wp-json/worldgraph/v1/import/validate
POST /wp-json/worldgraph/v1/import
```

`/import/validate` performs a dry run. `/import` accepts the JSON document and
an optional overwrite flag. Import requires administrator permission. The same
engine is available through the WordPress Import admin screen.

Markdown screenplay and storyboard export is delivered through the WordPress
admin export action and exporter class; there is no `/scripts/export` REST
route in v1.

The bundled Final Draft FDX and Fountain integrations run through
capability- and nonce-protected WordPress admin actions. They parse locally in
the browser, normalize supported screenplay structure into the World Graph
Studio JSON contract, and delegate persistence to the importer above. They do
not add REST routes.

Fade In, Highland, Story Architect, format-specific preview/merge, and
professional script-export routes are not registered in v1. Consumers must
not depend on `/scripts/*` paths; future adapters should document their own
route contracts.

## Generation

Generation uses an active `worldgraph_template` paired with an available
`worldgraph_conn`. WordPress creates an internal generation record, schedules a
bounded WP-Cron batch, invokes the matching adapter, polls asynchronous jobs,
imports returned media or retains normalized text results, and records
provenance.

```http
POST /wp-json/worldgraph/v1/generation
POST /wp-json/worldgraph/v1/generation/suno-callback
GET  /wp-json/worldgraph/v1/generation/{id}
POST /wp-json/worldgraph/v1/generation/{id}/cancel
GET  /wp-json/worldgraph/v1/generation/asset/{asset_id}/history
GET  /wp-json/worldgraph/v1/generation/templates/{id}/requirements
```

The `POST /generation` payload contains an output `type`, prompt,
Template/workflow reference, parameters, and optional target Asset and bound
inputs. The controller accepts `image`, `video`, `audio`, and `text` type values,
but the Template and Connection must name the same registered provider adapter
and the requested output must match an available Template modality.

The editor-facing image workflow is also available through:

```http
GET  /wp-json/worldgraph/v1/assets/generate/prompt
POST /wp-json/worldgraph/v1/assets/generate
```

Delivered execution adapters are Comfy Cloud MCP, local ComfyUI HTTP workflows,
fal MCP, ElevenLabs, Suno through SunoAPI.org REST and AceData Cloud MCP, and
VideoDraft MCP.
The Suno callback route is public because the provider calls it, but an HMAC
query token binds it to one Suno Connection. It only schedules an authenticated
poll; the worker still retrieves canonical status and imports every final track
before completing the job. The built-in catalogs provision text-to-image,
ElevenLabs audio, and Suno music/lyrics Templates. Additional output modalities
need an adapter that registers and executes a compatible Template; a provider
value without that implementation is configuration metadata only.

See [Suno Integration](plugins/SUNO.md) for the transport-specific Template,
callback, polling, credential, and result contracts.

## Connections

```http
GET    /wp-json/worldgraph/v1/connections
POST   /wp-json/worldgraph/v1/connections
POST   /wp-json/worldgraph/v1/connections/sync
GET    /wp-json/worldgraph/v1/connections/{id}
PUT    /wp-json/worldgraph/v1/connections/{id}
DELETE /wp-json/worldgraph/v1/connections/{id}
GET    /wp-json/worldgraph/v1/connections/{id}/resolve
POST   /wp-json/worldgraph/v1/connections/{id}/test
```

Connection status is the load/disable authority for provider adapters.
`resolve` reports the normalized Connection configuration, including its
sensitive credential references; `test` exercises the provider-specific
readiness check; `sync` refreshes the local provider-capability descriptor.
Provider catalog and Template discovery run through provider-specific
save/test/admin flows rather than this generic capability route.

The adapter registry is extensible through the `worldgraph_conn_adapters`
filter. An integration can contribute provider metadata, a callable loader,
optional initialization, and guided setup choices without changing the
Connection resource contract. Plugin-relative `files` are resolved inside the
main World Graph Studio plugin and are intended for bundled implementations;
external plugins should use a callable loader.

## AI Editor and Advisors

The Gutenberg AI Editor exposes permission-aware routes under the core
namespace:

```http
POST /wp-json/worldgraph/v1/ai/chat
POST /wp-json/worldgraph/v1/ai/analyze
POST /wp-json/worldgraph/v1/ai/generate
POST /wp-json/worldgraph/v1/ai/continuity
GET  /wp-json/worldgraph/v1/ai/context
GET  /wp-json/worldgraph/v1/ai/agents
GET  /wp-json/worldgraph/v1/ai/settings
GET  /wp-json/worldgraph/v1/ai/health
```

The plugin also registers an older record-oriented route shape:

```http
GET|POST        /wp-json/worldgraph/v1/agents
GET|PUT|DELETE  /wp-json/worldgraph/v1/agents/{id}
POST            /wp-json/worldgraph/v1/agents/{id}/actions
GET             /wp-json/worldgraph/v1/agents/{id}/history
```

Those record routes expect a `worldgraph_agent` post type, which is not part of
the current 15-type Story Graph registration. They are retained implementation
surface, not a supported advisor-record API. Current clients should discover
the `.agent.md` advisor profiles through `GET /worldgraph/v1/ai/agents` and use
the `/ai/*` routes above.

The current bundle contains more than 50 specialist profiles. WordPress scans
the plugin-owned agent directory at runtime, so adding another focused profile
does not require a new REST route, data model, or execution service.

An LLM connection is optional for the Story Graph itself but required for
routes that request model output.

## Search

```http
POST /wp-json/worldgraph/v1/search
GET  /wp-json/worldgraph/v1/search/suggest
```

Search accepts a query, optional entity-type filters, mode, and result limit.
The current `semantic` mode uses the same WordPress-backed retrieval as keyword
mode; no vector-store integration is registered. Both Search routes are public.
Anonymous requests receive published records. Authenticated editors may also
receive non-public lifecycle states that WordPress permits them to read.

## Production and Editorial Views

Project-scoped production endpoints expose the delivered planning model:

```http
GET  /wp-json/worldgraph/v1/production/{project_id}/overview
GET  /wp-json/worldgraph/v1/production/{project_id}/pipeline
PUT  /wp-json/worldgraph/v1/production/{project_id}/stage
GET  /wp-json/worldgraph/v1/production/{project_id}/tasks
POST /wp-json/worldgraph/v1/production/{project_id}/tasks
PUT  /wp-json/worldgraph/v1/production/tasks/{task_id}/status
GET  /wp-json/worldgraph/v1/production/{project_id}/timeline
```

Project-scoped editorial routes provide views, records, export data, reviews,
and storyboards:

```http
GET  /wp-json/worldgraph/v1/editorial/{project_id}/overview
GET  /wp-json/worldgraph/v1/editorial/{project_id}/artifacts
POST /wp-json/worldgraph/v1/editorial/{project_id}/artifacts
POST /wp-json/worldgraph/v1/editorial/{project_id}/export
GET  /wp-json/worldgraph/v1/editorial/{project_id}/reviews
POST /wp-json/worldgraph/v1/editorial/{project_id}/reviews
GET  /wp-json/worldgraph/v1/editorial/{project_id}/storyboard
```

The optional EDL plugin delivers CMX 3600 and SMPTE 436m XML formatters and
downloads plus import parsing and preview through its nonce- and
capability-protected WordPress admin/AJAX workflow. Its current export resolver
uses fixed sample clips rather than a live Project/Episode timeline, and its
advanced export controls are not wired through to the formatter. Confirmed
previews are not persisted as Story Graph timeline records. The plugin does not
register the speculative `/editorial/edl/generate` REST path.

## Optional Celtx Synchronization

When the bundled Celtx plugin is enabled and configured, it registers these
administrator-only routes in the core namespace:

```http
GET    /wp-json/worldgraph/v1/celtx/test
GET    /wp-json/worldgraph/v1/celtx/sync
POST   /wp-json/worldgraph/v1/celtx/sync
POST   /wp-json/worldgraph/v1/celtx/sync/{type}
POST   /wp-json/worldgraph/v1/celtx/sync/{type}/{id}
GET    /wp-json/worldgraph/v1/celtx/mapping/{type}/{id}
DELETE /wp-json/worldgraph/v1/celtx/unsync/{type}/{id}
```

Celtx synchronization sends supported Project, Character, Location, Scene, and
Shot data from World Graph Studio to Celtx and stores persistent external ID
mappings in `_worldgraph_celtx_mapping`. The current sync service does not
import remote Celtx changes into WordPress.

## Optional VideoDraft Synchronization

When VideoDraft Sync is enabled and a `videodraft` Connection is selected, it
registers these administrator-only routes:

```http
GET    /wp-json/worldgraph/v1/videodraft/projects
GET    /wp-json/worldgraph/v1/videodraft/schema
POST   /wp-json/worldgraph/v1/videodraft/push
POST   /wp-json/worldgraph/v1/videodraft/pull
GET    /wp-json/worldgraph/v1/videodraft/mapping/{project_id}
DELETE /wp-json/worldgraph/v1/videodraft/mapping/{project_id}
```

Push accepts `project_id`, optional `connection_id`, optional
`remote_project_id`, and `force`. Pull accepts `remote_project_id`, optional
`connection_id`, `force`, and `dry_run`; `dry_run` defaults to `true`. Existing
remote Projects are checkpointed before update. Mapping state is stored in
`_worldgraph_videodraft_mapping` and credentials remain on the Connection.

The routes map the shared Project/script/storyboard/visual-asset subset rather
than promising lossless VideoDraft production-timeline interchange. See
[VideoDraft Connection and Sync](plugins/VIDEODRAFT.md).

## Google Web Stories Extension Prototype

The repository contains prototype source for a separate
`worldgraph-web-stories/v1` namespace:

```http
POST /wp-json/worldgraph-web-stories/v1/sync/story/{story_id}
POST /wp-json/worldgraph-web-stories/v1/sync/scene/{scene_id}
POST /wp-json/worldgraph-web-stories/v1/sync/all
GET  /wp-json/worldgraph-web-stories/v1/mapping/{post_id}
GET  /wp-json/worldgraph-web-stories/v1/status
GET  /wp-json/worldgraph-web-stories/v1/settings
POST /wp-json/worldgraph-web-stories/v1/settings
```

These paths document the prototype controller surface, not routes delivered by
the active World Graph Studio plugin. The main plugin does not load or register
the Web Stories package, and the package's current bootstrap and settings
paths are not production-ready. Clients must not depend on bidirectional sync,
automatic sync, storyboard-page sync, or an admin sync dashboard unless an
extension first completes and activates that integration.

## Errors and Versioning

Controllers return standard WordPress REST errors. A typical error has this
shape:

```json
{
  "code": "worldgraph_sound_scene_required",
  "message": "A Sound must belong to a Scene.",
  "data": { "status": 400 }
}
```

The stable current namespace is `worldgraph/v1`. Extensions should use their
own namespace when they do not implement the core contract, and clients should
feature-detect optional integration routes rather than assume they are active.
