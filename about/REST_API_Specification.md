# StoryOS REST API Specification v1.0

> Build Your Story Once. Create Everywhere.

## Overview

> **Implementation update:** the current API is WordPress-native. WordPress Abilities replace MAF services, and Comfy Cloud MCP replaces the Python generation runtime. Historical endpoints in this document require review before implementation; see [Deployment and Connections](Deployment_and_Connections.md).

The StoryOS REST API provides a unified integration layer between:

- WordPress
- Story Graph
- WordPress Abilities API
- WordPress generation records and WP-Cron
- Comfy Cloud MCP
- Script Importers
- Production Workflows
- Editorial Systems

The API exposes Story Graph entities and workflows while maintaining a consistent contract for internal and external integrations.

---

# Design Principles

## Story Graph First

All API operations ultimately read or write Story Graph entities.

## Resource Oriented

Entities are exposed as REST resources.

## Extensible

New entity types and workflows can be added without breaking existing integrations.

## Auditable

All modifications should support activity tracking and version history.

---

# Base URL

```text
/api/storyos/v1
```

---

# Authentication

Supported mechanisms:

```text
WordPress Authentication
Application Passwords
OAuth (Future)
Entra ID (Future)
Service Accounts (Future)
```

---

# Core Resources

## Projects

```http
GET    /projects
GET    /projects/{id}
POST   /projects
PUT    /projects/{id}
DELETE /projects/{id}
```

## Story Worlds

```http
GET    /worlds
GET    /worlds/{id}
POST   /worlds
PUT    /worlds/{id}
```

## Characters

```http
GET    /characters
GET    /characters/{id}
POST   /characters
PUT    /characters/{id}
DELETE /characters/{id}
```

Common list filters:

- `character_role` (slug or comma-separated slugs)
- `status` (taxonomy slug)

Filter combination behavior:

- Filters are combined with `AND` across different filter keys.
- Comma-separated values within a single key are matched as OR terms for that taxonomy.

Examples:

```http
GET /characters?character_role=protagonist&status=approved
GET /characters?character_role=protagonist,mentor&status=in-development
```

Expected response snippet:

```json
[
  {
    "id": 412,
    "type": "storyos_character",
    "title": "Mara Quinn",
    "meta": {
      "character_roles": [
        { "id": 12, "name": "Protagonist", "slug": "protagonist" }
      ]
    },
    "taxonomies": {
      "storyos_character_role": [
        { "id": 12, "name": "Protagonist", "slug": "protagonist" }
      ],
      "storyos_status": [
        { "id": 7, "name": "Approved", "slug": "approved" }
      ]
    }
  }
]
```

Character responses include taxonomy metadata such as:

- `storyos_character_relation`
- `storyos_character_role`

## Locations

```http
GET    /locations
POST   /locations
PUT    /locations/{id}
```

## Scenes

```http
GET    /scenes
GET    /scenes/{id}
POST   /scenes
PUT    /scenes/{id}
```

Common list filters:

- `sequence` (slug or comma-separated slugs)
- `status` (taxonomy slug)

Filter combination behavior:

- Filters are combined with `AND` across different filter keys.
- Comma-separated values within a single key are matched as OR terms for that taxonomy.

Examples:

```http
GET /scenes?sequence=climax&status=approved
GET /scenes?sequence=midpoint,climax&status=approved
```

Expected response snippet:

```json
[
  {
    "id": 827,
    "type": "storyos_scene",
    "title": "Bridge Confrontation",
    "meta": {
      "sequences": [
        { "id": 24, "name": "Climax", "slug": "climax" }
      ],
      "shot_count": 11
    },
    "taxonomies": {
      "storyos_sequence": [
        { "id": 24, "name": "Climax", "slug": "climax" }
      ],
      "storyos_status": [
        { "id": 7, "name": "Approved", "slug": "approved" }
      ]
    }
  }
]
```

Scene responses include taxonomy metadata such as:

- `storyos_scene_tag`
- `storyos_sequence`

## Shots

```http
GET    /shots
POST   /shots
PUT    /shots/{id}
```

Shot metadata includes:

- `take_number`
- `slate_id`
- `shot_type` values including `establishing`, `insert`, `cutaway`, and `reaction`

## Sounds

```http
GET    /sounds
GET    /sounds/{id}
POST   /sounds
PUT    /sounds/{id}
DELETE /sounds/{id}
GET    /sounds/{id}/graph
```

Sounds are planned soundtrack cues, not audio file encodings. A Sound links to
an audio-typed Asset, which can represent the rendered WordPress attachment.

List filters:

- `scene` (post ID)
- `shot` (post ID)
- `sound_type` (slug or comma-separated slugs)
- `production_status` (`storyos_status` taxonomy slug or comma-separated slugs)
- `status` (WordPress lifecycle: `draft`, `pending`, `publish`, or `private`)

Creation requires a non-empty `title`, exactly one `meta.sound_type`, and
`meta.scene`. If `meta.shot` is provided, the API validates that it belongs to
the selected Scene. Nonzero `meta.asset` values must reference an audio-typed
Asset.

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

Seed terms are `narration`, `voiceover`, `music`, `sound-effect`, `ambience`,
`foley`, `silence`, and `adr`; `storyos_sound_type` remains extensible.
Custom terms must be created through the taxonomy API or admin before REST use.
The `dialogue` slug is reserved and cannot be created or assigned.
`spoken_text` is reserved for narration, voice-over, or ADR. Existing Scene
dialogue remains canonical and is not duplicated into Sound resources.

## Assets

```http
GET    /assets
GET    /assets/{id}
POST   /assets
PUT    /assets/{id}
```

---

# Story Graph Endpoints

## Entity Relationships

```http
GET /graph/entity/{id}
GET /graph/entity/{id}/relationships
```

Response includes:

- Related entities
- Relationship types
- Metadata

## Relationship Semantics

StoryOS distinguishes two link intents in API/UI wording:

- Source: provenance link where an output is derived from an origin entity.
- Linked: associative link where entities are related but not necessarily derived.

Examples:

- Asset -> Source Scene (provenance)
- Asset -> Source Character (provenance)
- Character -> Linked Asset (association)
- Sound -> Scene/Shot (`belongs_to` placement)
- Sound -> Character (optional voice/narrator association)
- Sound -> Asset (optional rendered-audio encoding)

## Vocabulary Semantics

API payloads and docs follow these shared term meanings:

- Shot: continuous footage between two edits.
- Take: one recorded attempt of a shot; modeled as shot production metadata.
- Sequence: one or more scenes grouped by dramatic progression (`storyos_sequence`).
- Continuity: consistency across adjacent shots/scenes and linked entities.
- EDL: editorial decision output represented as an Editorial Artifact.
- ADR: post-production dialogue replacement metadata when present.

Lifecycle interpretation for status-like fields:

- Pre-Production -> planning-oriented states and artifacts
- Principal Photography -> capture/execution states
- Post-Production -> editorial/finishing states

## Graph Traversal

```http
POST /graph/query
```

Example:

```json
{
  "entityType": "Character",
  "entityId": 123,
  "depth": 3
}
```

---

# Script Integration API

## ✅ Celtx Integration (COMPLETE — Phase E)

The `storyos-celtx` WordPress plugin provides bi-directional sync with Celtx via the Celtx GEM API.

### Sync Endpoints

```http
GET  /wp-json/storyos-celtx/v1/sync/status
POST /wp-json/storyos-celtx/v1/sync/characters
POST /wp-json/storyos-celtx/v1/sync/locations
POST /wp-json/storyos-celtx/v1/sync/scenes
POST /wp-json/storyos-celtx/v1/sync/shots
POST /wp-json/storyos-celtx/v1/sync/projects
POST /wp-json/storyos-celtx/v1/sync/full
```

### Settings Endpoints

```http
GET  /wp-json/storyos-celtx/v1/settings
POST /wp-json/storyos-celtx/v1/settings
```

### Authentication

- API Key: `x-api-key` header (primary)
- Basic Auth: `Authorization: Basic base64(username:password)`
- Cookie Auth: `Cookie: cx_session=...`

### Supported Formats (Planned)

#### Import

- [ ] FDX (Final Draft) — XML parsing → Story Graph entities
- [ ] Fade In — import screenplay format
- [ ] Highland — import screenplay format
- [ ] Markdown — basic scene detection

#### Export

- [ ] Markdown — structured markdown export
- [ ] Storyboard - export a storyboard as a PDF
- [ ] Screenplay — formatted screenplay export
- [ ] Shooting Script — scene numbers, shot descriptions, asset references


### Import/Export Endpoints (Planned)

```http
POST /scripts/import
POST /scripts/export
GET  /scripts/import/{id}/preview
POST /scripts/import/{id}/commit
GET  /scripts/export/{project_id}?format=shooting
```

---

# Storyboard API

## Generate Storyboard

```http
POST /storyboards/generate
```

Input:

- Scene ID
- Shot IDs
- Style profile

Output:

- Storyboard records
- Generated assets

---

# Asset Generation API

## Execute Workflow

```http
POST /generation/workflows/run
```

Payload:

```json
{
  "sceneId": 15,
  "workflow": "character-sheet",
  "model": "flux"
}
```

## Workflow Status

```http
GET /generation/workflows/{id}
```

## Retrieve Assets

```http
GET /generation/assets/{id}
```

---

# MAF Agent API

## Execute Advisor

```http
POST /agents/run
```

Sample:

```json
{
  "agent": "story-advisor",
  "projectId": 1,
  "prompt": "Review character consistency"
}
```

## Get Agent Context

```http
GET /agents/context/{projectId}
```

---

# Production API

## Generate Shot List

```http
POST /production/shotlists/generate
```

## Generate Schedule

```http
POST /production/schedules/generate
```

## Generate Breakdown

```http
POST /production/breakdowns/generate
```

---

# Editorial API

## Generate EDL

```http
POST /editorial/edl/generate
```

## Export Timeline Metadata

```http
POST /editorial/timeline/export
```

## Editorial Artifacts

```http
GET /editorial/artifacts
```

---

# Search API

## Entity Search

```http
GET /search?q=query
```

## Semantic Search

```http
POST /search/semantic
```

Future enhancement using vector search.

---

# Events

StoryOS should support event-driven workflows.

Example events:

```text
ProjectCreated
CharacterUpdated
SceneCreated
AssetGenerated
StoryboardCreated
EDLGenerated
```

---

# Versioning

API versioning format:

```text
/api/storyos/v1
/api/storyos/v2
```

Backward compatibility should be maintained whenever possible.

---

# Error Format

```json
{
  "success": false,
  "code": "SCENE_NOT_FOUND",
  "message": "Scene does not exist"
}
```

---

# Long-Term Objective

The StoryOS API becomes the integration backbone connecting storytelling, generation, production, and editorial systems through a common Story Graph platform.
