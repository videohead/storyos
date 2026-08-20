World Graph Studio Architecture Document v1.0

## Executive Summary

World Graph Studio is an open-source storytelling operating system built around a Story Graph in WordPress, helpful filmmaking agents exposed through the WordPress Abilities API, and generative workflows through ComfyUI and MCP.

## Vision

Build your story once. Create everywhere.

## Goals

Structured story management, asset generation, AI advisory, production planning, and editorial workflows.

## Key Components

| Component | Location | Description |
|-----------|----------|-------------|
| WordPress Plugin | `wordpress/wp-content/plugins/worldgraph/` | Main plugin with 11 CPTs, taxonomies, REST API, AI Editor |
| LLM Connection | WordPress AI Settings | OpenAI, Claude, or any OpenAI-compatible local or hosted endpoint |
| Comfy Cloud MCP | `https://cloud.comfy.org/mcp` | Managed generative media workflows |
| Local Comfy MCP | Creator MCP client | Optional local ComfyUI development and workflow operation |

## Core Workstreams

Story Core, Generation Core, Agent Core, Script Ecosystem, Production & Editorial.

## MVP

Projects, Characters, Locations, Scenes, Assets, Generation Engine integration, advisor layer, Celtx script sync, Web Stories sync.

## Roadmap

Phases 1-4, 7-8 complete. Phases 5, 6, 9 on hold.

## Technical Architecture Diagram

```
Creators
   ↓
World Graph Studio (WordPress + CPTs + SCF)
   ↓
Story Graph (canonical source of truth)
   ↓
┌─────────────────────────────────────────────────────┐
│  AI Layer                                           │
│  ┌──────────────────┐  ┌──────────────────────────┐ │
│  │ AI Editor        │  │ Story Graph Intelligence │ │
│  │ • Gutenberg UI   │  │ • Semantic search        │ │
│  │ • REST API       │  │ • Continuity validation  │ │
│  │ • LLM client     │  │ • Relationship analytics │ │
│  │ • WordPress      │  │                          │ │
│  │   Abilities API  │  │                          │ │
│  │ • Abilities API  │  │                          │ │
│  └────────┬─────────┘  └──────────┬───────────────┘ │
│           │                      │                  │
│           ▼                      ▼                  │
│  ┌──────────────────────────────────────────────────┐│
│  │  ComfyUI MCP       ││
│  └──────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────┘
   ↓                      ↓
   └──────┬───────────────┘
          ↓
     ComfyUI (GPU generation)
          ↓
       Assets
          ↓
    ┌─────┴─────┐
    ↓           ↓
Production   Script Ecosystem
 Planning    Celtx Sync (import/export)
    ↓           ↓
   EDL ←── Script Import/Export (planned)
    ↓           ↓
Editorial   Web Stories Sync
 Timeline    (export/import)
```

## WordPress Plugins

| Plugin | Purpose | Status |
|--------|---------|--------|
| EDL Import/Export | Edit Decision List import/export for NLE integration | ✅ Implemented |
| Web Stories Sync | Bidirectional sync with Google Web Stories | ✅ Implemented |
| Celtx Sync | Script import/export with Celtx format | ✅ Implemented |
| World Graph Studio - Story Core | Main plugin: CPTs, SCF, REST API, AI Editor, Intelligence | ✅ Implemented |
| Secure Custom Fields | Custom field framework for Story Graph data | ✅ Dependency |

## Phase Status (as of 2026-08-08)

| Phase | Name | Status | Remaining |
|-------|------|--------|-----------|
| 1 | Story Core | ✅ Complete | — |
| 2 | Generation Core | ✅ Complete | — |
| 3 | Agent Core | ✅ Complete | — |
| 4 | Storyboarding & Production | ✅ WordPress Plugins | — |
| 5 | Script Ecosystem | ⏸️ On Hold | Celtx sync operational; import/export deferred |
| 6 | Editorial Ecosystem | ⏸️ On Hold | EDL implemented; AAF/OMF, NLE plugins deferred |
| 7 | Story Graph Intelligence | ✅ Complete | Incremental indexing, caching, Neo4j integration (future) |
| 8 | AI Editor | ✅ Complete | MCP Adapter setup, agent-skills clone, keyboard shortcuts, audits |
| 9 | Community Platform | ⏸️ On Hold | Marketplace, templates, contributor programs deferred | 
