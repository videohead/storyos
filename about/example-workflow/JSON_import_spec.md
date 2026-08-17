# Little Red Riding Hood Import Specification

## Purpose

This document defines how `little-red-riding-hood.storyos.json` is imported into StoryOS CPTs, SCF fields, relationships, and Story Graph entities.

This specification is intended to make importer implementation deterministic and testable.

---

# Import Workflow

```text
StoryOS JSON
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
storyos_project
```

### Field Mapping

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| title | post_title |
| description | project_description |

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
storyos_world
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |
| description | world_description |

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
storyos_character
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
storyos_location
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |
| description | location_description |

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
storyos_prop
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| name | post_title |

### Relationships

```text
World → Prop
```

---

# Scenes

### JSON

```text
scenes[]
```

### CPT

```text
storyos_scene
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| title | post_title |
| summary | scene_summary |
| location | scene_location |

### Relationships

```text
Scene → Characters
Scene → Props
Scene → Location
```

### Dialogue Import

Each dialogue record should be imported into structured scene dialogue metadata.

Suggested schema:

```text
speaker
line
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
storyos_shot
```

### Fields

| JSON Field | CPT Field |
|------------|------------|
| id | external_id |
| type | shot_type |
| description | shot_description |

### Relationships

```text
Scene → Shot
```

### Expected Count

```text
9 Shots
```

---

# Storyboard Frames

### JSON

```text
storyboards[]
```

### CPT

```text
storyos_storyboard_frame
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
storyos_sequence
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

## Location Usage

```text
Village Cottage → Scene 1
Forest Path → Scene 2
Grandmother House → Scene 3
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
Storyboard Frames:  9
Sequences:          1
```

## Import Passes When

- [ ] All CPTs created
- [ ] All relationships created
- [ ] Dialogue imported
- [ ] Sequence ordering preserved
- [ ] Story Graph relationships generated
- [ ] AI analysis tasks available
- [ ] No orphaned entities exist

---

# MVP Goal

Importing this sample project should create a complete miniature StoryOS project in one action and provide a fully-functional demonstration of Story Graph creation, AI-assisted analysis, generation preparation, storyboard planning, sequencing, and script export.
