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
- Location
- Time Of Day
- Characters
- Notes

## Relationships

- Belongs To Episode
- Contains Shots
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

## Production Status

- Draft
- In Development
- Approved
- Archived

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

Story Graph → Script Formats

Supported Targets:

- Final Draft
- Fountain
- Celtx
- Fade In
- Highland
- Markdown

---

# Editorial Integration Mapping

Story Graph → Editorial Outputs

Supported Targets:

- EDL
- Timeline Metadata
- XML (Future)
- AAF (Future)

---

# Design Principle

The Story Graph is the canonical source of truth.

Every script, storyboard, generated asset, production plan, and editorial artifact should be traceable back to structured story entities.
