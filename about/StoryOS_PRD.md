StoryOS Product Requirements Document (PRD) v1.1

## Executive Summary

StoryOS is an open-source storytelling operating system built around a Story Graph in WordPress, helpful filmmaking agents exposed through the WordPress Abilities API, and optional generative workflows through ComfyUI and MCP.

The product is designed to support the full narrative lifecycle: story development, continuity tracking, worldbuilding, AI-assisted review, asset generation, production planning, and editorial handoff from a single source of truth.

## Vision

Build your story once. Create everywhere.

## Goals

- Preserve story structure as connected, queryable data
- Support creative and production planning from the same project model
- Provide AI assistance without separating the narrative from its metadata
- Enable generation workflows through ComfyUI or Comfy Cloud when needed
- Support script, editorial, and asset workflows without forcing a single toolchain

## Product Scope

StoryOS is not limited to visual generation. The core product is a structured storytelling environment for managing projects, characters, locations, scenes, storyboards, assets, continuity, and production metadata.

The recommended first-time user workflow is intentionally simple and example-driven:

1. Set up a WordPress host or local StoryOS environment
2. Connect an LLM for AI advisor workflows
3. Optionally connect ComfyUI or Comfy Cloud for generation
4. Import the example story
5. Create or populate the Story Graph with world, character, location, scene, and asset records
6. Use AI advisors to review narrative consistency, dialogue, and visual direction
7. Generate visuals and import them back into StoryOS
8. Assemble or render a final sequence for the project

This onboarding flow is representative of the product experience, but it is not required for story-only workflows that do not use generation.

## Core Workstreams

- Story Core
- Generation Core
- Agent Core
- Script Ecosystem
- Editorial Ecosystem
- Story Graph Intelligence
- AI Editor

## Minimum Viable Product (MVP)

The current StoryOS MVP includes:

- Projects, worlds, characters, locations, and scenes
- Shots, storyboard frames, and generated assets
- Production metadata and sequencing support
- Story Graph-based relationship tracking
- AI advisor layer for story and production review
- ComfyUI / Comfy Cloud generation integration
- Celtx sync support for script ecosystem workflows
- EDL support for editorial handoff

## Technical Architecture Diagram

```
Creators
   ↓
StoryOS (WordPress + CPTs + SCF + Story Graph)
   ↓
┌───────────────────────────────┬──────────────────────────────┐
│       AI Layer                │      Generation Layer        │
│ • AI Editor                   │ • ComfyUI / Comfy Cloud      │
│ • WordPress Abilities API     │ • Workflow execution         │
│ • Story Graph Intelligence    │ • Asset import + tracking    │
│ • Continuity review           │ • Batch processing           │
└───────────────┬───────────────┴───────────────┬──────────────┘
                ↓                               ↓
        Story Graph (canonical source of truth)
                ↓
       ┌────────────┬────────────┬──────────────┐
       │   World    │ Characters │   Locations  │
       └─────┬──────┴─────┬──────┴─────┬────────┘
             ↓             ↓             ↓
        Projects      Scenes        Props / Assets
             ↓             ↓             ↓
       Storyboards    Shots       Production + Editorial
             ↓             ↓             ↓
           Sequence      EDL          Script Sync
```

## Product Requirements by Capability

### Story Core

- Support structured narrative data with persistent relationships between story entities
- Store projects, characters, locations, props, scenes, shots, and generated assets
- Maintain canonical metadata for continuity, production planning, and editorial tracking

### Generation Core

- Support generation requests through ComfyUI or Comfy Cloud
- Track generation status, job results, and associated assets in WordPress
- Preserve connection between generated output and the relevant Story Graph records
- Allow optional generation without making it a dependency for story-only workflows

### Agent Core

- Expose filmmaking advisors through WordPress AI tooling and Abilities API
- Support domain-specific review for story, visual style, production planning, and continuity
- Maintain context from the current Story Graph rather than isolated prompts

### Script Ecosystem

- Support script import/export workflows and script-aligned story structures
- Maintain Celtx synchronization where needed for collaborative production work
- Keep future file-based script import/export as an extension of the Story Graph model

### Editorial Ecosystem

- Support EDL export/import and timeline metadata
- Keep scene and shot mapping aligned with StoryOS records
- Enable downstream production and editorial packaging from the same canonical source

### Story Graph Intelligence

- Support semantic search and discovery across story entities
- Enable continuity validation and relationship analytics
- Provide narrative understanding as a production asset, not just a writing assistant

### AI Editor

- Provide a WordPress-based editor experience backed by Story Graph context
- Support direct LLM interactions for analysis, generation, and continuity review
- Keep the editor and its API surface inside the WordPress application layer

## Roadmap and Phase Status

| Phase | Name | Status | Notes |
|-------|------|--------|-------|
| 1 | Story Core | ✅ Complete | Canonical Story Graph and core entities |
| 2 | Generation Core | ✅ Complete | Comfy Cloud / ComfyUI integration path |
| 3 | Agent Core | ✅ Complete | WordPress Abilities and filmmaker advisors |
| 4 | Storyboarding & Production | ✅ WordPress Plugins | Storyboards, production metadata, asset workflows |
| 5 | Script Ecosystem | ⏸️ On Hold | Celtx sync is operational; broader file-based import/export remains deferred |
| 6 | Editorial Ecosystem | ⏸️ On Hold | EDL support exists; broader NLE and AAF/OMF work remains deferred |
| 7 | Story Graph Intelligence | ✅ Complete | Semantic search, continuity validation, relationship analytics |
| 8 | AI Editor | ✅ Complete | WordPress-based editor and LLM integration layer; live validation pending |
| 9 | Community Platform | ⏸️ Planned | Marketplace, templates, contributor onboarding, and community features |

## Example Workflow Requirement

The example workflow described in the StoryOS onboarding guide is a first-class product requirement for user adoption. It must be easy for a new user to:

- import a sample story
- populate projects and world data
- review generated narrative structure and character context
- generate assets using ComfyUI or Comfy Cloud
- return those assets to StoryOS and assemble a final output

This workflow establishes the product narrative and is the clearest demonstration of StoryOS as a storytelling operating system rather than a standalone image generator.

## Success Criteria

StoryOS is successful when a user can:

- create and manage a story project in WordPress
- maintain structured continuity across narrative entities
- use AI advisors with relevant story context
- generate or import media with clear project association
- export or package editorial deliverables from the same data model

The product should remain useful even when generation is optional, and it should not require a local GPU or custom Python runtime to serve as a structured story system.
