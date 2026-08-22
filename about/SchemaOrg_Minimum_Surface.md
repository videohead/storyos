# World Graph Studio Minimum Non-Destructive Schema Surface

Date: 2026-08-08

## Intent

Align existing World Graph Studio fields to closest Schema.org properties without renaming, deleting, or migrating existing metadata.

## Minimum Surface Rules

1. Keep all current CPT field names as-is.
2. Use alias mapping to resolve each field to the closest Schema.org property.
3. Prioritize exact and close matches; leave weak matches intact but flagged.
4. Expose inferred schema hints through REST responses for downstream graph consumers.

## CPT Type Alignment

- `worldgraph_project` -> `CreativeWork` (runtime upgrade to `Movie` for `film`/`short_film`)
- `worldgraph_episode` -> `Episode`
- `worldgraph_scene` -> `Clip`
- `worldgraph_shot` -> `Clip`
- `worldgraph_sound` -> `CreativeWork` (runtime upgrade to `MusicComposition` for music cues)
- `worldgraph_character` -> `Person`
- `worldgraph_location` -> `Place`
- `worldgraph_org` -> `Organization`
- `worldgraph_asset` -> `MediaObject`

Runtime asset subtype inference:

- `VideoObject` for video assets
- `AudioObject` for audio assets
- `ImageObject` for character/environment/prop/storyboard/lookbook/concept-art assets

Planned Sound cues remain creative works; only linked audio Assets representing
their attachments are `AudioObject` encodings.

## Closest Similarity Snapshot

Counts reflect current mapping in code (`exact`, `close`, `weak`):

- `worldgraph_project`: 3, 7, 1
- `worldgraph_world`: 1, 6, 1
- `worldgraph_character`: 1, 3, 6
- `worldgraph_location`: 2, 3, 1
- `worldgraph_prop`: 2, 2, 1
- `worldgraph_org`: 2, 2, 2
- `worldgraph_episode`: 3, 2, 0
- `worldgraph_scene`: 3, 3, 4
- `worldgraph_shot`: 3, 2, 3
- `worldgraph_sound`: 3, 6, 3
- `worldgraph_asset`: 2, 8, 4
- `worldgraph_editorial`: 1, 6, 0

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

- `worldgraph_schema_type_map()`
- `worldgraph_schema_type_for_entity()`
- `worldgraph_schema_field_map()`
- `worldgraph_schema_property_for_field()`
- `worldgraph_schema_hints_from_meta()`
- `worldgraph_schema_property_for_relationship()`
- `worldgraph_schema_similarity_summary()`

This provides interoperability consistency now, while deferring deeper schema normalization or JSON-LD publication.
