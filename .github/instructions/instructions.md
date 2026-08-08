# StoryOS Multi-Agent Build Instructions

> Build Your Story Once. Create Everywhere.
>
> This file defines how multiple AI agents collaborate to build StoryOS using the
> Microsoft Agent Framework (MAF) pattern, GitHub Copilot Chat, and the local
> nvidia/Qwen3.6-35B-A3B-NVFP4 model.

---

## Agent Hierarchy

StoryOS uses a **hierarchical agent architecture** where agents collaborate through
an orchestrator pattern. Each agent has a focused responsibility and accesses the
Story Graph as the canonical source of truth.

```
┌─────────────────────────────────┐
│     Executive Orchestrator      │  ← Routes tasks, aggregates results
│     (Copilot Chat session)      │
└──────────┬──────────────────────┘
           │
    ┌──────┼──────────┬──────────┬──────────┐
    │      │          │          │          │
    v      v          v          v          v
┌────────┐┌────────┐┌────────┐┌────────┐┌────────┐
│ Story  ││Prompt  ││Pro-    ││Edi-    ││Tech-   │
│ Advisor││Advisor││duction ││torial  ││nical   │
│        ││       ││Advisor ││Advisor││Advisor │
└────────┘└────────┘└────────┘└────────┘└────────┘
```

### Agent Roles

| Agent | Responsibility | Maps To |
|-------|---------------|---------|
| **Executive Orchestrator** | Task decomposition, routing, result aggregation | Copilot Chat session manager |
| **Story Advisor** | Story Core: CPTs, content model, Story Graph entities | `storyos/` — WordPress plugin |
| **Prompt Advisor** | Generation Core: ComfyUI workflows, prompt templates | `storyos/plugins/comfy-generate/` — ComfyUI integration |
| **Production Advisor** | Production planning: shot lists, schedules, storyboards | Future: production modules |
| **Editorial Advisor** | Editorial workflows: EDL, timeline, continuity | Future: editorial modules |
| **Technical Advisor** | Infrastructure: Docker, MAF integration, APIs | `multi-agent-framework/` |

---

## Multi-Agent Framework (MAF) Integration

The `multi-agent-framework/` directory provides the scaffolding for agentic
collaboration. Agents use this framework to:

- Communicate through a shared message bus
- Route tasks via the orchestrator pattern
- Access the local model through a normalized proxy
- Maintain context across agent handoffs

### MAF Components

| Component | Path | Purpose |
|-----------|------|---------|
| **API Client** | `multi-agent-framework/api_client.py` | OpenAI-compatible HTTP client with health checks and multi-endpoint fallback |
| **Local Agent Harness** | `multi-agent-framework/local_agent_framework.py` | CLI for health checks, single prompts, and interactive chat loops |
| **MAF Example** | `multi-agent-framework/maf_example.py` | Simulated two-agent demo (fallback when `agent-framework` package is absent) |
| **MAF Integration** | `multi-agent-framework/maf_integration.py` | Adaptive MAF integration — detects installed package, maps Agent/Orchestrator APIs |
| **Proxy** | `multi-agent-framework/proxy/proxy.py` | Normalizes OpenAI-compatible requests to the local model server |

### Local Model Setup

All agents use the local nvidia/Qwen3.6-35B-A3B-NVFP4 model:

```
Model Server:  http://0.0.0.0:11434/v1/
Proxy:         http://0.0.0.0:11435/v1/   (optional, for normalization)
Copilot:       Configured via COPILOT_CONFIG.md
```

Proxy workflow:
```
Agent → Proxy (:11435) → Model Server (:11434)
```

The proxy normalizes OpenAI-style requests (chat completions, streaming) into
the format expected by the local model server (vLLM / Ollama compatible).

### Running the MAF Stack

```bash
# 1. Start the proxy (normalizes requests to local model)
cd multi-agent-framework
python proxy/proxy.py

# 2. Run the local agent harness (health check, chat, interactive)
python local_agent_framework.py health
python local_agent_framework.py chat -p "your prompt here"
python local_agent_framework.py interactive

# 3. Run the simulated two-agent demo (fallback mode)
python maf_example.py

# 4. Run the adaptive MAF integration (requires agent-framework package)
python maf_integration.py
```

### Environment Variables

All MAF components read from `.env` (copy from `.env.example`):

```env
OPENAI_API_BASE=http://localhost:11434/v1
OPENAI_API_KEY=local-dev-key
PROXY_PORT=8080
PROXY_TARGET_MODEL=qwen3.6:35b-a3b-q4_K_M
MODEL=qwen
```

---

## Project Structure

```
storyos/
├── orchestrator/              # Phase 1-3: Python orchestrator (FastAPI + Celery)
│   ├── app.py                 # FastAPI main application (all endpoints)
│   ├── tasks.py               # Celery tasks (template-based generation)
│   ├── models.py              # Pydantic request/response models
│   ├── story_graph.py         # Story Graph context builder (WordPress API)
│   ├── health.py              # Health check service (WordPress, ComfyUI, Redis, Celery)
│   ├── middleware.py           # Structured logging & Prometheus metrics
│   ├── queue_manager.py       # Queue management (submit, cancel, prioritize)
│   ├── asset_lineage.py       # Asset tracking & provenance (WordPress CPT)
│   ├── workflows/             # Phase A: Workflow template system
│   │   ├── templates/         # JSON workflow templates
│   │   │   ├── base.json
│   │   │   ├── character-sheet.json
│   │   │   ├── environment.json
│   │   │   └── storyboard.json
│   │   └── loader.py          # Template loader with caching
│   └── adapters/              # Phase C: AI advisor adapters
│       ├── story_advisor.py
│       ├── prompt_advisor.py
│       ├── production_advisor.py
│       ├── editorial_advisor.py
│       ├── technical_advisor.py
│       └── executive_orchestrator.py
├── multi-agent-framework/     # MAF integration scaffold
│   ├── api_client.py          # OpenAI-compatible HTTP client
│   ├── local_agent_framework.py # CLI harness (health, chat, interactive)
│   ├── maf_example.py         # Simulated two-agent demo
│   ├── maf_integration.py     # Adaptive MAF integration scaffold
│   ├── proxy/proxy.py         # Request normalization proxy
│   └── requirements.txt       # Python dependencies
├── wordpress/                 # WordPress core
├── docker-compose.yml         # Full stack orchestration (6 services)
├── IMPLEMENTATION_PLAN.md     # Detailed implementation roadmap
└── *.md                       # Architecture & specification documents
```

---

## Implementation Status

### ✅ Phase A: Workflow Template System (COMPLETE)
- JSON-based workflow templates with `__PLACEHOLDER__` substitution
- Templates: base, character-sheet, environment, storyboard
- Story Graph context builder (WordPress CPT queries with TTL caching)
- Celery task refactoring with template support
- Retry logic with exponential backoff (max 3 retries)

### ✅ Phase B: Production Hardening (COMPLETE)
- Health check service (WordPress, ComfyUI, Redis, Celery)
- Structured logging middleware (JSON format, request ID propagation)
- Prometheus-style metrics endpoint (`/metrics`)
- Queue management (submit, cancel, prioritize, rate limit)
- Asset lineage tracking (provenance, versioning via WordPress CPT)
- Docker Compose orchestration (6 services: wordpress, db, redis, python-orchestrator, celery-worker, comfyui)

### ✅ Phase C: Agent Integration (COMPLETE)
- 5 specialized advisor adapters:
  - **Story Advisor**: Narrative analysis, character development, plot consistency
  - **Prompt Advisor**: Asset generation prompts (positive/negative), style recommendations
  - **Production Advisor**: Production planning, scheduling, asset tracking
  - **Editorial Advisor**: Asset quality review, style consistency, curation
  - **Technical Advisor**: Integration troubleshooting, ComfyUI optimization, API design
- Executive Orchestrator with intelligent routing (keyword + context-based)
- Agent API endpoints (`/agents/*`)
- Conversation history tracking
- Multi-advisor review capability

### ✅ Phase E: Script Ecosystem (COMPLETE — Celtx Integration)
- **Celtx GEM API Integration** — Full bi-directional sync via `storyos-celtx` plugin
  - CPT synchronization (Projects, Characters, Locations, Scenes, Shots)
  - Persistent StoryOS ↔ Celtx ID mapping in post meta
  - WordPress REST API endpoints (`/wp-json/storyos-celtx/v1/*`)
  - API key, Basic Auth, and Cookie Auth support
  - Settings UI for Celtx credentials in WordPress admin
- **File-Based Import** (Planned)
  - Fountain, FDX, Fade In, Highland, Markdown
  - Scene/character/location extraction from scripts
  - Auto-create Story Graph entities from imported scripts
- **Script Export** (Planned)
  - Fountain, Screenplay, Shooting Script formats
  - Script-to-Story Graph conversion

### 📋 Phase F: Editorial Ecosystem (PLANNED)
- EDL export
- Timeline metadata
- NLE integrations (XML, AAF)

### 📋 Phase G: Story Graph Intelligence (PLANNED)
- Semantic search
- Continuity validation
- Relationship analytics

---

## Build Phases & Agent Assignment

Each roadmap phase maps to specific agents and repositories:

### ✅ Phase 1-2: Story Core + Generation Core → Story Advisor + Prompt Advisor + Technical Advisor
**Repository:** `orchestrator/`
- WordPress CPT architecture (Project, Story World, Character, Location, Scene, Shot, Asset)
- Structured Content Fields (SCF) data models
- Workflow template system (JSON-based ComfyUI workflows)
- Story Graph context builder (WordPress REST API queries)
- Celery task queue with retry logic
- Asset lineage tracking

**Status:** ✅ COMPLETE - All core generation pipeline implemented

### ✅ Phase 3: Agent Core → Technical Advisor + Executive Orchestrator
**Repository:** `orchestrator/adapters/` + `multi-agent-framework/`
- 5 specialized advisor adapters (Story, Prompt, Production, Editorial, Technical)
- Executive Orchestrator with intelligent routing
- Agent API endpoints (`/agents/*`)
- Conversation history tracking
- Multi-advisor review capability
- Local model integration (Qwen3.6-35B via Ollama)

**Status:** ✅ COMPLETE - All advisors implemented and integrated

### ✅ Phase 5: Script Ecosystem → Story Advisor + Technical Advisor
**Repository:** `wordpress/wp-content/plugins/storyos/plugins/celtx/`
- Celtx GEM API bi-directional sync (COMPLETE)
- CPT synchronization (Projects, Characters, Locations, Scenes, Shots)
- WordPress plugin with REST API endpoints
- API authentication (API key, Basic Auth, Cookie Auth)
- Settings UI for Celtx credentials
- **File-Based Import/Export** (Planned) — Fountain, FDX, Fade In, Highland, Markdown
- **Script-to-Story Graph Conversion** (Planned)

**Status:** ✅ COMPLETE - Celtx integration implemented; file-based import/export planned

**Status:** 📋 PLANNED

### 📋 Phase 6: Editorial Ecosystem → Editorial Advisor
- EDL export
- Timeline metadata
- Scene/shot mapping
- NLE integrations (XML, AAF)

**Status:** 📋 PLANNED

### 📋 Phase 7: Story Graph Intelligence → Story Advisor + Technical Advisor
- Semantic search
- Continuity validation
- Relationship analytics
- Narrative reasoning

**Status:** 📋 PLANNED

### 📋 Phase 8: Community Platform → All Agents
- Plugin marketplace
- Workflow marketplace
- Advisor marketplace
- Community templates

**Status:** 📋 PLANNED

---

## Agent Collaboration Protocol

### Task Decomposition

The Executive Orchestrator (Copilot Chat session) decomposes user requests
into subtasks assigned to specialized agents:

1. **Receive request** — Understand the user's goal
2. **Identify domain** — Map to the appropriate agent(s)
3. **Decompose** — Break into atomic, parallelizable tasks
4. **Assign** — Route each task to the correct agent context
5. **Execute** — Agents work on their assigned tasks
6. **Aggregate** — Combine results, resolve conflicts
7. **Validate** — Ensure all tests pass, code follows conventions
8. **Report** — Summarize what was built

### Parallel Execution

Agents can work in parallel when tasks are independent:

```
User Request: "Add a new Asset CPT with versioning support"
    │
    ├── Story Advisor ──→ Define CPT schema, fields, taxonomies
    ├── Technical Advisor ──→ Create REST endpoints, update API spec
    └── Prompt Advisor ──→ Add asset versioning to SCF fields
```

### Context Sharing

Agents share context through:

- **Story Graph** — The canonical data model (all agents read/write here)
- **Specification documents** — `Content_Model_Specification.md`, `REST_API_Specification.md`, etc.
- **Shared files** — Changes to common files are visible to all agents
- **Session memory** — Current conversation context

### Handoff Protocol

When one agent's output becomes another agent's input:

1. Agent A completes its task and writes results to the appropriate file
2. Agent A signals completion with a clear summary
3. Agent B reads the output and proceeds with its task
4. Executive Orchestrator validates the combined result

---

## Coding Conventions

### WordPress (storyos/)
- Use WordPress Coding Standards (WPCS)
- Custom Post Types registered in a single plugin file
- Structured Content Fields via ACF Pro or MetaBox
- REST API endpoints under `/api/storyos/v1/` namespace
- All CPTs must support the REST API
- Use WordPress nonces for all form submissions
- Sanitize input, escape output
- Sub-plugins live under `storyos/plugins/` (e.g., `comfy-generate/`, `celtx/`)

### Python (multi-agent-framework/, orchestrator/)
- Python 3.10+ type hints
- Modular design — one responsibility per module
- Use `api_client.py` for all OpenAI-compatible HTTP calls
- Environment variables via `python-dotenv`
- Error handling with specific exception types
- Logging for all agent interactions

### Docker / Lando
- Use Lando where possible for local environment management
- Docker Compose for service orchestration
- WordPress and database data in named volumes (never bind-mount)
- ComfyUI behind GPU-enabled service (uncomment when GPU available)
- Never commit sensitive `.env` files

### Git
- Commit messages follow conventional commits format
- Agents build and test code, but **humans review before committing**
- Each phase should have its own feature branch
- Tests must pass before merging

---

## Testing

### Python Tests (orchestrator/)
- Run pytest: `cd orchestrator && pytest tests/ -v`
- Test structure:
  - `tests/test_app.py` — FastAPI endpoint tests
  - `tests/test_tasks.py` — Celery task unit tests
  - `tests/test_comfy_integration.py` — ComfyUI integration tests
  - `tests/test_pipeline_send_to_comfy.py` — End-to-end pipeline tests
  - `tests/test_upload_edgecases.py` — WordPress media upload edge cases
- Use `conftest.py` for test fixtures (eager mode, mocked services)
- Tests use monkeypatch for HTTP calls when services unavailable

### MAF Tests
- Run local agent harness: `python local_agent_framework.py health`
- Test API client: `python maf_example.py` (simulated two-agent demo)
- Test proxy: `python proxy/proxy.py` and verify request normalization

### Integration Testing
- Full stack: `docker compose up -d` then test endpoints
- WordPress → Orchestrator → ComfyUI → WordPress upload pipeline
- Agent → Proxy → Model Server → Agent response flow
- Test logs stored in `orchestrator/test_logs/`

---

## Agent Execution Rules

1. **Read before writing** — Always read existing files before making changes
2. **Respect the Story Graph** — All new entities must fit the canonical model
3. **Follow the roadmap** — Build phases in order unless explicitly told otherwise
4. **Validate before proceeding** — Run tests after each code change
5. **Document as you build** — Update relevant `.md` files when architecture changes
6. **No assumptions** — If a specification is unclear, ask the user before guessing
7. **Preserve existing code** — Never delete working code without explicit instruction
8. **Use the local model** — Prefer `nvidia/Qwen3.6-35B-A3B-NVFP4` over paid models

---

## Quick Reference

### Start the stack
```bash
# Full stack (WordPress, DB, Redis, Orchestrator, Worker, ComfyUI)
docker compose up -d

# Just the orchestrator API (for development)
cd orchestrator
uvicorn app:app --reload --host 0.0.0.0 --port 8000

# Celery worker (for development)
cd orchestrator
celery -A tasks worker --loglevel=info --concurrency=4

# MAF proxy (for local model normalization)
cd multi-agent-framework
python proxy/proxy.py

# MAF agent harness
python local_agent_framework.py health
```

### Key URLs
- WordPress: `http://localhost:8080`
- WordPress Admin: `http://localhost:8080/wp-admin`
- ComfyUI: `http://localhost:8188`
- FastAPI Orchestrator: `http://localhost:8000`
- FastAPI Docs: `http://localhost:8000/docs`
- Model Server: `http://localhost:11434/v1/`
- Proxy: `http://localhost:11435/v1/` (optional)

### Key API Endpoints
- Generation: `POST /generate`, `GET /status/{job_id}`
- Workflows: `GET /workflows`, `POST /workflows/build`
- Queue: `POST /queue/submit`, `POST /queue/cancel`, `GET /queue/active`
- Assets: `GET /assets`, `POST /assets/{post_id}/media`
- Health: `GET /health`
- Metrics: `GET /metrics`
- Agents: `GET /agents`, `POST /agents/orchestrator`, `POST /agents/story`, etc.

### Key Files
- Story Graph spec: `Story_Graph_Specification.md`
- Content model: `Content_Model_Specification.md`
- REST API: `REST_API_Specification.md`
- Agent architecture: `Agent_Architecture.md`
- Roadmap: `ROADMAP_StoryOS.md`
- Implementation plan: `orchestrator/IMPLEMENTATION_PLAN.md`
- MAF README: `multi-agent-framework/MAF_README.md`
- Copilot config: `multi-agent-framework/COPILOT_CONFIG.md`