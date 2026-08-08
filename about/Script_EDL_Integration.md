# StoryOS Script & EDL Integration Specification v1.0

> Build Your Story Once. Create Everywhere.

## Purpose

This document defines how StoryOS integrates with screenwriting software, production workflows, and editorial systems.

The goal is to allow story information stored within the Story Graph to flow seamlessly between writing, storyboarding, production planning, asset generation, and editing.

---

# Strategic Vision

Traditional filmmaking workflows are fragmented.

Story development occurs in one application.
Production planning occurs in another.
Editorial planning occurs elsewhere.

StoryOS positions the Story Graph as the canonical source of truth.

```text
Story Graph
    ↓
Script
    ↓
Storyboard
    ↓
Shot List
    ↓
Production Plan
    ↓
EDL
    ↓
Editorial Timeline
```

Every downstream artifact can be regenerated from structured story data.

---

# Script Integration Goals

## Import Existing Projects

Creators should be able to migrate existing projects into StoryOS.

## Export Professional Deliverables

StoryOS should support traditional filmmaking workflows.

## Preserve Relationships

Imported content should create Story Graph entities automatically.

---

# Supported Script Imports

## ✅ Celtx Integration (COMPLETE — Phase E)

Full bi-directional synchronization with Celtx via the Celtx GEM API:

- **CPT Synchronization**: Projects, Characters, Locations, Scenes, Shots sync between StoryOS and Celtx
- **Bidirectional Sync**: Changes in either platform propagate to the other
- **Element Mapping**: Persistent StoryOS ↔ Celtx ID mapping stored in post meta
- **API Authentication**: API key, Basic Auth, and Cookie Auth support
- **WordPress Plugin**: `storyos-celtx` plugin handles all Celtx communication
- **REST API**: Sync endpoints via `wp-json/storyos-celtx/v1/*`

### Celtx Sync Architecture

```
StoryOS (WordPress)
    ↓
storyos-celtx Plugin
    ├── API Client (class-celtx-api.php)
    ├── Sync Service (class-celtx-sync.php)
    └── Settings (class-celtx-settings.php)
    ↓
Celtx GEM API (games-api.celtx.com/api)
    ├── /project
    ├── /episode
    ├── /scene
    ├── /element
    ├── /script
    ├── /comment
    ├── /catalog
    ├── /breakdown
    └── /custom_field
```

## Phase 1 — File-Based Import (Planned)

- [ ] Fountain — scene headings, action, dialogue, character extraction
- [ ] Markdown — basic scene detection

## Phase 2 — Professional Formats (Planned)

- [ ] Final Draft (FDX) — XML parsing → Story Graph entities
- [ ] Fade In — import screenplay format
- [ ] Highland — import screenplay format
- [ ] Story Architect — import project data

## Future Support

- [ ] PDF Parsing — text extraction
- [ ] Custom Studio Formats

---

# Import Workflow

## Celtx Bi-Directional Sync (Complete)

```text
StoryOS CPTs
    ↓
Sync Service
    ↓
Celtx API Client
    ↓
Celtx GEM API
    ↓
Celtx Cloud
    ↓ (bidirectional)
Celtx Cloud
    ↓
Celtx GEM API
    ↓
Sync Service
    ↓
StoryOS CPTs
```

Changes in either platform propagate to the other via persistent ID mapping.

## File-Based Import (Planned)

```text
Script File (Fountain, FDX, etc.)
     ↓
Parser
     ↓
Scene Extraction
     ↓
Character Extraction
     ↓
Location Extraction
     ↓
Story Graph Population
```

Generated entities should be linked automatically.

---

# Script Export Targets

## Development Script

Used by writers.

## Screenplay

Standard screenplay format.

## Shooting Script

Includes production metadata.

## Production Script

Includes scene references and scheduling support.

## Fountain

Portable text format.

## Markdown

Documentation and version control friendly format.

---

# Story Graph Mapping

## Character

Maps to:

- Dialogue
- Action References
- Storyboards
- Assets

## Location

Maps to:

- Scene Headers
- Production Scheduling
- Asset Generation

## Scene

Maps to:

- Storyboards
- Shot Lists
- Editorial Sequences

---

# Storyboard Integration

Storyboards are derived from scenes and shots.

```text
Scene
    ↓
Shot Planning
    ↓
Storyboard Frames
    ↓
Generated Assets
```

Each storyboard frame remains connected to its source entities.

---

# Shot List Generation

StoryOS should generate structured shot lists.

Example:

```text
Scene 14

Shot A
Wide Establishing

Shot B
Medium Dialogue

Shot C
Close Up
```

Shot records become reusable production assets.

---

# Production Planning Integration

Generated outputs:

- Shot Lists
- Production Breakdowns
- Schedules
- Call Sheets
- Character Reports
- Location Reports

All data originates from Story Graph entities.

---

# EDL Integration

## Purpose

Provide editorial systems with structured timeline information.

## Export Targets

### Phase 1

- EDL

### Future

- XML
- AAF
- NLE-specific exports

---

# Editorial Workflow

```text
Scene
    ↓
Shot
    ↓
Storyboard
    ↓
Timeline Segment
    ↓
EDL Export
```

This enables early editorial planning.

---

# Editorial Metadata

Each shot may contain:

- Duration
- Camera Information
- Lens Information
- Storyboard References
- Asset References
- Production Notes
- Editorial Notes

---

# Continuity Support

Editorial exports should retain links to:

- Characters
- Locations
- Props
- Storyboards
- Assets

This allows future continuity validation.

---

# AI Advisor Support

Agents may assist with:

- Script analysis
- Scene breakdowns
- Shot recommendations
- Storyboarding suggestions
- Production preparation
- Editorial planning

All recommendations should use Story Graph context.

---

# Future Opportunities

## Auto Storyboarding

Script → Storyboard generation.

## Auto Shot Planning

Scene → Shot recommendations.

## Editorial Assistants

Generate suggested EDL structures.

## Continuity Engine

Detect inconsistencies across script, assets, storyboards, and editorial outputs.

---

# Success Criteria

StoryOS can:

1. Import screenplay formats.
2. Populate the Story Graph automatically.
3. Generate production artifacts.
4. Generate editorial artifacts.
5. Maintain relationships throughout the lifecycle.

---

# Design Principle

A script is not the source of truth.

A storyboard is not the source of truth.

An EDL is not the source of truth.

The Story Graph is the source of truth.

All creative, production, and editorial deliverables are derived representations of graph data.
