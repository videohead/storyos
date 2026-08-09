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
│  WordPress  │───▶ │Orchestrator      │───▶│ ComfyUI    │
|(Story Graph)│     │(FastAPI + Celery)│     │  (GPU Gen) │
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
2. Python Orchestrator queries Story Graph, builds generation context
3. Workflow templates render ComfyUI JSON with story context
4. Celery workers submit to ComfyUI, poll for completion
5. Generated assets upload back to WordPress media library
6. AI Advisors assist at every stage (story, prompts, production, editorial, technical)

## Technology Stack

### WordPress
- Content management with Custom Post Types (Project, Character, Location, Scene, Shot, Asset)
- Structured Content Fields (SCF) for metadata
- REST API for Story Graph queries
- Media library for asset storage

### Python Orchestrator
- FastAPI for REST API endpoints
- Celery + Redis for async task queue
- Workflow template system (JSON-based ComfyUI workflows)
- Story Graph context builder
- Asset lineage tracking
- Health monitoring and metrics

### ComfyUI
- GPU-accelerated image/video generation
- Template-based workflow system
- Character sheets, environments, storyboards

### AI Advisors
- 50 specialized advisors from film industry archetypes
- Executive Orchestrator for intelligent routing (Story, Prompt, Production, Editorial, Technical)
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
- Executive Orchestrator with intelligent routing
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
- the orchestrator services
- phpMyAdmin for database inspection
- the test framework for Playwright and PHPUnit

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
The optional AI services are not hosted by Lando. They run as separate Docker Compose services outside the Lando stack and should be started from their own directories when needed:

```bash
# Start the external LLM service
cd llm/gemma26B
docker compose up -d --build

# Start the external ComfyUI service
cd ../ComfyUI
docker compose --profile gpu up -d
```

Check the containers and health endpoints:

```bash
docker compose ps
curl -fsS http://localhost:11434/v1/models
curl -fsS http://localhost:8188
```

The orchestrator reads these services through the `VLLM_URL` and `COMFYUI_URL` environment variables.

### 4b. Running ComfyUI externally with Docker Compose
If you prefer to run ComfyUI outside of Lando (for example with full GPU access), a standalone `docker-compose.yaml` is provided in the `ComfyUI/` directory.

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
lando python
lando phpunit
lando playwright
lando pma
```

### Troubleshooting
- If `lando start` fails, make sure Docker is running and that your user account can access Docker.
- If the database is still warming up, wait a moment and run `lando info` again.
- If you need to inspect logs, use `lando logs`.

### API Endpoints

#### Generation
- `POST /generate` — Submit generation task
- `GET /status/{job_id}` — Check task status
- `GET /workflows` — List available workflow templates
- `POST /workflows/build` — Dry-run workflow build

#### Queue Management
- `POST /queue/submit` — Submit task with priority
- `POST /queue/cancel` — Cancel pending task
- `GET /queue/active` — List active tasks
- `GET /queue/pending` — List pending tasks

#### Asset Lineage
- `GET /assets` — List assets with filters
- `GET /assets/{post_id}` — Get asset details
- `POST /assets/{post_id}/status` — Update asset status
- `POST /assets/{post_id}/media` — Upload media

#### Health & Monitoring
- `GET /health` — Service health checks
- `GET /metrics` — Prometheus-style metrics

#### AI Advisors (Phase 3+)
- `GET /agents` — List available agents
- `POST /agents/orchestrator` — Executive Orchestrator
- `POST /agents/story` — Story Advisor
- `POST /agents/prompt` — Prompt Advisor
- `POST /agents/production` — Production Advisor
- `POST /agents/editorial` — Editorial Advisor
- `POST /agents/technical` — Technical Advisor
- `POST /agents/review` — Multi-advisor review
- `GET /agents/history` — Conversation history

## Project Structure

```
storyos/
├── orchestrator/              # Python orchestrator (FastAPI + Celery)
│   ├── app.py                 # FastAPI main application
│   ├── tasks.py               # Celery tasks
│   ├── models.py              # Pydantic models
│   ├── story_graph.py         # Story Graph context builder
│   ├── health.py              # Health check service
│   ├── middleware.py           # Logging & metrics middleware
│   ├── queue_manager.py       # Queue management
│   ├── asset_lineage.py       # Asset tracking
│   ├── workflows/             # Workflow templates
│   │   ├── templates/         # JSON workflow templates
│   │   └── loader.py          # Template loader
│   └── adapters/              # AI advisor adapters
│       ├── story_advisor.py
│       ├── prompt_advisor.py
│       ├── production_advisor.py
│       ├── editorial_advisor.py
│       ├── technical_advisor.py
│       └── executive_orchestrator.py
├── multi-agent-framework/     # MAF integration scaffold
├── wordpress/                 # WordPress core
├── docker-compose.yml         # Full stack orchestration
└── *.md                       # Architecture docs
```

## Contributing

We welcome contributions from storytellers, filmmakers, artists, WordPress developers, ComfyUI developers, AI engineers, educators, and researchers.

## Long-Term Goal

StoryOS is an open storytelling infrastructure project that supports every stage of the creative lifecycle from concept through production and editorial delivery.

**The future of storytelling is structured.**

**The future of storytelling is open.**
