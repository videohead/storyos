# StoryOS CPT and SCF Schema Specification v1.0

> Build Your Story Once. Create Everywhere.

## Purpose

This document defines the WordPress Custom Post Types (CPTs), Structured Content Fields (SCF), taxonomies, and relationships used to implement the StoryOS Story Graph.

The schema serves as the foundation for:

- Story development
- AI advisor context retrieval
- ComfyUI asset generation
- Production planning
- Script integration
- Editorial workflows

---

# Architecture Principle

```text
WordPress
    ↓
Custom Post Types
    ↓
Structured Content Fields
    ↓
Story Graph Relationships
    ↓
StoryOS Services
```

The Story Graph is the canonical source of truth.

---

# CPT: Project

## Purpose

Top-level container for all story assets.

## Fields

- project_name (text)
- project_slug (slug)
- description (wysiwyg)
- genre (taxonomy)
- target_medium (select)
- status (select)
- owner (user)
- start_date (date)
- team_members (relationship)

## Relationships

- has_many Story Worlds
- has_many Episodes
- has_many Assets

---

# CPT: Story World

## Fields

- world_name
- synopsis
- timeline
- rules
- themes
- geography
- references

## Relationships

- belongs_to Project
- has_many Characters
- has_many Locations
- has_many Organizations

---

# CPT: Character

## Fields

- display_name
- biography
- age
- appearance
- personality
- motivation
- backstory
- voice_profile
- avatar_asset

## Taxonomies

- storyos_character_role

## Relationships

- belongs_to Story World
- appears_in Scenes
- linked_to Assets
- related_to Characters

---

# CPT: Location

## Fields

- location_name
- description
- environment_type
- geography
- mood
- visual_reference

## Relationships

- belongs_to Story World
- used_in Scenes
- linked_to Assets

---

# CPT: Prop

## Fields

- prop_name
- description
- purpose
- owner_character
- notes

## Relationships

- appears_in Scenes
- linked_to Assets

---

# CPT: Organization

## Fields

- organization_name
- organization_type
- description
- leadership
- goals

## Relationships

- belongs_to Story World
- contains Characters

---

# CPT: Episode

## Fields

- episode_number
- title
- synopsis
- status

## Relationships

- belongs_to Project
- contains Scenes

---

# CPT: Scene

## Fields

- scene_number
- title
- summary
- script_content
- location
- time_of_day
- emotional_tone
- production_notes
- sequence

## Relationships

- belongs_to Episode
- contains Shots
- references Characters
- references Assets
- references Storyboards

---

# CPT: Shot

## Fields

- shot_number
- shot_type
- camera_angle
- lens
- duration
- shot_description
- editorial_notes

## Relationships

- belongs_to Scene
- references Storyboard Frames
- references Assets

---

# CPT: Storyboard Frame

## Fields

- frame_number
- frame_description
- image_asset
- prompt_text
- camera_notes

## Relationships

- belongs_to Scene
- belongs_to Shot

---

# CPT: Asset

## Fields

- asset_title
- asset_type
- workflow_name
- prompt
- model_name
- seed
- generation_parameters
- version
- storage_uri

## Relationships

- linked_to Character
- linked_to Location
- linked_to Scene
- linked_to Storyboard

---

# CPT: Editorial Artifact

## Fields

- artifact_type
- export_format
- generated_date
- source_scene
- source_shot
- notes

## Artifact Types

- EDL
- XML
- AAF
- Timeline Metadata
- Production Reports

---

# Global Taxonomies

## Genre

- Drama
- Comedy
- Sci-Fi
- Fantasy
- Horror
- Documentary
- Animation

## Project Status

- Draft
- Development
- Production
- Post Production
- Published
- Archived

## Asset Type

- Character
- Environment
- Prop
- Storyboard
- Video
- Audio

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

# Relationship Table

```text
Project -> Story World
Project -> Episode
Episode -> Scene
Scene -> Shot
Scene -> Character
Scene -> Location
Shot -> Storyboard Frame
Storyboard Frame -> Asset
Asset -> Character
Asset -> Location
Editorial Artifact -> Scene
Editorial Artifact -> Shot
```

---

# AI Advisor Access Requirements

All fields should be exposed through StoryOS APIs.

Agents must be able to:

- Query entities
- Traverse relationships
- Retrieve metadata
- Store recommendations
- Create assets and production artifacts

---

# Vocabulary Alignment (Story Science + StudioBinder)

To reduce ambiguity, StoryOS uses a controlled vocabulary that aligns with common story and film terminology.

## Core Hierarchy

- Shot: a single camera setup/take unit
- Scene: a dramatic unit made from one or more shots
- Sequence: a larger dramatic movement made from one or more scenes

Implementation note:

- StoryOS models Shot and Scene as first-class entities.
- Sequence is currently implemented as an optional Scene taxonomy (`storyos_sequence`).

## Canonical Narrative Terms

- Protagonist: model as a Character role/tag, not a separate CPT
- Antagonist: model as a Character role/tag, not a separate CPT
- Stakes: capture in Scene or Episode notes/metadata
- Conflict: capture in Scene summary/notes and relationship metadata
- Climax and Resolution (Denouement): capture as tagged Scene milestones
- Premise and Logline: capture at Project level metadata

## Canonical Production Terms

- Shot List: represented by ordered Shot entities per Scene
- Coverage: represented by Shot variants (shot_type, angle, lens, duration)
- Continuity: represented by graph relationships across Scene, Shot, Asset, Character, and Location
- Storyboard: represented by Storyboard Frame entities linked to Scene/Shot
- EDL: represented as Editorial Artifact with links to source Scene/Shot

## Film Grammar Terms

- Take: a single uninterrupted recording instance of a shot
- Clapperboard/Slate: production marker identifying scene and take for sync and tracking
- Establishing Shot: a context-setting shot, represented in StoryOS by shot_type and scene metadata
- Insert/Cutaway/Reaction Shot: shot function categories, represented in shot_type taxonomy/enum values
- Continuity Error: a validation outcome for continuity checks, not a first-class entity

## Production Lifecycle Terms

- Pre-Production: planning phase (scripts, storyboards, shot planning)
- Principal Photography: active scene/shot capture phase
- Post-Production: editorial/finishing phase (EDL, timeline, exports)
- Daily Call Sheet: production schedule artifact (future Production entity)
- Dailies: raw daily review media (future Asset sub-type)

## Field-to-Vocabulary Crosswalk

- scene_number -> Scene
- sequence -> Sequence
- shot_number -> Shot
- shot_description -> Coverage notes
- prompt_text -> Storyboard prompt
- prompt -> Asset generation prompt
- source_scene/source_shot (editorial) -> provenance lineage
- linked_to / derived_from relationships -> association vs provenance semantics
- status (asset/editorial) -> lifecycle state across production phases

## Naming Rule

When a relationship implies provenance, prefer Source wording in UI labels and docs.
When a relationship implies generic association, keep Linked wording.

---

# MVP Schema

Required for initial release:

- Project
- Character
- Location
- Scene
- Shot
- Asset
- Storyboard Frame

Future entities can be added without modifying the core Story Graph model.
