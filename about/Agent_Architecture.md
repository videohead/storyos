# StoryOS Agent Architecture v1.0

> Build Your Story Once. Create Everywhere.

## Purpose

> **Current architecture:** StoryOS uses WordPress Abilities API and plugin-owned filmmaking agents. See [Deployment and Connections](Deployment_and_Connections.md) for the supported runtime.

This document preserves the original agent-role design. Current agents run in WordPress with Story Graph context.

The agent system provides intelligent assistance throughout story development, asset generation, production planning, and editorial workflows while maintaining awareness of the Story Graph.

---

# Architectural Vision

StoryOS does not use AI as a replacement for creators.

StoryOS uses AI as a collaborative team of expert advisors.

The creator remains the Executive Producer.

Agents provide:

- Guidance
- Analysis
- Recommendations
- Automation
- Workflow execution
- Context retrieval

---

# Guiding Principles

## Human Directed

Humans make final decisions.

## Story Graph First

Agents retrieve knowledge from the Story Graph.

## Specialized Expertise

Each agent has a focused responsibility.

## Orchestrated Collaboration

Agents collaborate through MAF.

## Extensible

New advisors can be added without redesigning the platform.

---

# High Level Architecture

```text
Creator
   |
   v
StoryOS Interface
   |
   +-------------------+
   |                   |
   v                   v
Story Graph      Tool Integrations
Context          WordPress
                 ComfyUI
                 Search
                 Scripts
                 Editorial
```

---

# Agent Hierarchy

```text
Agent Abilities
        |
        +-----------------------------+
        |             |               |
        v             v               v
Story      Production Advisor   Technical Advisor
Advisor
        |
        +-------------+
        |             |
        v             v
Prompt        Editorial Advisor
Advisor
```

---

# Story Advisor

## Purpose

Assist with narrative development.

## Capabilities

- Story analysis
- Character review
- Plot consistency
- Worldbuilding support
- Story arc analysis

## Inputs

- Story Graph
- Scripts
- Notes

---

# Prompt Advisor

## Purpose

Transform story context into asset-generation prompts.

## Capabilities

- Character prompts
- Environment prompts
- Storyboard prompts
- Style recommendations

## Outputs

- ComfyUI-ready workflows
- Prompt templates

---

# Production Advisor

## Purpose

Support planning and scheduling.

## Capabilities

- Shot list generation
- Production breakdowns
- Scheduling support
- Resource planning

---

# Editorial Advisor

## Purpose

Assist post-production workflows.

## Capabilities

- Scene sequencing
- EDL support
- Timeline planning
- Continuity analysis

---

# Tooling Layer

Agents access tools through controlled interfaces.

## WordPress Tools

- Query CPTs
- Create content
- Update entities
- Search Story Graph

## ComfyUI Tools

- Execute workflows
- Retrieve assets
- Store generation metadata

## Script Tools

- Import scripts
- Export scripts
- Parse screenplay formats

## Editorial Tools

- Generate EDLs
- Produce timeline metadata

---

# Memory Architecture

## Session Memory

Current conversation context.

## Project Memory

Stored in StoryOS entities.

## Story Memory

Derived from Story Graph relationships.

## Agent Knowledge

Role-specific instructions and workflows.

---

# Context Retrieval Flow

```text
User Request
      |
      v
Story Graph Query
      |
      v
Relevant Context
      |
      v
Advisor Response
```

---

# Asset Generation Workflow

```text
Scene
  |
  v
Prompt Advisor
  |
  v
ComfyUI Workflow
  |
  v
Generated Asset
  |
  v
Story Graph Update
```

---

# Script To Editorial Workflow

```text
Script
   |
   v
Scene Extraction
   |
   v
Shot Planning
   |
   v
Production Data
   |
   v
EDL Export
```

---

# Future Advisors

Potential future agents include:

- Character Advisor
- Continuity Advisor
- Research Advisor
- Location Advisor
- Education Advisor
- Publishing Advisor
- Marketing Advisor
- Community Advisor

---

# Security Model

Agents should operate with least-privilege access.

All tool execution should be auditable.

Project-specific permissions must be respected.

---

# Strategic Objective

The long-term goal is an intelligent advisor ecosystem built around the Story Graph.

As models evolve, the Story Graph becomes the persistent knowledge layer while agents become interchangeable expert interfaces.

StoryOS therefore preserves story knowledge while remaining model-agnostic and future-proof.
