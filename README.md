# StoryOS

> Build Your Story Once. Create Everywhere.
>
> An Open Source AI Storytelling Operating System built on WordPress, ComfyUI, and Agentic AI.

## Table of Contents

### About StoryOS
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
┌─────────────┐     ┌──────────────────┐     ┌────────────┐
│  WordPress  │───▶ │ComfyUI MCP Bridge│───▶│ ComfyUI    │
|(Story Graph)│     │ (submit/status)  │     │  (GPU Gen) │
└─────────────┘     └────────┬─────────┘     └────────────┘
                             │
                      ┌───────▼────────┐
                      │  AI Advisors   │
                      │ (50 Specialized│
                      │  + Executive)  │
                      └────────────────┘
```

**Data Flow:**
1. WordPress stores structured story data (CPTs, SCFs, Story Graph)
2. WordPress submits generation requests through ComfyUI MCP operations
3. MCP bridge maps normalized operations to ComfyUI workflow/runtime calls
4. MCP status/artifact operations return completion data and outputs
5. Generated assets upload back to WordPress media library
6. AI Advisors assist at every stage (story, prompts, production, editorial, technical)

## Technology Stack

### WordPress
- Content management with Custom Post Types (Project, Character, Location, Scene, Shot, Asset)
- Structured Content Fields (SCF) for metadata
- REST API for Story Graph queries
- Media library for asset storage

### ComfyUI MCP Bridge
- Normalized generation operations (submit, status, cancel, artifacts)
- ComfyUI workflow/runtime transport abstraction
- Compatible with remote MCP service endpoints

### WordPress Generation Workflow
- WordPress-native generation endpoints under `/wp-json/storyos/v1/generation`
- ComfyUI MCP operations (`submit`, `status`, `cancel`, `artifacts`) for execution
- Asset ingestion and lineage tracking in the WordPress control plane

### ComfyUI
- GPU-accelerated image/video generation
- Template-based workflow system
- Character sheets, environments, storyboards

### AI Advisors
- 50 specialized advisors from film industry archetypes
- WordPress-native advisor routing (Story, Prompt, Production, Editorial, Technical)
- Local model integration (BYOK, currently using a 35B MOE from Qwen via vLLM)
- Conversation history and context management

## Current Status

### ✅ Phase A: Workflow Template System (COMPLETE)
- JSON-based workflow templates
- Templates: base, character-sheet, environment, storyboard
- Story Graph context builder (WordPress CPT queries)
- Celery task refactoring with template support
- Retry logic with exponential backoff

### ✅ Phase B: Production Hardening (COMPLETE)
- Health check service (WordPress, ComfyUI, Redis, Celery)
- Structured logging middleware (JSON format, request IDs)
- Prometheus-style metrics endpoint
- Queue management (submit, cancel, prioritize, rate limit)
- Asset lineage tracking (provenance, versioning)
- Docker Compose orchestration (6 services)

### ✅ Phase C: Agent Integration (COMPLETE)
- 50 specialized advisors from film industry archetypes
- 5 specialized advisor adapters (Story, Prompt, Production, Editorial, Technical)
- WordPress-native advisor routing across specialized roles
- Agent API endpoints (`/agents/*`)
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
- Redis cache service
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

### 4. Optional AI services
The optional AI services are disabled by default. Start them only when you need them:

```bash
lando start-vllm
lando start-comfyui
```

You can inspect them with:

```bash
lando describe-vllm
lando describe-comfyui
```

### 4b. Running ComfyUI standalone with Docker Compose
If you prefer to run ComfyUI outside of Lando (e.g. with full GPU access), a standalone `docker-compose.yaml` is provided in the `ComfyUI/` directory.

```bash
cd ComfyUI

# CPU-only mode (for testing or machines without a GPU)
docker compose up

# GPU mode (recommended — requires nvidia-container-toolkit)
docker compose --profile gpu up
```

The ComfyUI web UI will be available at **http://localhost:8188**.

Downloaded models are persisted in a Docker volume (`comfyui_models`) so they survive container rebuilds.

To stop and clean up:

```bash
docker compose --profile gpu down
```

### Useful commands

```bash
lando info
lando wp
lando wp option update siteurl https://storyos.lndo.site
lando wp option update home https://storyos.lndo.site
lando pma
```

### Troubleshooting
- If `lando start` fails, make sure Docker is running and that your user account can access Docker.
- If the database is still warming up, wait a moment and run `lando info` again.
- If you need to inspect logs, use `lando logs`.

### API Endpoints (`/wp-json/storyos/v1`)

#### Story Graph Entities
- `GET|POST /projects`, `GET|POST /characters`, `GET|POST /locations`, `GET|POST /scenes`, `GET|POST /shots`
- `GET|POST /assets`, `GET|POST /storyboard-frames`, `GET|POST /editorial-artifacts`, `GET|POST /storyworlds`, `GET|POST /episodes`, `GET|POST /props`, `GET|POST /organizations`

#### Generation Workflow
- `GET|POST /generation` — Submit and list generation jobs
- `GET /generation/{id}` — Check generation status
- `POST /generation/{id}/cancel` — Cancel generation
- `GET /generation/asset/{asset_id}/history` — Inspect asset generation history

#### Search & Graph
- `GET /search` and `GET /search/suggest` — Story Graph search and suggestions
- `GET /graph/{id}`, `GET /graph/entities`, `GET|POST /graph/relationships` — Graph traversal and relationship management

#### AI Editor
- `POST /ai/chat`, `POST /ai/analyze`, `POST /ai/generate`, `POST /ai/continuity`
- `GET /ai/context`, `GET /ai/agents`, `GET|POST /ai/settings`, `GET /ai/health`

## Project Structure

```
storyos/
├── wordpress/                 # WordPress core and StoryOS plugin runtime
│   └── wp-content/plugins/storyos/   # StoryOS plugin (Story Graph, generation, AI editor)
├── ComfyUI/                   # Optional standalone ComfyUI runtime
├── multi-agent-framework/     # Legacy research artifacts
└── *.md                       # Architecture docs
```

## Contributing

We welcome contributions from storytellers, filmmakers, artists, WordPress developers, ComfyUI developers, AI engineers, educators, and researchers.

## Long-Term Goal

StoryOS is an open storytelling infrastructure project that supports every stage of the creative lifecycle from concept through production and editorial delivery.

**The future of storytelling is structured.**

**The future of storytelling is open.**
