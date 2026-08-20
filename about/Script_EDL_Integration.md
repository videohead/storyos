# World Graph Studio Script & EDL Integration Specification v1.0

> Build Your Story Once. Create Everywhere.

## Purpose

This document defines how World Graph Studio integrates with screenwriting software, production workflows, and editorial systems.

The goal is to allow story information stored within the Story Graph to flow seamlessly between writing, storyboarding, production planning, asset generation, and editing.

---

# Strategic Vision

Traditional filmmaking workflows are fragmented.

Story development occurs in one application.
Production planning occurs in another.
Editorial planning occurs elsewhere.

World Graph Studio positions the Story Graph as the canonical source of truth.

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

Creators should be able to migrate existing projects into World Graph Studio.

## Export Professional Deliverables

World Graph Studio should support traditional filmmaking workflows.

## Preserve Relationships

Imported content should create Story Graph entities automatically.

---

# Supported Script Imports

## ✅ Celtx Integration (COMPLETE — Phase E)

Full bi-directional synchronization with Celtx via the Celtx GEM API:

- **CPT Synchronization**: Projects, Characters, Locations, Scenes, Shots sync between World Graph Studio and Celtx
- **Bidirectional Sync**: Changes in either platform propagate to the other
- **Element Mapping**: Persistent World Graph Studio ↔ Celtx ID mapping stored in post meta
- **API Authentication**: API key, Basic Auth, and Cookie Auth support
- **WordPress Plugin**: `worldgraph-celtx` plugin handles all Celtx communication
- **REST API**: Sync endpoints via `wp-json/worldgraph-celtx/v1/*`

### Celtx Sync Architecture

```
World Graph Studio (WordPress)
    ↓
worldgraph-celtx Plugin
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
World Graph Studio CPTs
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
World Graph Studio CPTs
```

Changes in either platform propagate to the other via persistent ID mapping.

## File-Based Import (Planned)

```text
Script File (FDX, etc.)
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

## Storyboard

Pictoral representation of the script with scene and camera descriptions

## Development Script

Used by writers.

## Screenplay

Standard screenplay format.

## Shooting Script

Includes production metadata.

## Production Script

Includes scene references and scheduling support.

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

World Graph Studio should generate structured shot lists.

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

Provide editorial systems with structured timeline information from World Graph Studio projects and episodes. Enables bidirectional workflow between World Graph Studio shot planning and professional NLEs (Non-Linear Editors).

**Status**: ✅ Implemented — CMX 3600 ASCII and SMPTE 436m XML export/import

## Supported Formats

| Format | Import | Export | Status |
|--------|--------|--------|--------|
| CMX 3600 (ASCII) | ✅ | ✅ | ✅ Complete |
| SMPTE 436m (XML) | ✅ | ✅ | ✅ Complete |
| AAF | ❌ | ❌ | 📋 Future |

## NLE Compatibility

Fully compatible with:

- ✅ **Unreal Engine Sequencer** — pre-roll/post-roll handles, drop-frame timecode
- ✅ **Adobe Premiere Pro** — 32-character clip names, drop-frame timecode
- ✅ **DaVinci Resolve** — multi-track (V/A), drop-frame timecode
- ✅ **Avid Media Composer** — CMX 3600 standard, multi-track
- ✅ **Final Cut Pro** — XML format, standard EDL

## Features

### Export
- CMX 3600 ASCII format (universal NLE compatibility)
- SMPTE 436m XML format (structured XML)
- Drop-frame timecode for 29.97/59.94fps NTSC
- Pre-roll / Post-roll handles (Unreal Engine Sequencer)
- 32-character clip names (Premiere Pro)
- Configurable video tracks (V1, V2, V C) and audio tracks (A1, A2, A C)
- Frame rate presets: 23.976, 24, 25, 29.97, 30, 50, 59.94, 60

### Import
- Upload `.txt`, `.edl`, or `.xml` EDL files
- Preview detected clips before importing
- Automatic format detection (CMX 3600 / XML)
- Frame rate conversion support

## Unreal Engine Sequencer Workflow

1. **Export from World Graph Studio** → Shot timeline as EDL with pre-roll/post-roll handles
2. **Render in Unreal Engine** → Sequencer exports video clips + EDL
3. **Edit in NLE** → Import EDL into Premiere Pro/DaVinci Resolve, link media, make edits
4. **Re-import to UE** → Export edited EDL from NLE, import back into Unreal Engine Sequencer
5. **Sync Changes** → Updated timing/cuts reflected in the Unreal Engine sequence

## Export Targets

### ✅ Complete

- CMX 3600 ASCII EDL
- SMPTE 436m XML EDL
- Drop-frame timecode support
- Frame handles (pre-roll/post-roll)
- Multi-track (video + audio)
- 32-character clip names

### Future

- AAF (Advanced Authoring Format)
- OMF (Open Media Framework)
- NLE-specific plugins (Premiere Pro panel, DaVinci Resolve plugin)
- Direct media linking (EDL with absolute file paths)

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
EDL Export (CMX 3600 / XML)
    ↓
NLE Editorial (Premiere Pro, DaVinci Resolve, Avid, Unreal Engine)
    ↓
EDL Import (optional re-sync)
```

This enables early editorial planning and bidirectional NLE integration.

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
- Source In/Out points
- Record In/Out points
- Pre-roll / Post-roll handles

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

World Graph Studio can:

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
