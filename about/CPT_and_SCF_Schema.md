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

# SCF Persistence and Runtime Contract

StoryOS archives one REST-enabled SCF field group per CPT under
`wordpress/wp-content/plugins/storyos/acf-json/`. These Local JSON files are
committed with the plugin and are the portable field-schema baseline. StoryOS
adds that directory to SCF's JSON load paths and routes saves for
`group_storyos_*` groups back to the same directory; unrelated SCF groups keep
their configured save paths.

At plugin initialization, StoryOS imports any missing archived group into the
WordPress database. The database copy makes the group available in SCF's
Field Groups admin screen, where administrators can add, update, and manage
custom fields. Saving a StoryOS-owned group in SCF refreshes its Local JSON
archive, which should be reviewed and committed like any other schema change.

SCF field groups are the runtime authority for the StoryOS field schema.
StoryOS's PHP field definitions seed the persisted groups, provide a fallback
when SCF is unavailable, and retain StoryOS-only semantics that SCF cannot
express. Runtime consumers use `storyos_get_fields()`, and value reads, writes,
and deletes use SCF's field APIs when a declared field exists. Compatibility
hooks also maintain SCF's hidden field-key reference when legacy code writes
named post meta directly.

Committed core fields use deterministic, per-CPT keys so common field names
remain globally unique:

- Group key: `group_{cpt}`, for example `group_storyos_project`
- Field key: `field_{cpt}_{field_name}`, for example
  `field_storyos_project_status`

Keep these keys stable when changing labels or other field settings. Additions
to the committed core contract use the same per-CPT convention; extension
fields created in SCF retain the stable key generated for them by SCF.

SCF complex fields participate in the wider WordPress and StoryOS models:

- Taxonomy fields load assigned terms, save edited selections back to the
  object, allow term creation, and return term IDs.
- SCF post-object and relationship fields are bridged to named Story Graph
  slots. Saving a control replaces the slot's graph edges, and loading it reads
  the current targets from the Story Graph. Legacy named relationship meta is
  only a fallback until graph relationship metadata exists.
- Scene `dialogue` is an importer-managed SCF repeater with speaker, line,
  description, and sequence values. Its value is read-only in the content edit
  form so manual edits cannot diverge from the imported screenplay structure.

The canonical WordPress post-type keys for Storyboard Frame and Editorial
Artifact are `storyos_storyboard` and `storyos_editorial`. The longer names
`storyos_storyboard_frame` and `storyos_editorial_artifact` are REST-facing
bases and legacy identifiers, not valid current CPT keys.

---

# CPT: Project

## Purpose

Top-level container for all story assets.

## Fields

- `project_name` (text)
- `project_slug` (text)
- `description` (wysiwyg)
- `genre` (taxonomy: `storyos_genre`, multiple)
- `target_medium` (select)
- `status` (taxonomy: `storyos_status`)
- `owner` (user)
- `start_date` (date)
- `end_date` (date)
- `team_members` (relationship to `storyos_character`, multiple)
- `production_stage` (select)
- `frame_width` (number)
- `frame_height` (number)
- `aspect_ratio` (text)
- `frame_rate` (number)

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
- dialogue (structured importer-managed entries: speaker, line, description, sequence)
- location
- time_of_day
- emotional_tone
- production_notes
- sequence

## Relationships

- belongs_to Episode
- contains Shots
- contains Sounds
- references Characters
- references Assets
- references Storyboards

## Celtx Sync Metadata (Phase E — Complete)

When synced with Celtx, the following post meta fields are added:

| Meta Key | Type | Description |
|----------|------|-------------|
| `storyos_celtx_id` | string | Celtx element/project ID |
| `storyos_celtx_type` | string | Celtx entity type (`scene`, `element`, etc.) |
| `storyos_celtx_project_id` | string | Parent Celtx project ID |
| `storyos_synced_at` | datetime | Last successful sync timestamp |
| `storyos_sync_direction` | string | `wordpress_to_celtx`, `celtx_to_wordpress`, `bidirectional` |

---

# CPT: Shot

## Fields

- `shot_name` (text)
- `shot_number` (number)
- `shot_type` (select)
- `camera_angle` (select)
- `lens` (text)
- `duration` (text)
- `take_number` (number)
- `slate_id` (text)
- `shot_description` (wysiwyg)
- `editorial_notes` (wysiwyg)
- `scene` (relationship to `storyos_scene`)
- `sequence` (taxonomy: `storyos_sequence`)

## Relationships

- belongs_to Scene
- linked_from Sounds
- references Storyboard Frames
- references Assets

---

# CPT: Storyboard Frame

Canonical CPT key: `storyos_storyboard`.

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

# CPT: Sound

Represents a planned soundtrack cue. Sound is authorial and production intent;
the recorded or generated file remains a WordPress attachment represented by
an audio-typed `storyos_asset` linked through the `asset` relationship.

Ordinary screenplay dialogue remains structured Scene dialogue metadata and is
not duplicated as Sound records.

## Fields

- sound_type (taxonomy)
- production_status (storyos_status taxonomy)
- spoken_text (textarea; narration, voice-over, or ADR only)
- lyrics (textarea; music cues)
- start_timecode (text)
- duration (text; ISO 8601 preferred)
- diegetic (select: unspecified, diegetic, non_diegetic, internal, mixed)
- production_notes (textarea)

The WordPress title and content are the canonical cue title and description.

## Relationships

- belongs_to Scene (required)
- belongs_to Shot (optional)
- linked_to Character (optional narrator or voice source)
- linked_to Asset (optional rendered audio encoding)

When a Shot is selected, it must belong to the selected Scene. One Sound record
represents one cue occurrence; repeated cues may link to the same Asset.

Schema.org alignment uses `CreativeWork` for a planned Sound and
`MusicComposition` for a music cue. Audio-typed Assets remain `AudioObject`
encodings. The MVP intentionally keeps composition text such as lyrics on the
cue; a reusable composition entity can normalize repeated music works later.

---

# CPT: Asset

## Fields

- `asset_title` (text)
- `asset_type` (taxonomy: `storyos_asset_type`)
- `workflow_name` (text)
- `prompt` (wysiwyg)
- `model_name` (text)
- `seed` (number)
- `generation_parameters` (wysiwyg)
- `version` (text)
- `status` (select)
- `storage_uri` (text)
- `character` (relationship to `storyos_character`)
- `location` (relationship to `storyos_location`)
- `scene` (relationship to `storyos_scene`)
- `storyboard` (relationship to `storyos_storyboard`)

## Relationships

- linked_to Character
- linked_to Location
- linked_to Scene
- linked_to Storyboard
- linked_from Sounds

---

# CPT: Generation Template

The `storyos_template` CPT stores reusable, provider-neutral generation
configuration. It is an editorial configuration record, not an executable
workflow and not a replacement for the StoryOS Assets metabox.

## Fields

- `template_name` (text)
- `description` (wysiwyg)
- `generation_structure` (text)
- `modality` (select)
- `connection_id` (text; a `storyos_connection` post ID)
- `checkpoint` (text)
- `model_family` (select)
- `workflow_json` (textarea)
- `provider_template_id` (text)
- `configuration_json` (textarea)
- `input_bindings` (textarea)
- `model_requirements` (textarea)
- `default_values` (textarea)
- `provider_type` (text)
- `version` (text)
- `status` (select: `draft`, `active`, or `archived`)

`configuration_json` contains the parameter definitions, reference roles, and
SCF field mappings used to resolve a generation request. `default_values` may
provide reusable starting values, but explicit user input takes precedence
after validation.

## Planned Generation Relationship

The Assets workflow is expected to select an active template revision for the
asset-generating Story Graph item. That relationship must preserve the
template identity and revision on the generation record; it must not alter the
featured attachment or `_storyos_asset_gallery_ids` gallery metadata.

After resolution, WordPress should create a normalized request package for the
configured ComfyUI MCP connection. The package includes resolved prompts,
references, parameters, output requirements, Story Graph target, workflow
identity, and provenance. Credentials, raw provider responses, and arbitrary
executable workflow content do not belong in the package.

This relationship and request-package adapter are a remaining Generation Core
epic and are not yet represented as a completed CPT contract.

---

# CPT: Editorial Artifact

Canonical CPT key: `storyos_editorial`.

## Fields

- `artifact_type` (select)
- `export_format` (text)
- `generated_date` (date)
- `source_scene` (relationship to `storyos_scene`)
- `source_shot` (relationship to `storyos_shot`)
- `notes` (wysiwyg)
- `project` (relationship to `storyos_project`)

## Artifact Types

- EDL
- XML
- AAF
- Timeline Metadata
- Production Reports

---

# CPT: Connection

The `storyos_connection` CPT is a control-plane record for a configured
provider endpoint. It stores credential references, never raw secret values.
Templates and generation jobs select a Connection by post ID; this association
is currently stored as configuration rather than as a Story Graph edge.

## Fields

- `connection_name` (text)
- `provider_type` (select)
- `environment` (select)
- `status` (select)
- `endpoint_url` (text)
- `mcp_endpoint_url` (text)
- `credential_reference` (text)
- `model` (text)
- `max_tokens` (text)
- `temperature` (text)
- `model_access` (textarea containing JSON)
- `enabled_structures` (textarea containing JSON)
- `enabled_templates` (textarea containing JSON)
- `rate_limits` (textarea containing JSON)
- `cost_controls` (textarea containing JSON)

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

## Sound Type

- Narration
- Voice-over
- Music
- Sound Effect
- Ambience
- Foley
- Intentional Silence
- ADR

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
Sound -> Scene
Sound -> Shot
Sound -> Character
Sound -> Asset
Scene -> Character
Scene -> Location
Shot -> Storyboard Frame
Storyboard Frame -> Asset
Asset -> Character
Asset -> Location
Editorial Artifact -> Scene
Editorial Artifact -> Shot
Editorial Artifact -> Project
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
- Continuity: represented by graph relationships across Scene, Shot, Sound, Asset, Character, and Location
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
- Sound
- Asset
- Storyboard Frame

Future entities can be added without modifying the core Story Graph model.
