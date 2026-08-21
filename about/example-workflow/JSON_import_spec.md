# Little Red Riding Hood Import Specification

## Purpose

This document defines how `little-red-riding-hood.worldgraph.json` is imported into World Graph Studio CPTs, SCF fields, relationships, and Story Graph entities.

This specification is intended to make importer implementation deterministic and testable.

The `sounds[]` section was added in World Graph Studio JSON 1.1. Writers should emit the
section (using an empty array when there are no cues); readers treat a missing
section as an empty array so version 1.0 documents remain compatible.

---

# Import Workflow

```text
World Graph Studio JSON
      ↓
JSON Validation
      ↓
CPT Creation
      ↓
SCF Population
      ↓
Relationship Creation
      ↓
Story Graph Construction
      ↓
Verification
```

---

# CPT Mapping

## Project

### JSON

```text
project
```

### CPT

```text
worldgraph_project
```

### Field Mapping

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| title | post_title |
| description | description |

### Acceptance Criteria

- One Project CPT is created.
- Project title matches source JSON.

---

# World

### JSON

```text
world
```

### CPT

```text
worldgraph_world
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |
| description | synopsis |

### Relationships

```text
Project → World
```

---

# Characters

### JSON

```text
characters[]
```

### CPT

```text
worldgraph_character
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |
| archetype | archetype |
| description | biography |

### Relationships

```text
World → Character
```

### Expected Records

- Little Red Riding Hood
- Grandmother
- Wolf

---

# Locations

### JSON

```text
locations[]
```

### CPT

```text
worldgraph_location
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |
| description | description |

### Relationships

```text
World → Location
```

---

# Props

### JSON

```text
props[]
```

### CPT

```text
worldgraph_prop
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |
| description | description |
| owner_character | owner_character |

### Relationships

```text
World → Prop
Prop → Owner Character
```

---

# Scenes

### JSON

```text
scenes[]
```

Each Scene may include `script_content` in addition to its summary. Importers
store that value in the Scene's canonical script field without replacing the
summary.

### CPT

```text
worldgraph_scene
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| title | post_title |
| summary | summary |
| dialogue | dialogue (structured, read-only through the generic REST field writer) |
| location | Location relationship |

### Relationships

```text
Scene → Characters
Scene → Props
Scene → Location
```

### Dialogue Import

Each dialogue record should be imported into structured scene dialogue metadata.
Ordinary dialogue remains canonical here and must not be duplicated as Sound
records.

Stored schema (the JSON `text` property is normalized to `line`):

```text
speaker
line
description
sequence
```

---

# Shots

### JSON

```text
shots[]
```

### CPT

```text
worldgraph_shot
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| scene | scene (required relationship; external Scene ID) |
| type | shot_type |
| description | shot_description |

### Relationships

```text
Shot → Scene (belongs_to, required `scene` field)
```

### Expected Count

```text
9 Shots
```

---

# Sounds

### JSON

```text
sounds[]
```

### CPT

```text
worldgraph_sound
```

Each record is a planned soundtrack cue. The cue links to an audio-typed
`worldgraph_asset`, which can represent a rendered file or WordPress attachment;
the Sound record itself is not the media encoding.

### Required Fields

- `id`
- `title`
- `type`
- `scene`

### Field Mapping

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| title | post_title |
| description | post_content |
| type | worldgraph_sound_type taxonomy |
| production_status | worldgraph_status taxonomy (optional, existing term) |
| spoken_text | spoken_text |
| lyrics | lyrics |
| start_timecode | start_timecode |
| duration | duration |
| diegetic | diegetic |
| production_notes | production_notes |

Seeded `type` slugs are `narration`, `voiceover`, `music`, `sound-effect`,
`ambience`, `foley`, `silence`, and `adr`. The taxonomy remains extensible.
`spoken_text` is for narration, voice-over, or ADR; it does not replace
`scenes[].dialogue`. Music cues may carry multiline `lyrics`.

### Relationships

```text
Sound → Scene (belongs_to, required)
Sound → Shot (belongs_to, optional)
Sound → Character (linked_to, optional narrator/voice source)
Sound → Asset (linked_to, optional rendered audio)
```

Project membership is derived through the required Scene relationship; Sound
does not store a second direct Project edge.

When `shot` is present, it must belong to the referenced `scene`. An `asset`
external ID must already resolve to an audio-typed `worldgraph_asset`; the sample
does not include one because the current JSON format has no top-level asset import.

### Expected Count

```text
7 Sounds
```

---

# Storyboard Frames

### JSON

```text
storyboards[]
```

### CPT

```text
worldgraph_board
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| description | frame_description |

### Relationships

```text
Shot → Storyboard Frame
```

### Expected Count

```text
9 Storyboard Frames
```

---

# Sequence

### JSON

```text
sequence
```

### CPT

```text
worldgraph_sequence
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| title | post_title |

### Relationships

Sequence order should be preserved.

```text
Scene 1
 ↓
Scene 2
 ↓
Scene 3
```

---

# Story Graph Relationships

## Character Appearances

```text
Little Red → Scene 1
Little Red → Scene 2
Little Red → Scene 3

Grandmother → Scene 1
Grandmother → Scene 3

Wolf → Scene 2
Wolf → Scene 3
```

## Prop Usage

```text
Basket → Scene 1
Basket → Scene 2

Red Cloak → Scene 1

Bed → Scene 3
```

## Prop Ownership

```text
Red Cloak → Little Red
Basket → Little Red
Bed → Grandmother
```

## Location Usage

```text
Village Cottage → Scene 1
Forest Path → Scene 2
Grandmother House → Scene 3
```

## Sound Placement

```text
Opening Narration → Scene 1 / Shot 1
Red Remembers Her Promise → Scene 2 / Shot 4 / Little Red
Stay to the Path → Scene 2 / Shot 4
Forest Path Ambience → Scene 2
Wolf Approaches Through Leaves → Scene 2 / Shot 5
Wolf Reveal Sting → Scene 3 / Shot 9
Silence Before the Reveal → Scene 3 / Shot 8
```

---

# AI Analysis Tasks Triggered After Import

## Story Graph Analyst

Must:

- Validate entity relationships
- Verify scene structure
- Classify archetypes
- Generate initial graph insights

## Production Designer

Must:

- Suggest visual style
- Suggest costume references
- Suggest prop references
- Suggest environment design

## Cinematography Advisor

Must:

- Suggest coverage
- Suggest shot improvements
- Suggest camera language

---

# Import Verification Checklist

## Expected Totals

```text
Projects:           1
Worlds:             1
Characters:         3
Locations:          3
Props:              3
Scenes:             3
Shots:              9
Sounds:             7
Storyboard Frames:  9
Sequences:          1
```

## Import Passes When

- [ ] All CPTs created
- [ ] All relationships created
- [ ] Dialogue imported
- [ ] Sound cues imported without duplicating Scene dialogue
- [ ] Sound Scene/Shot references and music lyrics preserved
- [ ] Sequence ordering preserved
- [ ] Story Graph relationships generated
- [ ] AI analysis tasks available
- [ ] No orphaned entities exist

---

# MVP Goal

Importing this sample project should create a complete miniature World Graph Studio project in one action and provide a fully-functional demonstration of Story Graph creation, AI-assisted analysis, generation preparation, storyboard planning, sequencing, and script export.
