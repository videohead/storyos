# StoryOS Content Model Specification v1.0

> Build Your Story Once. Create Everywhere.

## Overview

The StoryOS Content Model defines the canonical Story Graph used by the platform.

All story, production, asset, and editorial information is represented as structured entities stored within WordPress using Custom Post Types (CPTs), Structured Content Fields (SCF), taxonomies, relationships, and metadata.

The Story Graph serves as the primary source of truth.

Scripts, storyboards, generated assets, production plans, schedules, and editorial artifacts are derived views of Story Graph data.

---

# Core Design Principles

## Story First

Narrative entities drive every workflow.

## Structured Content

Stories are represented as connected objects rather than documents.

## Reusable Data

Information entered once can be reused throughout the production lifecycle.

## AI Accessible

All entities must be queryable by AI advisors and workflows.

## Interoperability

All entities should support import, export, API access, and future integrations.

---

# Entity Relationship Overview

Project
├── Story Worlds
├── Characters
├── Locations
├── Props
├── Organizations
├── Episodes
├── Scenes
├── Shots
├── Storyboards
├── Sounds
├── Assets
└── Editorial Artifacts

---

# CPT: Project

Represents a top-level creative project.

## Fields

- Project Name
- Description
- Genre
- Status
- Target Medium
- Owner
- Team Members
- Production Stage
- Created Date
- Updated Date

## Relationships

- Owns Story Worlds
- Owns Assets
- Owns Scripts
- Owns Storyboards

---

# CPT: Story World

Represents a fictional universe.

## Fields

- World Name
- Description
- Timeline
- Rules
- Themes
- Geography
- Historical Notes

## Relationships

- Contains Characters
- Contains Locations
- Contains Organizations

---

# CPT: Character

## Fields

- Name
- Biography
- Age
- Visual Description
- Voice Description
- Personality Traits
- Motivation
- Backstory
- Tags

## Relationships

- Appears In Scenes
- Associated With Locations
- Related To Other Characters
- Referenced By Storyboards
- Referenced By Assets

## Taxonomies

- Character Role

---

# CPT: Location

## Fields

- Name
- Description
- Geography
- Environment Type
- Mood
- Visual References

## Relationships

- Contains Scenes
- Appears In Storyboards
- Linked To Assets

---

# CPT: Prop

## Fields

- Name
- Description
- Purpose
- Ownership
- References

## Relationships

- Used In Scenes
- Appears In Assets

---

# CPT: Organization

## Fields

- Name
- Type
- Description
- Leadership
- Relationships

---

# CPT: Episode

## Fields

- Episode Number
- Summary
- Status

## Relationships

- Contains Scenes

---

# CPT: Scene

## Fields

- Scene Number
- Title
- Description
- Script Content
- Dialogue (structured speaker, line, description, and sequence entries)
- Location
- Time Of Day
- Characters
- Notes
- Sequence

## Relationships

- Belongs To Episode
- Contains Shots
- Contains Sounds
- References Assets
- References Storyboards

---

# CPT: Shot

## Fields

- Shot Number
- Camera Angle
- Lens
- Duration
- Notes
- Editorial Metadata

## Relationships

- Belongs To Scene
- Linked From Sounds
- References Storyboard Frames
- References Assets

---

# CPT: Storyboard Frame

## Fields

- Frame Number
- Description
- Prompt
- Image Reference
- Notes

## Relationships

- Belongs To Scene
- Belongs To Shot

---

# CPT: Sound

Represents one planned soundtrack cue in a Scene, optionally narrowed to a
Shot. It stores sound intent independently from the recorded/generated file so
the same audio Asset can be reused by multiple cues.

## Fields

- Sound Type
- Production Status
- Spoken Text (Narration / Voice-over / ADR)
- Lyrics (Music)
- Start Timecode
- Duration
- Story-world Relation (Diegetic / Non-diegetic / Internal / Mixed)
- Production Notes

## Relationships

- Belongs To Scene
- Belongs To Shot (Optional)
- Linked To Narrator / Voice Character (Optional)
- Linked To Rendered Audio Asset (Optional)

Ordinary dialogue continues to live in structured Scene dialogue metadata and
is not mirrored into Sound records.

## Schema.org Alignment

- Planned Sound cue: `CreativeWork`
- Music Sound cue: `MusicComposition`
- Linked audio Asset or attachment: `AudioObject`

For the MVP, a music cue carries its composition text directly. A later reusable
composition entity can normalize lyrics shared by multiple cue occurrences.

---

# CPT: Asset

## Fields

- Asset Title
- Asset Type
- Source Workflow
- Prompt
- Model
- Version
- Status
- Storage Location

## Relationships

- Linked To Characters
- Linked To Locations
- Linked To Scenes
- Linked To Storyboards
- Linked From Sounds

---

# CPT: Editorial Artifact

## Fields

- Type
- Export Format
- Version
- Notes

## Supported Types

- EDL
- Timeline Metadata
- XML
- AAF (Future)
- Shot Lists

---

# Taxonomies

## Genre

- Science Fiction
- Fantasy
- Drama
- Documentary
- Horror
- Animation

## Asset Type

- Character
- Prop
- Environment
- Storyboard
- Video
- Audio

## Sound Type

- Narration
- Voice-over
- Music
- Sound Effect
- Ambience
- Foley
- Intentional Silence
- ADR

## Production Status

- Draft
- In Development
- Approved
- Archived

## Character Role

- Protagonist
- Antagonist
- Deuteragonist
- Mentor
- Ally
- Foil
- Love Interest
- Comic Relief
- Ensemble
- Unknown

## Sequence

- Setup
- Rising Action
- Complication
- Midpoint
- Climax
- Resolution

---

# AI Advisor Access Model

All entities should expose structured metadata for:

- Narrative Advisors
- Prompt Advisors
- Production Advisors
- Editorial Advisors
- Technical Advisors

Advisors should retrieve context directly from Story Graph entities.

---

# Script Integration Mapping

## ✅ Celtx Integration (COMPLETE — Phase E)

StoryOS ↔ Celtx bi-directional sync via the `storyos-celtx` WordPress plugin.

### Synced Entities

| StoryOS CPT | Celtx Entity | Sync Direction |
|-------------|--------------|----------------|
| Project | `/project` | Bi-directional |
| Character | `/element` (character) | Bi-directional |
| Location | `/element` (location) | Bi-directional |
| Scene | `/scene` / `/element` | Bi-directional |
| Shot | `/element` (shot) | Bi-directional |

### ID Mapping

Persistent mapping stored in WordPress post meta:
- `storyos_celtx_id` — Celtx element/project ID
- `storyos_celtx_type` — Celtx entity type
- `storyos_synced_at` — Last sync timestamp

### API Endpoints

- `GET /wp-json/storyos-celtx/v1/sync/status`
- `POST /wp-json/storyos-celtx/v1/sync/{entity_type}`
- `GET /wp-json/storyos-celtx/v1/settings`

### Supported Formats (Planned)

Story Graph → Script Formats:

- [ ] Final Draft (.fdx)
- [ ] Fade In
- [ ] Highland
- [ ] Markdown

---

# Editorial Integration Mapping

Story Graph → Editorial Outputs

Supported Targets:

- EDL
- Timeline Metadata
- XML (Future)
- AAF (Future)
- Storyboard

---

# Vocabulary Assumptions

StoryOS aligns with widely used story and film terminology to keep metadata portable across writing, production, and editorial workflows.

## Structural Terms

- Shot: smallest filmed unit
- Scene: dramatic unit composed of one or more shots
- Sequence: dramatic run composed of one or more scenes

Current model coverage:

- Scene and Shot are modeled directly.
- Sequence is modeled as an optional taxonomy attached to Scene records.

## Story Terms

- Protagonist and Antagonist are Character roles.
- Premise and Logline belong to Project-level story metadata.
- Conflict, Stakes, and Turning Points are Scene/Episode annotations.
- Climax and Resolution are milestone tags on key scenes.

## Film Production Terms

- Coverage is captured through shot-level metadata (type, angle, lens, duration).
- Shot List is a view derived from ordered Scene -> Shot relations.
- Continuity is validated from linked entities across Character, Location, Prop, Scene, Shot, Sound, Storyboard, and Asset.
- Storyboard is represented through Storyboard Frame entities and links.
- EDL is represented as an Editorial Artifact derived from Scene/Shot structure.

## Film Grammar Terms

- Take is the recording instance of a shot and should be captured in shot-level production metadata.
- Slate/Clapperboard identifiers should be modeled as optional shot metadata for sync and editorial traceability.
- Establishing, Insert, Cutaway, and Reaction are shot function categories and should map to shot_type values.
- Continuity errors are validation findings generated from graph comparisons, not standalone entities.

## Lifecycle Terms

- Pre-Production covers planning entities and metadata.
- Principal Photography covers capture-oriented shot/scene execution data.
- Post-Production covers editorial artifacts, timeline metadata, and exports.

## Preferred Wording in UI and API

- Use Source for provenance links (for example, Source Scene, Source Shot).
- Use Linked for non-provenance associations (for example, Linked Character).

---

# Design Principle

The Story Graph is the canonical source of truth.

Every script, storyboard, generated asset, production plan, and editorial artifact should be traceable back to structured story entities.
