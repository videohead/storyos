# StoryOS Story Graph Specification v1.0

> Build Your Story Once. Create Everywhere.

## Purpose

The Story Graph is the core architectural component of StoryOS.

It provides a structured, interconnected representation of narrative, production, asset, and editorial information. Rather than treating a story as a collection of documents, StoryOS treats a story as a living graph of related entities.

The Story Graph serves as the canonical source of truth for all StoryOS workflows.

---

# Design Goals

## Narrative Continuity

Preserve relationships between story elements throughout the project lifecycle.

## Reusability

Allow data entered once to be reused across writing, storyboarding, generation, production, and editing.

## AI Accessibility

Enable advisors and agent workflows to access contextual project knowledge.

## Traceability

Every generated artifact should be traceable back to originating story entities.

## Extensibility

Support future entity types, workflows, integrations, and graph technologies.

---

# Canonical Principle

The Story Graph is the source of truth.

The following are considered generated views of graph data:

- Scripts
- Storyboards
- Lookbooks
- Shot Lists
- Production Plans
- Schedules
- Call Sheets
- EDL Files
- Editorial Metadata
- AI Assets

---

# Core Entity Graph

Project
├── Story World
│   ├── Characters
│   ├── Locations
│   └── Organizations
│
├── Episodes
│   └── Scenes
│       ├── Shots
│       └── Sounds
│
├── Storyboards
├── Sounds
├── Assets
└── Editorial Artifacts

---

# Relationship Types

## Contains

Parent-child ownership.

Examples:

- Project contains Episodes
- Project contains Sounds
- Episode contains Scenes
- Scene contains Shots

## Appears In

Used for narrative participation.

Examples:

- Character appears in Scene
- Prop appears in Shot

## Located In

Represents geographic or environmental placement.

Examples:

- Scene located in Location
- Organization located in Location

## References

Associates supporting information.

Examples:

- Storyboard references Character
- Asset references Scene

## Derived From

Tracks provenance.

Examples:

- Asset derived from Scene
- EDL derived from Shot List

---

# Character Graph

Character
│
├── Relationships
├── Scenes
├── Assets
├── Dialogue
├── Sounds
├── Storyboards
└── Locations

Character nodes become major continuity anchors.

---

# Scene Graph

Scene
│
├── Characters
├── Locations
├── Props
├── Shots
├── Sounds
├── Storyboards
├── Assets
└── Editorial References

Scenes act as the primary narrative aggregation point.

---

# Sound Graph

Sound
│
├── Sound Type
├── Scene
├── Shot (Optional)
├── Narrator / Voice Character (Optional)
├── Spoken Text or Lyrics
├── Timing and Diegesis
└── Rendered Audio Asset (Optional)

A Sound is a planned cue, while an audio-typed Asset or WordPress attachment is
the rendered encoding. Ordinary screenplay dialogue remains structured Scene
metadata. This separation supports narration, music, effects, ambience, Foley,
and intentional silence without duplicating dialogue or media files.

---

# Asset Graph

Asset
│
├── Source Scene
├── Source Character
├── Prompt
├── Workflow
├── Model
├── Version
└── Storage Location

Assets must maintain lineage information.

---

# Production Graph

Production entities are connected to story entities.

Examples:

Scene
→ Shot List
→ Production Schedule
→ Call Sheet
→ Shoot Day

This enables production planning directly from story structure.

---

# Editorial Graph

Scene
→ Shot
→ Timeline Segment
→ EDL
→ Editorial Metadata

Editorial artifacts remain linked to source story elements.

---

# AI Retrieval Model

Agents retrieve context through graph traversal.

Example Query:

Character → Scenes → Sounds / Storyboards → Assets

This allows advisors to access relevant project knowledge without requiring full-project context.

---

# Continuity Engine

Future StoryOS releases should support automated continuity validation.

Potential checks:

- Character consistency
- Location consistency
- Prop continuity
- Relationship continuity
- Story arc tracking
- Asset consistency
- Sound placement and cue consistency

---

# Semantic Search

The graph should support semantic discovery of related information.

Examples:

- Find all scenes involving a specific character.
- Find all assets related to a location.
- Find all shots derived from a storyboard.
- Find all editorial artifacts associated with an episode.

---

# Graph Traversal Examples

## Narrative Query

Character
→ Scene
→ Episode
→ Story Arc

## Production Query

Scene
→ Shot
→ Schedule
→ Shoot Day

## Asset Query

Character
→ Asset
→ Workflow
→ Prompt

## Editorial Query

Episode
→ Scene
→ Shot
→ EDL

---

# Future Enhancements

## Graph Database Support

Potential future support:

- Neo4j
- ArangoDB
- RDF Stores
- WordPress-native graph abstractions

## Analytics

- Character importance scoring
- Narrative density analysis
- Scene dependency analysis
- Production complexity scoring

## Story Intelligence

- Continuity reasoning
- Narrative recommendations
- Story gap analysis
- Dependency visualization

---

# Strategic Importance

The Story Graph is the long-term differentiator of StoryOS.

AI models, image generators, video generators, and external tools will evolve rapidly.

The structured representation of story knowledge remains the enduring asset.

By treating stories as connected data, StoryOS can support the entire lifecycle of creative development, production planning, asset generation, and editorial workflows.
