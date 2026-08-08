StoryOS Architecture Document v1.0

## Executive Summary

StoryOS is an open-source storytelling operating system built around a Story Graph, WordPress, ComfyUI, and AI advisors.

## Vision

Build your story once. Create everywhere.

## Goals

Structured story management, asset generation, AI advisory, production planning, and editorial workflows.

## Key Components

| Component | Location | Description |
|-----------|----------|-------------|
| WordPress Plugin | `wordpress/wp-content/plugins/storyos/` | Main plugin with 11 CPTs, taxonomies, REST API, AI Editor |
| Orchestrator | `orchestrator/` | FastAPI service with Celery, 5 advisors, 32+ agents |
| MAF Framework | `multi-agent-framework/` | 32+ filmmaking agent definitions (`.agent.md`) |
| Local LLM | `llm/qwen35MOE/` | Qwen3.6-35B-A3B-NVFP4 via vLLM/Ollama |
| ComfyUI | `ComfyUI/` | GPU-based asset generation |
| Test Framework | `test-framework/` | PHPUnit + pytest test suites |

## Core Workstreams

Story Core, Generation Core, Agent Core, Script Ecosystem, Production & Editorial.

## MVP

Projects, Characters, Locations, Scenes, Assets, ComfyUI Generate integration, advisor layer, Celtx script sync, Web Stories sync.

## Roadmap

Phases 1-4, 7-8 complete. Phases 5, 6, 9 on hold.

## Technical Architecture Diagram

```
Creators
   ↓
StoryOS (WordPress + CPTs + SCF)
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
│  │ • MAF bridge     │  │                          │ │
│  │ • Abilities API  │  │                          │ │
│  └────────┬─────────┘  └──────────┬───────────────┘ │
│           │                      │                  │
│           ▼                      ▼                  │
│  ┌──────────────────────────────────────────────────┐│
│  │  Orchestrator (FastAPI + Celery)                 ││
│  │  • ExecutiveOrchestrator                         ││
│  │  • 5 Advisors (Story, Prompt, Production, etc.)  ││
│  │  • 32+ MAF Agents                                ││
│  │  • Embedding backends (Dummy, Ollama, ST)        ││
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
| StoryOS - Story Core | Main plugin: CPTs, SCF, REST API, AI Editor, Intelligence | ✅ Implemented |
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
