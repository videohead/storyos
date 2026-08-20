# StoryOS Minimum Non-Destructive Schema Surface

Date: 2026-08-08

## Intent

Align existing StoryOS fields to closest Schema.org properties without renaming, deleting, or migrating existing metadata.

## Minimum Surface Rules

1. Keep all current CPT field names as-is.
2. Use alias mapping to resolve each field to the closest Schema.org property.
3. Prioritize exact and close matches; leave weak matches intact but flagged.
4. Expose inferred schema hints through REST responses for downstream graph consumers.

## CPT Type Alignment

- `storyos_project` -> `CreativeWork` (runtime upgrade to `Movie` for `film`/`short_film`)
- `storyos_episode` -> `Episode`
- `storyos_scene` -> `Clip`
- `storyos_shot` -> `Clip`
- `storyos_sound` -> `CreativeWork` (runtime upgrade to `MusicComposition` for music cues)
- `storyos_character` -> `Person`
- `storyos_location` -> `Place`
- `storyos_organization` -> `Organization`
- `storyos_asset` -> `MediaObject`

Runtime asset subtype inference:

- `VideoObject` for video assets
- `AudioObject` for audio assets
- `ImageObject` for character/environment/prop/storyboard/lookbook/concept-art assets

Planned Sound cues remain creative works; only linked audio Assets representing
their attachments are `AudioObject` encodings.

## Closest Similarity Snapshot

Counts reflect current mapping in code (`exact`, `close`, `weak`):

- `storyos_project`: 3, 7, 1
- `storyos_story_world`: 1, 6, 1
- `storyos_character`: 1, 3, 6
- `storyos_location`: 2, 3, 1
- `storyos_prop`: 2, 2, 1
- `storyos_organization`: 2, 2, 2
- `storyos_episode`: 3, 2, 0
- `storyos_scene`: 3, 3, 4
- `storyos_shot`: 3, 2, 3
- `storyos_sound`: 3, 6, 3
- `storyos_storyboard_frame`: 1, 5, 1
- `storyos_asset`: 2, 8, 4
- `storyos_editorial_artifact`: 1, 6, 0

## High-Value Similarities (No Migration Needed)

- `project_name`, `display_name`, `location_name`, `organization_name`, `asset_title` -> `name`
- `description`/`synopsis`/`summary`/`shot_description` -> `description`
- `episode_number` -> `episodeNumber`
- `duration` (shot) -> `duration`
- `spoken_text` -> `text`; music `lyrics` -> a `lyrics` CreativeWork; flexible sound `duration` -> `duration` (close)
- `version` (asset) -> `version`
- `storage_uri` -> `contentUrl` (close)
- `project`, `episode`, `scene` relationship fields -> `isPartOf` (exact/close)

## Weak Similarity Areas to Watch

- Character psychology fields (`motivation`, `personality`, `voice_profile`) currently collapse to generic descriptive properties.
- Production-specific knobs (`seed`, `workflow_name`, `model_name`) map only weakly to global schema terms.
- Editorial and camera note fields map as generic `text`/`description`.

## Storytelling Relationship Semantics

Relationship output now maps to closest Schema.org properties:

- `contains` -> `hasPart`
- `belongs_to` -> `isPartOf`
- `references` -> `mentions`
- `derived_from` -> `isBasedOn`
- `located_in` -> `contentLocation`
- `linked_to` -> context-aware (`character`, `contentLocation`, `isPartOf`, or `about`)
- `appears_in` -> context-aware (`subjectOf` from `Person`, `character` from `CreativeWork`)

## Minimal Next Step

Use the new helper utilities to make schema assumptions explicit in API and tooling:

- `storyos_schema_type_map()`
- `storyos_schema_type_for_entity()`
- `storyos_schema_field_map()`
- `storyos_schema_property_for_field()`
- `storyos_schema_hints_from_meta()`
- `storyos_schema_property_for_relationship()`
- `storyos_schema_similarity_summary()`

This provides interoperability consistency now, while deferring deeper schema normalization or JSON-LD publication.
