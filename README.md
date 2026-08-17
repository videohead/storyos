# StoryOS

> Build Your Story Once. Create Everywhere.
>
> An open-source storytelling operating system: stories and helpful agents in WordPress, generative workflows through ComfyUI and MCP.

## Table of Contents

### About StoryOS
- [Deployment and Connections](about/Deployment_and_Connections.md) — Comfy Cloud, local Comfy MCP, LLM, and BYOK setup
- [StoryOS Architecture](about/StoryOS_Architecture.md) — System overview and component design
- [Content Model Specification](about/Content_Model_Specification.md) — Data model for stories, characters, scenes, and assets
- [Story Graph Specification](about/Story_Graph_Specification.md) — Connected story data structure
- [CPT and SCF Schema](about/CPT_and_SCF_Schema.md) — Custom Post Type and Structured Content Field definitions
- [REST API Specification](about/REST_API_Specification.md) — API endpoints and usage
- [SchemaOrg Minimum Surface](about/SchemaOrg_Minimum_Surface.md) — Schema.org integration baseline
- [SchemaOrg Interoperability Review](about/SchemaOrg_Interoperability_Review.md) — Cross-platform schema analysis
- [Script EDL Integration](about/Script_EDL_Integration.md) — Edit Decision List support for editorial workflows

### Multi-Agent System
- [Agent Architecture](about/Agent_Architecture.md) — AI advisor system design
- [Agents Documentation](about/AGENTS.md) — Agent roles, capabilities, and configuration
- [Agents Copy](about/AGENTS\ copy.md) — Additional agent reference documentation

### Product & Planning
- [StoryOD PRD](about/StoryOD_PRD.md) — Product Requirements Document
- [Roadmap](about/ROADMAP_StoryOS.md) — Project timeline and milestones

### Governance & Community
- [Contributing Guide](about/CONTRIBUTING_StoryOS.md) — How to contribute to StoryOS
- [Governance](about/GOVERNANCE_StoryOS.md) — Project governance model
- [Code of Conduct](about/CODE_OF_CONDUCT_StoryOS.md) — Community guidelines

### Marketing
- [Brand Guide](about/marketing/StoryOS_Brand_Guide.md) — Brand identity and usage guidelines
- [Pitch Deck](about/marketing/StoryOS-PitchDeck.png) — Visual pitch deck

---

## What is StoryOS?

StoryOS is an open-source platform that combines structured story development, AI-assisted creation, production planning, asset generation, and editorial workflows into a unified storytelling environment.

Unlike AI tools that focus only on image or video generation, StoryOS focuses on preserving story context throughout the entire creative lifecycle.

StoryOS treats stories as structured, connected data.

Characters, locations, props, scenes, storyboards, scripts, shots, generated assets, production plans, and editorial artifacts all become part of a shared Story Graph that serves as the source of truth for the project.

## Vision

Create an open platform where creators can manage story worlds, develop scripts, generate visual assets, plan productions, collaborate with AI advisors, and export industry-standard deliverables from a single source of truth.

## Core Architecture

```
┌─────────────┐     ┌──────────────────┐     ┌───────────────┐
│  WordPress  │───▶ │ WP-Cron batches  │───▶ │ Comfy Cloud   │
│(Story Graph)│     │ + MCP client     │     │ MCP / ComfyUI │
└──────┬──────┘     └──────────────────┘     └───────────────┘
  │
┌──────▼───────────┐
│ WordPress        │
│ Abilities API    │
│ (filmmaking AI)  │
└──────────────────┘
```

**Data Flow:**
1. WordPress stores structured story data (CPTs, SCFs, Story Graph)
2. WordPress queues durable generation records and WP-Cron processes bounded batches
3. The Comfy Cloud MCP client submits templates and polls remote job status
4. Generated assets and job state remain associated with WordPress records
5. WordPress Abilities expose filmmaking agents to MCP-compatible AI tooling

## Technology Stack

### WordPress
- Content management with Custom Post Types (Project, Character, Location, Scene, Shot, Asset)
- Structured Content Fields (SCF) for metadata
- REST API for Story Graph queries
- Media library for asset storage

### Generation Processing
- WordPress generation records and WP-Cron batch processing
- Official Comfy Cloud MCP over Streamable HTTP
- Comfy workflow templates and remote job polling
- No project-managed Python, Celery, or Redis runtime

### Comfy Cloud MCP
- GPU-accelerated image, video, audio, and 3D workflows
- Template discovery and execution through the first-party MCP endpoint
- API key supplied with `STORYOS_COMFY_API_KEY` or the StoryOS option

### AI Advisors
- 50 specialized advisors from film industry archetypes
- WordPress Abilities API registration for tools, resources, and prompts
- Plugin-owned filmmaker agent definitions and context-aware local routing
- Conversation history and context management

## Current Status

### ✅ Phase A: Workflow Template System (COMPLETE)
- JSON-based workflow templates
- Templates: base, character-sheet, environment, storyboard
- Story Graph context builder (WordPress CPT queries)
- WP-Cron batch scheduling and durable job records
- Comfy Cloud MCP template execution and status polling

### ✅ Phase B: Production Hardening (COMPLETE)
- WordPress-native job state, cancellation, and status endpoints
- Bounded cron batches with overlap locking

### ✅ Phase C: Agent Integration (COMPLETE)
- 50 specialized advisors from film industry archetypes
- WordPress Ability tools, resources, and prompt templates
- Plugin-owned filmmaker agent registry
- Conversation history tracking
- Multi-advisor review capability

### ✅ Phase E: Script Ecosystem (COMPLETE)
- **Celtx Integration** — Full bi-directional sync via Celtx GEM API
  - CPT synchronization (Projects, Characters, Locations, Scenes, Shots)
  - Persistent StoryOS ↔ Celtx ID mapping
  - WordPress plugin with REST API endpoints
  - API key, Basic Auth, and Cookie Auth support
- **File-Based Import** (Planned)
  - Fountain, FDX, Fade In, Highland, Markdown
  - Scene/character/location extraction
  - Auto-create Story Graph entities from scripts
- **Script Export** (Planned)
  - Fountain, Screenplay, Shooting Script formats
  - Script-to-Story Graph conversion

### 📋 Phase F: Editorial Ecosystem (COMPLETE)
- EDL import and export
- Timeline metadata
- NLE integrations (Unity, Davinci Resolve)

### 📋 Phase G: Story Graph Intelligence (COMPLETE)
- Semantic search and indexing in WordPress
- Continuity validation
- Relationship analytics

## Quick Start

### 1. Install the prerequisites
Before starting StoryOS, make sure you have the following installed and running:
- Docker Desktop or Docker Engine
- Git
- Lando
- A WordPress.org-capable host or local Docker/Lando deployment
- An API-connected LLM: local OpenAI-compatible server or an OpenAI/Anthropic API key

Comfy Cloud MCP or local ComfyUI via an MCP client is optional for generation. Browser-only ChatGPT, Claude, and Claude Code subscriptions are not supported by the WordPress integration without an API credential.

### 2. Install Lando
Lando is the recommended way to run StoryOS locally.

Get the installer from the official Lando documentation:
https://docs.lando.dev/getting-started/installation.html

The installation steps vary by platform:
- macOS: use the macOS installer or Homebrew
- Windows: use the Windows installer
- Linux: install Docker first, then install the Linux package or installer from the official Lando site

After installation, verify that Lando is available:

```bash
lando version
```

### 3. Clone the repository and start the stack

```bash
git clone <repo-url>
cd storyos
lando start
```

This starts the core local stack, including:
- WordPress with PHP 8.2 and MariaDB
- phpMyAdmin for database inspection

Once Lando finishes starting, use:

```bash
lando info
```

to see the local URLs for the app, database tools, and other services.

### 3b. Import the database

To import the existing WordPress database dump:

```bash
lando db-import scripts/backup.sql
```

This loads the SQL dump into the MariaDB database. Verify the import with:

```bash
lando db-import --check
```

### 4. Connect Generation and AI
Open **StoryOS > Setup** in WordPress to configure Comfy Cloud MCP and an API-connected LLM. Configure local Comfy MCP in an MCP-capable agent client. See [Deployment and Connections](about/Deployment_and_Connections.md) for the required credentials and supported local endpoints.

### Useful commands

```bash
lando info
lando wp
lando wp option update siteurl https://storyos.lndo.site
lando wp option update home https://storyos.lndo.site
lando phpunit
lando playwright
lando wp-cron
lando pma
```

### Troubleshooting
- If `lando start` fails, make sure Docker is running and that your user account can access Docker.
- If the database is still warming up, wait a moment and run `lando info` again.
- If you need to inspect logs, use `lando logs`.

### API Endpoints

#### WordPress REST
- `POST /wp-json/storyos/v1/generation` — Queue a Comfy Cloud MCP generation
- `GET /wp-json/storyos/v1/generation/{id}` — Read persisted job state
- `POST /wp-json/storyos/v1/generation/{id}/cancel` — Cancel a queued WordPress job
- `GET /wp-json/storyos/v1/ai/agents` — List plugin-owned filmmaking agents

## Project Structure

```
storyos/
├── wordpress/                 # WordPress core and StoryOS plugin
│   └── wp-content/plugins/storyos/
│       ├── includes/ai-editor/ # WordPress Abilities and filmmaker agents
│       └── includes/utils/     # Comfy Cloud MCP client and WP-Cron batches
├── .lando.yml                 # PHP, MariaDB, and phpMyAdmin development stack
└── *.md                       # Architecture docs
```

## Contributing

We welcome contributions from storytellers, filmmakers, artists, WordPress developers, ComfyUI developers, AI engineers, educators, and researchers.

## Long-Term Goal

StoryOS is an open storytelling infrastructure project that supports every stage of the creative lifecycle from concept through production and editorial delivery.

**The future of storytelling is structured.**

**The future of storytelling is open.**
