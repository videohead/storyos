StoryOS Product Requirements Document (PRD) v1.0

## Executive Summary

StoryOS is an open-source storytelling operating system built around a Story Graph and helpful filmmaking agents in WordPress, with generative workflows through ComfyUI and MCP.

> **Implementation update:** Python orchestration and Microsoft Agent Framework references later in this historical PRD are superseded by WordPress Abilities API, WP-Cron, and Comfy Cloud MCP. See [Deployment and Connections](Deployment_and_Connections.md).

## Vision

Build your story once. Create everywhere.

## Goals

Structured story management, asset generation, AI advisory, production planning, and editorial workflows.

## Core Workstreams

Story Core, Generation Core, Agent Core, Script Ecosystem, Production & Editorial.

## MVP

Projects, Characters, Locations, Scenes, Assets, Generation Engine integration, advisor layer, Celtx script sync.

## Roadmap

Phases 1-6 including Story Graph, scripts, production and EDL support.

## Technical Architecture Diagram

```
Creators
   ↓
StoryOS (WordPress + CPTs + SCF)
   ↓
Story Graph (canonical source of truth)
   ↓
┌─────────────────────┬──────────────────┐
│     Advisors        │ Generation Engine│
│ Story, Prompt,      │ ComfyUI workflows│
│ Production &        |                  |
| Editorial,Character,│                  |
│ Technical           │ Environment,     │
│                     │ Storyboard       │
└─────────────────────┴──────────────────┘
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
 Planning    Celtx Sync
    ↓           ↓
   EDL ←── Script Import/Export
    ↓
Editorial Timeline
```

## Phase Status

| Phase | Name | Status |
|-------|------|--------|
| 1 | Story Core | ✅ Complete |
| 2 | Generation Core | ✅ Complete |
| 3 | Agent Core | ✅ Complete |
| 4 | Storyboarding & Production | 📋 WordPress Plugins |
| 5 | Script Ecosystem | ✅ Complete (Celtx) |
| 6 | Editorial Ecosystem | 📋 Planned | 
| 7 | User workflow, import and generate | 📋 Planned |
