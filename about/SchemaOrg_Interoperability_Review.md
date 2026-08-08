# StoryOS Schema.org Interoperability Review (Knowledge Graph Focus)

Date: 2026-08-08

## Goal

Use Schema.org as the canonical interoperability vocabulary for StoryOS content types and relationships, with focus on:

- Knowledge graph alignment
- Cross-system portability
- Avoiding custom ontology reinvention for common storytelling concepts

Out of scope for now:

- JSON-LD publishing/rendering concerns
- Search rich-result optimization

## Key Direction

StoryOS should treat existing Schema.org classes and properties as the default semantic model, and only use StoryOS-specific fields where no practical Schema.org property exists.

## Canonical Type Mapping (Current CPT -> Schema.org)

### Core Content

- `storyos_project` -> `Movie` (for film/short film targets), else `CreativeWork`
- `storyos_episode` -> `Episode`
- `storyos_scene` -> `Clip` (preferred) or `CreativeWork` when clip semantics are not available
- `storyos_shot` -> `Clip` (child clip or segment)

### Narrative Entities

- `storyos_character` -> `Person` (Schema.org allows fictional persons)
- `storyos_location` -> `Place`
- `storyos_organization` -> `Organization`
- `storyos_prop` -> `Thing` (optionally `Product` if production inventory workflows emerge)

### Media/Artifacts

- `storyos_asset` -> `MediaObject` base with subtyping:
  - image assets -> `ImageObject`
  - video assets -> `VideoObject`
  - audio assets -> `AudioObject`
- `storyos_storyboard_frame` -> `ImageObject` or `CreativeWork` (depending on whether the frame is treated as media-first or annotation-first)
- `storyos_editorial_artifact` -> `CreativeWork`

## Relationship Mapping (Story Graph -> Schema.org semantics)

StoryOS relationship verbs can remain internal, but each should have a deterministic Schema.org mapping:

- `contains` -> `hasPart`
- `belongs_to` -> `isPartOf`
- `appears_in` -> `actor` (for people in moving-image works) or `character` (for fictional character links from work)
- `located_in` -> `contentLocation`
- `references` -> `mentions`
- `related_to` -> `isRelatedTo`
- `linked_to` -> `subjectOf` or `about` (choose by direction)
- `derived_from` -> `isBasedOn`
- `used_in` -> `isPartOf` or `about` (context dependent)
- `generated_by` -> `producer` / `creator` (if agent known) or provenance note in `isBasedOn`

## Required Interop Field Set by Type

### Movie / CreativeWork (`storyos_project`)

Must support these Schema.org properties at minimum:

- `name`
- `description`
- `genre`
- `inLanguage`
- `keywords`
- `creativeWorkStatus`
- `dateCreated`
- `dateModified`
- `isPartOf` / `hasPart`

Movie-specific additions:

- `actor`
- `director`
- `productionCompany`
- `duration`
- `countryOfOrigin`
- `contentRating`
- `trailer` (link to `VideoObject`)

### Episode (`storyos_episode`)

- `name`
- `description`
- `episodeNumber`
- `isPartOf`
- `hasPart`
- `actor`, `director`, `productionCompany` (optional but supported)

### Scene / Shot (`storyos_scene`, `storyos_shot`)

- `name`
- `description`
- `position`
- `isPartOf`
- `hasPart`
- `contentLocation`
- `duration` (for shot-level timing)

### Character (`storyos_character`)

- `name`
- `description`
- `image`
- `sameAs` (optional external identity links)

### Location (`storyos_location`)

- `name`
- `description`
- `address` or textual geography
- `geo` (optional when available)

### Organization (`storyos_organization`)

- `name`
- `description`
- `member`
- `parentOrganization` / `subOrganization` where relevant

### Asset (`storyos_asset` as MediaObject)

- `name`
- `description`
- `encodingFormat`
- `contentUrl`
- `creator` / `producer`
- `dateCreated`
- `isBasedOn` (prompt or source lineage)

## Problems Identified in Current StoryOS Model (Interoperability Impact)

1. WordPress core fields (`post_title`, `post_content`) are duplicated by custom fields (`project_name`, `asset_title`, `display_name`) causing semantic drift.
2. Relationship verbs are internal-only and not mapped to Schema.org semantics at the model level.
3. CPT fields do not consistently represent canonical Schema.org properties needed for graph exchange (`inLanguage`, `contentUrl`, `encodingFormat`, `position`, etc.).
4. SCF is declared as dependency, but field lifecycle currently behaves like an internal registry rather than a schema-contract system.
5. Existing tests validate structure only and do not verify schema-level assumptions.

## Recommended Revisions (No Wheel Reinvention)

### 1) Adopt canonical aliases instead of replacing all existing fields

Create a mapping layer where StoryOS fields resolve to canonical Schema.org property names:

- `project_name` -> `name`
- `synopsis`/`summary` -> `description`
- `world_name`, `location_name`, `organization_name`, `display_name` -> `name`
- `storage_uri` -> `contentUrl`
- `model_name` remains implementation metadata (not schema primary)

This avoids destructive migrations while enabling semantic interoperability.

### 2) Add missing schema-critical fields to CPT definitions

Start with:

- project: `inLanguage`, `keywords`, `country_of_origin`, `content_rating`, `duration`
- asset: `content_url`, `encoding_format`, `caption`
- scene/shot: `position`, `duration` (ISO 8601 preferred)
- organization: `legal_name` (optional), `same_as`
- character: `same_as`, `image`

### 3) Make hierarchy first-class

Ensure project -> episode -> scene -> shot is always representable as:

- parent has `hasPart`
- child has `isPartOf`

### 4) Reserve custom StoryOS fields for production-only concepts

Keep StoryOS-only fields when they are operational (e.g., generation seed, workflow internal IDs), but do not use them as replacements for existing Schema.org semantics.

### 5) Add schema-contract tests

Introduce tests that validate:

- every CPT has a declared Schema.org base type
- required core properties for that type are representable by field or alias
- relationship verbs have deterministic Schema.org mappings

## Suggested Implementation Sequence

1. Define and commit a CPT-to-Schema.org mapping config.
2. Add field alias resolver (legacy field -> canonical property).
3. Add missing interoperability fields to CPT definitions.
4. Add relationship semantic mapping table.
5. Add schema contract tests in plugin test suite.

## Success Criteria

StoryOS is considered interoperability-ready when:

- Every CPT has an explicit Schema.org type assignment.
- Every core entity can be losslessly represented using Schema.org property names.
- Internal relationship verbs map deterministically to Schema.org relationship properties.
- No major entity depends on StoryOS-only names for fundamental semantics like name, description, containment, people, place, or media identity.