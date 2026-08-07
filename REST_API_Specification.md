# StoryOS REST API Specification v1.0

> Build Your Story Once. Create Everywhere.

## Overview

The StoryOS REST API provides a unified integration layer between:

- WordPress
- Story Graph
- Microsoft Agent Framework (MAF)
- wp-comfy
- ComfyUI
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

## Shots

```http
GET    /shots
POST   /shots
PUT    /shots/{id}
```

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

## Import Script

```http
POST /scripts/import
```

Supported Formats:

- Fountain
- FDX
- Celtx
- Fade In
- Markdown

## Export Script

```http
POST /scripts/export
```

Supported Outputs:

- Fountain
- Screenplay
- Shooting Script
- Markdown

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
