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
| **Story Advisor** | Story Core: CPTs, content model, Story Graph entities | `wp-comfy/` — WordPress plugin |
| **Prompt Advisor** | Generation Core: ComfyUI workflows, prompt templates | `wp-comfy/` — orchestrator + ComfyUI |
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
├── wp-comfy/                    # Phase 1-2: WordPress + ComfyUI integration
│   ├── docker-compose.yml       # WordPress, MariaDB, Python orchestrator, ComfyUI
│   ├── orchestrator/            # FastAPI + Celery worker for ComfyUI jobs
│   ├── wordpress/               # WordPress core + plugins
│   └── scripts/                 # DB setup, migrations
│
├── multi-agent-framework/       # Phase 3: MAF agent orchestration
│   ├── api_client.py            # OpenAI-compatible HTTP client
│   ├── local_agent_framework.py # CLI harness (health, chat, interactive)
│   ├── maf_example.py           # Simulated two-agent demo
│   ├── maf_integration.py       # Adaptive MAF integration scaffold
│   ├── proxy/proxy.py           # Request normalization proxy
│   └── requirements.txt         # Python dependencies
│
├── .github/instructions/        # This file — agent collaboration rules
│   └── instructions.md
│
└── *.md                         # Architecture & specification documents
```

---

## Build Phases & Agent Assignment

Each roadmap phase maps to specific agents and repositories:

### Phase 1: Story Core → Story Advisor + Technical Advisor
**Repository:** `wp-comfy/`
- WordPress CPT architecture (Project, Story World, Character, Location, Prop, Scene, Shot, Episode, Asset)
- Structured Content Fields (SCF) data models
- Taxonomies and relationships
- REST API foundation (`/api/storyos/v1/`)
- Story Graph entity storage

### Phase 2: Generation Core → Prompt Advisor + Technical Advisor
**Repository:** `wp-comfy/`
- ComfyUI workflow templates
- Prompt storage and management
- Generation history tracking
- Asset versioning
- SCF field integration (positive/negative prompts, resolution, pose/style images)

### Phase 3: Agent Core → Technical Advisor + Executive Orchestrator
**Repository:** `multi-agent-framework/`
- Microsoft Agent Framework integration
- Context routing between agents
- Advisor memory system
- Tool integration layer
- Project context retrieval from Story Graph

### Phase 4: Storyboarding & Production → Production Advisor
**Repository:** `wp-comfy/` (future expansion)
- Storyboard management
- Shot list generation
- Production breakdowns
- Scheduling and call sheets

### Phase 5: Script Ecosystem → Story Advisor + Technical Advisor
- Script import/export (Fountain, FDX, Celtx, Fade In, Markdown)
- Script-to-Story Graph conversion
- Industry tool integration

### Phase 6: Editorial Ecosystem → Editorial Advisor
- EDL export
- Timeline metadata
- Scene/shot mapping
- NLE integrations (XML, AAF)

### Phase 7: Story Graph Intelligence → Story Advisor + Technical Advisor
- Semantic search
- Continuity validation
- Relationship analytics
- Narrative reasoning

### Phase 8: Community Platform → All Agents
- Plugin marketplace
- Workflow marketplace
- Advisor marketplace
- Community templates

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

### WordPress (wp-comfy/)
- Use WordPress Coding Standards (WPCS)
- Custom Post Types registered in a single plugin file
- Structured Content Fields via ACF Pro or MetaBox
- REST API endpoints under `/api/storyos/v1/` namespace
- All CPTs must support the REST API
- Use WordPress nonces for all form submissions
- Sanitize input, escape output

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

### WordPress Tests
- Run PHPUnit tests for CPT registration, REST endpoints, SCF fields
- Store test results in `wp-comfy/test-results/`
- Test CPT creation, retrieval, updates, deletion via REST API
- Test Story Graph relationship queries

### Python Tests
- Run pytest for orchestrator and MAF components
- Store test results in `multi-agent-framework/test-results/`
- Test API client health checks and endpoint fallbacks
- Test proxy request normalization
- Test agent handler functions

### Integration Tests
- End-to-end: WordPress → Python Orchestrator → ComfyUI → WordPress upload
- End-to-end: Agent → Proxy → Model Server → Agent response
- Store logs in `test-results/` directories

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
# WordPress + ComfyUI stack
cd wp-comfy
docker compose up -d

# MAF proxy
cd multi-agent-framework
python proxy/proxy.py

# MAF agent harness
python local_agent_framework.py health
```

### Key URLs
- WordPress: `http://localhost`
- ComfyUI: `http://localhost:8188`
- Model Server: `http://localhost:11434/v1/`
- Proxy: `http://localhost:11435/v1/`
- Orchestrator API: `http://localhost/wp-json/wp/v2/generate`

### Key Files
- Story Graph spec: `Story_Graph_Specification.md`
- Content model: `Content_Model_Specification.md`
- REST API: `REST_API_Specification.md`
- Agent architecture: `Agent_Architecture.md`
- Roadmap: `ROADMAP_StoryOS.md`
- MAF README: `multi-agent-framework/MAF_README.md`
- Copilot config: `multi-agent-framework/COPILOT_CONFIG.md`