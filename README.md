# StoryOS

> Build Your Story Once. Create Everywhere.
>
> An Open Source AI Storytelling Operating System built on WordPress, ComfyUI, and Agentic AI.

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
│  WordPress   │────▶│  Python Orchestrator│───▶│  ComfyUI   │
│  (Story Graph)│    │  (FastAPI + Celery) │   │  (GPU Gen) │
└─────────────┘     └────────┬─────────┘     └────────────┘
                              │
                      ┌───────▼────────┐
                      │  AI Advisors   │
                      │  (5 Specialized│
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

### AI Advisors (Phase 3+)
- 5 specialized advisors: Story, Prompt, Production, Editorial, Technical
- Executive Orchestrator for intelligent routing
- Local model integration (Qwen3.6-35B via Ollama)
- Conversation history and context management

## Current Status

### ✅ Phase A: Workflow Template System (COMPLETE)
- JSON-based workflow templates with `__PLACEHOLDER__` substitution
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
- 5 specialized advisor adapters (Story, Prompt, Production, Editorial, Technical)
- Executive Orchestrator with intelligent routing
- Agent API endpoints (`/agents/*`)
- Conversation history tracking
- Multi-advisor review capability

### 🔄 Phase D: Storyboarding & Production (PLANNED)
- Storyboard management
- Shot list generation
- Production breakdowns
- Scheduling and call sheets

### 📋 Phase E: Script Ecosystem (PLANNED)
- Script import/export (Fountain, FDX, Celtx)
- Script-to-Story Graph conversion
- EDL export

### 📋 Phase F: Editorial Ecosystem (PLANNED)
- EDL export
- Timeline metadata
- NLE integrations (XML, AAF)

### 📋 Phase G: Story Graph Intelligence (PLANNED)
- Semantic search
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
