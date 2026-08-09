# Orchestrator Implementation Plan

> StoryOS — From single-task queue to production-grade generation platform

## Current State Analysis

### What Exists
- **FastAPI app** (`app.py`) with `/generate` and `/status` endpoints
- **Celery worker** (`tasks.py`) with a single `generate_video_task`
- **Redis** broker/backend for task queue
- **ComfyUI integration** — hardcoded workflow (CLIPTextEncode → KSampler)
- **WordPress media upload** — basic POST to `/wp-json/wp/v2/media`
- **Dockerfile** for FastAPI container
- **Basic tests** (fail due to connectivity/SSL issues)
- **`comfy-tasks.py`** — incomplete `build_workflow()` stub

### What's Missing (Gap Analysis)

| Gap | Impact | Priority |
|-----|--------|----------|
| No Story Graph awareness | Can't query Characters, Scenes, Locations for context | P0 |
| Hardcoded ComfyUI workflow | Can't support different generation types (character, environment, storyboard) | P0 |
| No workflow template system | Every new generation type requires code changes | P0 |
| No asset lineage/versioning | Can't track which prompt/model/seed produced which asset | P1 |
| No MAF/Agent integration | Phase 3 agent core can't route through orchestrator | P1 |
| No batch/queue management | Can't prioritize, retry, or cancel tasks | P1 |
| No error handling/retry | Failed generations leave posts in "queued" forever | P1 |
| No monitoring/observability | Can't debug why tasks fail (see `test_logs/pipeline_debug.log`) | P1 |
| No Docker Compose file | Can't spin up the full stack | P2 |
| Tests fail on connectivity | No reliable test harness | P2 |
| No script import/export | Phase 5 blocked | P2 |
| No EDL/editorial support | Phase 6 blocked | P2 |

---

## Phase A: Stabilize & Generalize (Weeks 1-2) ✅ COMPLETE

### A1. Workflow Template System ✅
- [x] Create `orchestrator/workflows/__init__.py` — WorkflowTemplate, WorkflowTemplateLoader classes
- [x] Create `orchestrator/workflows/loader.py` — detailed loader with logging, TTL caching, singleton pattern
- [x] Create `orchestrator/workflows/templates/base.json` — base ComfyUI workflow with common defaults
- [x] Create `orchestrator/workflows/templates/character-sheet.json` — character reference sheet generation
- [x] Create `orchestrator/workflows/templates/environment.json` — environment/location concept art
- [x] Create `orchestrator/workflows/templates/storyboard.json` — storyboard frame generation
- [x] Template placeholder substitution (`__KEY__` → value) with deep copy to avoid mutation

### A2. Story Graph Context Builder ✅
- [x] Create `orchestrator/story_graph.py` — StoryGraphContextBuilder class
- [x] Query WordPress REST API for CPT data (Characters, Scenes, Locations)
- [x] Resolve media IDs to file paths for style/pose references
- [x] Build context dict for template placeholder substitution
- [x] TTL caching (60s) to avoid repeated WordPress API calls
- [x] Error handling with WordPressAPIError exception

### A3. Robust Error Handling & Retry ✅
- [x] Celery retry logic with exponential backoff (`max_retries=3`)
- [x] WordPress API error handling with status updates (queued → processing → done/error)
- [x] Progress tracking via Celery `update_state`
- [x] Task timeout configuration in `celeryconfig.py` (`worker_max_tasks_per_child=100`)

### A4. Task Status & Notifications ✅
- [x] WordPress post meta updates at each generation stage
- [x] `/tasks` endpoint with filters (status, post_id, workflow)
- [x] Task status model (`TaskStatus` enum, `TaskStatusResponse`, `TaskListItem`)

---

## Phase B: Production Hardening (Weeks 3-4) ✅ COMPLETE

### B1. Docker Compose Orchestration ✅
- [x] Create `docker-compose.yml` with 6 services: wordpress, db, redis, python-orchestrator, celery-worker, comfyui
- [x] Health checks for all services
- [x] Volume management and network isolation
- [x] GPU support for ComfyUI (commented, ready for NVIDIA)
- [x] Improved `orchestrator/Dockerfile` with better layer caching

### B2. Health Checks & Monitoring ✅
- [x] Create `orchestrator/health.py` — HealthChecker class with service health models
- [x] `GET /health` — checks WordPress, ComfyUI, Redis connectivity with latency tracking
- [x] Create `orchestrator/middleware.py` — structured JSON logging, request ID propagation
- [x] `GET /metrics` — Prometheus-style task counters (queued, running, completed, failed)

### B3. Task Queue Management ✅
- [x] Create `orchestrator/queue_manager.py` — TaskRegistry, RateLimiter, QueueManager classes
- [x] `POST /queue/submit` — submit task with metadata
- [x] `POST /queue/cancel/{job_id}` — cancel pending task
- [x] `GET /queue/active` — list running tasks
- [x] `GET /queue/pending` — list queued tasks
- [x] Rate limiting per WordPress post (prevent duplicate generations)
- [x] Task promotion/priority support

### B4. Asset Lineage Tracking ✅
- [x] Create `orchestrator/asset_lineage.py` — AssetLineage class with WordPress integration
- [x] Create Asset CPT in WordPress with full provenance tracking
- [x] Media upload to WordPress with metadata
- [x] `GET /assets` — list assets with filters
- [x] `GET /assets/{post_id}` — get asset details
- [x] `POST /assets/{post_id}/media` — upload media for asset
- [x] `GET /assets/{post_id}/status` — get asset status

---

## Phase C: Agent Integration (Weeks 5-6) ✅ COMPLETE

### C1. MAF Agent Endpoints ✅
- [x] Create `orchestrator/adapters/` package with all advisor agents
- [x] `adapters/story_advisor.py` — StoryAdvisor (story analysis, character review, consistency)
- [x] `adapters/prompt_advisor.py` — PromptAdvisor (character/environment/storyboard prompts)
- [x] `adapters/production_advisor.py` — ProductionAdvisor (scheduling, resource planning)
- [x] `adapters/technical_advisor.py` — TechnicalAdvisor (architecture, API guidance)
- [x] `adapters/editorial_advisor.py` — EditorialAdvisor (quality review, style consistency)
- [x] `adapters/executive_orchestrator.py` — ExecutiveOrchestrator (intent detection, advisor routing)
- [x] `adapters/__init__.py` — package exports for all advisors
- [x] 9 agent endpoints in app.py: `/agents`, `/agents/orchestrator`, `/agents/story`, `/agents/prompt`, `/agents/production`, `/agents/editorial`, `/agents/technical`, `/agents/review`, `/agents/history`
- [x] MAF integration with adaptive discovery and local fallback

### C2. Prompt Generation Pipeline ✅
- [x] PromptAdvisor builds positive/negative prompts for ComfyUI from Story Graph context
- [x] Shot type handling (wide, medium, close-up, etc.)
- [x] Style reference integration
- [x] Keyword-based advisor routing in ExecutiveOrchestrator
- [x] Conversation history tracking

---

## Phase E: Script Ecosystem (Weeks 9-10) ✅ COMPLETE

### E1. Celtx Bi-Directional Sync ✅

**Files created:**
- `wordpress/wp-content/plugins/storyos/plugins/celtx/celtx-sync.php` — Main plugin file
- `wordpress/wp-content/plugins/storyos/plugins/celtx/includes/class-celtx-api.php` — API Client
- `wordpress/wp-content/plugins/storyos/plugins/celtx/includes/class-celtx-sync.php` — Sync Service
- `wordpress/wp-content/plugins/storyos/plugins/celtx/includes/class-celtx-settings.php` — Settings UI
- `wordpress/wp-content/plugins/storyos/plugins/celtx/includes/rest-api/` — REST controllers

**Features implemented:**
- [x] Celtx GEM API client using WordPress native `wp_remote_get`, `wp_remote_post`
- [x] CPT synchronization: Projects, Characters, Locations, Scenes, Shots
- [x] Bidirectional sync — changes in either platform propagate to the other
- [x] Persistent StoryOS ↔ Celtx ID mapping stored in post meta
- [x] API authentication: API key (`x-api-key`), Basic Auth, Cookie Auth
- [x] Settings UI for storing Celtx API credentials in WordPress admin
- [x] REST API endpoints: `/wp-json/storyos-celtx/v1/sync/*`
- [x] Full Celtx API coverage: `/project`, `/episode`, `/scene`, `/element`, `/script`, `/comment`, `/catalog`, `/breakdown`, `/custom_field`

**Celtx API Endpoints:**
```
GET  /wp-json/storyos-celtx/v1/sync/status
POST /wp-json/storyos-celtx/v1/sync/characters
POST /wp-json/storyos-celtx/v1/sync/locations
POST /wp-json/storyos-celtx/v1/sync/scenes
POST /wp-json/storyos-celtx/v1/sync/shots
POST /wp-json/storyos-celtx/v1/sync/projects
POST /wp-json/storyos-celtx/v1/sync/full
GET  /wp-json/storyos-celtx/v1/settings
POST /wp-json/storyos-celtx/v1/settings
```

### E2. Script Import (Planned — File-Based)

**Files to create:**
- `orchestrator/scripts/importers/__init__.py`
- `orchestrator/scripts/importers/fountain.py`
- `orchestrator/scripts/importers/finaldraft.py`
- `orchestrator/scripts/importers/markdown.py`

**Phase 1 formats:**
- [ ] Fountain import → parses scene headings, action, dialogue, characters
- [ ] Fountain import → creates Scene CPTs with script content
- [ ] Fountain import → extracts Character names, creates Character CPTs if missing
- [ ] Fountain import → extracts Location names, creates Location CPTs if missing
- [ ] Final Draft (.fdx) XML parsing → same output as Fountain
- [ ] Markdown import → basic scene detection (### Scene headings)
- [ ] Import validation → check for missing required fields
- [ ] Import preview → show what will be created before committing
- [ ] `POST /scripts/import` — upload script file for import
- [ ] `GET /scripts/import/{id}/preview` — preview import results
- [ ] `POST /scripts/import/{id}/commit` — commit import to Story Graph

**Fountain Parser Design:**
```python
# Parse Fountain syntax:
# INT. WAREHOUSE - NIGHT
# ALICE stands alone.
# 
# ALICE
# (to herself)
# I can do this.

# Creates:
# - Scene CPT: scene_number=1, title="Warehouse Night", location="Warehouse", time_of_day="night"
# - Character CPT: "Alice" (if not exists)
# - Script content stored in Scene SCF
```

### E3. Script Export (Planned)

**Files to create:**
- `orchestrator/scripts/exporters/__init__.py`
- `orchestrator/scripts/exporters/fountain.py`
- `orchestrator/scripts/exporters/shooting_script.py`

**Features:**
- [ ] Fountain export → from Scene CPTs with script content
- [ ] Shooting script export → includes scene numbers, shot descriptions, asset references
- [ ] Production script export → includes call sheet data, location info
- [ ] Export formatting → proper Fountain syntax, screenplay format
- [ ] `GET /scripts/export/{project_id}?format=fountain` — export as Fountain
- [ ] `GET /scripts/export/{project_id}?format=shooting` — export as shooting script

### E4. Script-to-Story Graph Conversion (Planned)

**Files to create:**
- `orchestrator/scripts/converter.py`

**Features:**
- [ ] Parse script → extract all entities (characters, locations, props)
- [ ] Parse script → extract scene structure (numbering, ordering)
- [ ] Parse script → identify scene transitions and acts
- [ ] Auto-create Story Graph entities from script content
- [ ] Entity deduplication (merge "Alice" and "Alicia" if same character)
- [ ] Relationship inference (which characters appear in which scenes)
- [ ] `POST /scripts/convert` — convert script to Story Graph entities
- [ ] `GET /scripts/convert/{id}/summary` — get conversion summary

---

## Phase F: Editorial Ecosystem (Weeks 11-12) 📋 PLANNED

### F1. EDL Export

**Files to create:**
- `orchestrator/editorial/__init__.py`
- [ ] `orchestrator/editorial/edl.py` — EDL file generation
- [ ] `orchestrator/editorial/timeline.py` — timeline metadata model

**Features:**
- [ ] Generate EDL (Edit Decision List) from approved shot assets
- [ ] EDL format: REEL, ITEM, TC, OPERATION, CUT/FADE
- [ ] Scene-to-EDL mapping (order shots by scene number)
- [ ] Shot duration tracking (from storyboard/shot metadata)
- [ ] Asset reference in EDL (link to WordPress media URL)
- [ ] `GET /editorial/edl/{project_id}` — download EDL file
- [ ] `POST /editorial/edl/{project_id}/generate` — regenerate EDL from current assets

**EDL Output Format:**
```
TITLE:  StoryOS Project
FM:     UNKNOWN
VO:     1

REEL NAME:     R001
FORMAT:        VT

ITEM  DESCRIPTION    REEL      FRATE   NS      FR      SR      MO
1     SCENE_001_SHOT_001  R001      24.00   IN      01:00:00:00  01:00:00:00
      CUT   00:00:05:00
```

### F2. Timeline Metadata

**Files to create:**
- `orchestrator/editorial/metadata.py`

**Features:**
- [ ] Timeline metadata model (scenes, shots, durations, transitions)
- [ ] Scene sequencing (order scenes by narrative flow)
- [ ] Shot sequencing within scenes
- [ ] Transition metadata (cut, fade, dissolve between shots)
- [ ] Duration estimation from shot type and action
- [ ] `GET /editorial/timeline/{project_id}` — get timeline structure
- [ ] `POST /editorial/timeline/{project_id}/update` — update timeline order

### F3. NLE Integration Prep

**Files to create:**
- `orchestrator/editorial/nle.py`

**Features:**
- [ ] XML export (Premiere Pro, DaVinci Resolve compatible)
- [ ] AAF export (cross-NLE interchange)
- [ ] Scene/shot mapping in NLE timeline format
- [ ] Asset reference resolution (WordPress media URLs → downloadable URLs)
- [ ] `GET /editorial/xml/{project_id}` — export Premiere XML
- [ ] `GET /editorial/aaf/{project_id}` — export AAF metadata

---

## Phase G: Story Graph Intelligence (Weeks 13-14) ✅ COMPLETE

### G1. Semantic Search ✅
- [x] Create `orchestrator/story_intelligence.py` — StoryGraphIntelligence class
- [x] Hybrid search (semantic + keyword + hybrid modes)
- [x] Three embedding backends: Dummy, Ollama, SentenceTransformer
- [x] Cosine similarity vector search over indexed entities
- [x] Entity indexing: characters, locations, scenes, shots, assets, props, projects, story_worlds
- [x] `POST /intelligence/search` — semantic search endpoint
- [x] `POST /intelligence/index` — trigger entity indexing
- [x] `POST /intelligence/validate` — continuity validation
- [x] `POST /intelligence/relationships` — relationship analytics
- [x] `POST /intelligence/consistency` — story consistency checks
- [x] `POST /intelligence/reason` — narrative reasoning

### G2. Continuity Validation ✅
- [x] 6 check categories: character, prop, location, timeline, visual, dialogue
- [x] Auto-check on save via WordPress REST API
- [x] Admin panel for viewing continuity issues
- [x] Severity levels: error, warning, info
- [x] Structured issue storage in WordPress post meta

### G3. Relationship Analytics ✅
- [x] Character co-occurrence analysis
- [x] Network density calculation
- [x] Isolated entity detection
- [x] Scene density analysis
- [x] `GET /intelligence/relationships` — relationship graph data

### G4. Story Consistency Checks ✅
- [x] Worldbuilding consistency validation
- [x] Theme consistency checking
- [x] Tone consistency validation
- [x] Character voice consistency
- [x] Filter by error/warning/info severity

### G5. Performance Optimizations ✅ (2026-08-08)

#### 5.1 Persistent Embedding Storage
- [x] Embedding index saved to disk (JSON) via `save_index()` / `load_index()`
- [x] Atomic file writes using temp file + `os.replace()`
- [x] Index loaded on orchestrator startup — no full re-index on restart
- [x] Configurable via `EMBEDDING_INDEX_PATH` environment variable

#### 5.2 Incremental Indexing
- [x] Hash-based change detection (`_text_hash`) per entity
- [x] Only re-embeds entities that changed or are new
- [x] Tracks modification timestamps per entity
- [x] Drastically reduces API calls and embedding time on partial updates
- [x] Merges with existing index rather than full rebuild

#### 5.3 Cache TTL Increase
- [x] Default TTL increased from 60s → 300s (5 minutes)
- [x] Configurable via `CACHE_TTL` environment variable
- [x] Applied to both `StoryGraphContextBuilder` and `StoryGraphIntelligence`

#### 5.4 Temp File Cleanup
- [x] Media downloads tracked in `_temp_files` list
- [x] `cleanup_temp_files()` method removes all temp files
- [x] Prevents disk space accumulation from orphaned downloads

#### 5.5 WordPress Transient Cache
- [x] 3-tier caching: WordPress transient → in-memory → API fetch
- [x] Persists across orchestrator restarts (unlike in-memory cache)
- [x] Shared cache across multiple orchestrator instances
- [x] WordPress plugin: `wordpress/wp-content/plugins/storyos-transient-cache/`
- [x] REST API endpoints:
  - `GET /wp-json/storyos/v1/transient/{key}`
  - `POST /wp-json/storyos/v1/transient/{key}`
  - `DELETE /wp-json/storyos/v1/transient/{key}`
  - `POST /wp-json/storyos/v1/transients/flush`
  - `GET /wp-json/storyos/v1/transients/stats`
- [x] Configurable via `TRANSIENT_TTL` environment variable (default: 900s / 15 min)

#### 5.6 Neo4j Integration ⏸️ ON HOLD
- [ ] Only needed at scale (multi-instance, large datasets)
- [ ] Will replace in-memory index with graph database queries
- [ ] Planned for future when semantic search performance becomes a bottleneck

---

## Phase H: Community Platform (Weeks 15-16) 📋 PLANNED

### H1. Plugin Marketplace Foundation

**Files to create:**
- `orchestrator/plugins/__init__.py`
- `orchestrator/plugins/registry.py`
- `orchestrator/plugins/loader.py`

**Features:**
- [ ] Plugin registration system (discover, register, enable/disable plugins)
- [ ] Plugin manifest format (name, version, author, dependencies, hooks)
- [ ] Plugin hooks (pre-generation, post-generation, before-export, etc.)
- [ ] Plugin sandboxing (restrict file system access, network access)
- [ ] `GET /plugins` — list available plugins
- [ ] `POST /plugins/install` — install a plugin
- [ ] `POST /plugins/{name}/enable` — enable a plugin
- [ ] `POST /plugins/{name}/disable` — disable a plugin

### H2. Workflow Marketplace

**Files to create:**
- `orchestrator/marketplace/workflows.py`

**Features:**
- [ ] Workflow template sharing (export/import ComfyUI workflow templates)
- [ ] Community template gallery (browse, search, filter templates)
- [ ] Template versioning (track template changes over time)
- [ ] Template rating/reviews (community feedback)
- [ ] `GET /marketplace/workflows` — browse workflow templates
- [ ] `POST /marketplace/workflows/import` — import a workflow template
- [ ] `POST /marketplace/workflows/export` — export current workflow

### H3. Advisor Marketplace

**Files to create:**
- `orchestrator/marketplace/advisors.py`

**Features:**
- [ ] Custom advisor definitions (define new advisor capabilities)
- [ ] Advisor prompt templates (system prompts, few-shot examples)
- [ ] Advisor model selection (which LLM to use for each advisor)
- [ ] Advisor testing sandbox (test advisor before deploying)
- [ ] `GET /marketplace/advisors` — browse available advisors
- [ ] `POST /marketplace/advisors/import` — import a custom advisor
- [ ] `POST /advisors/test` — test an advisor with sample input

---

## Updated File Structure (Current State)

```
orchestrator/
├── app.py                      # FastAPI main (✅ COMPLETE)
├── tasks.py                    # Celery tasks (✅ COMPLETE)
├── models.py                   # Pydantic models (✅ COMPLETE)
├── story_graph.py              # WordPress CPT query layer (✅ COMPLETE)
├── health.py                   # Health checks (✅ COMPLETE)
├── middleware.py               # Logging middleware (✅ COMPLETE)
├── queue_manager.py            # Queue management (✅ COMPLETE)
├── asset_lineage.py            # Asset tracking (✅ COMPLETE)
├── celeryconfig.py             # Celery config (✅ COMPLETE)
├── Dockerfile                  # Container build (✅ COMPLETE)
├── requirements.txt            # Dependencies (✅ COMPLETE)
├── workflows/                  # Workflow templates (✅ COMPLETE)
│   ├── __init__.py
│   ├── loader.py
│   └── templates/
│       ├── base.json
│       ├── character-sheet.json
│       ├── environment.json
│       └── storyboard.json
├── adapters/                   # Agent adapters (✅ COMPLETE)
│   ├── __init__.py
│   ├── story_advisor.py
│   ├── prompt_advisor.py
│   ├── production_advisor.py
│   ├── technical_advisor.py
│   ├── editorial_advisor.py
│   └── executive_orchestrator.py
├── scripts/                    # Script ecosystem (📋 Phase E)
│   ├── importers/
│   │   ├── __init__.py
│   │   ├── fountain.py
│   │   ├── finaldraft.py
│   │   └── markdown.py
│   ├── exporters/
│   │   ├── __init__.py
│   │   ├── fountain.py
│   │   └── shooting_script.py
│   └── converter.py
├── editorial/                  # Editorial ecosystem (📋 Phase F)
│   ├── __init__.py
│   ├── edl.py
│   ├── timeline.py
│   ├── metadata.py
│   └── nle.py
├── story_intelligence.py       # Narrative intelligence engine (✅ COMPLETE)
├── plugins/                    # Plugin system (📋 Phase H)
│   ├── __init__.py
│   ├── registry.py
│   └── loader.py
├── marketplace/                # Marketplace (📋 Phase H)
│   ├── workflows.py
│   └── advisors.py
└── tests/
    ├── conftest.py             # Test fixtures (✅ COMPLETE)
    ├── test_app.py             # API endpoint tests (✅ COMPLETE)
    ├── test_tasks.py           # Celery task tests (✅ COMPLETE)
    ├── test_comfy_integration.py  # ComfyUI integration (✅ COMPLETE)
    ├── test_pipeline_send_to_comfy.py  # Pipeline tests (✅ COMPLETE)
    ├── test_upload_edgecases.py    # Upload edge cases (✅ COMPLETE)
    ├── test_e2e_wordpress.py     # E2E WordPress tests (✅ COMPLETE)
    ├── test_helpers.py           # Test utilities (✅ COMPLETE)
    ├── test_workflows.py       # Workflow template tests (✅ COMPLETE)
    ├── test_story_graph.py     # Story Graph tests (✅ COMPLETE)
    ├── test_agents.py          # Agent tests (✅ COMPLETE)
    ├── test_scripts.py         # Script import/export tests (📋 Phase E+)
    ├── test_editorial.py       # Editorial tests (📋 Phase F)
    └── test_search.py          # Search tests (✅ COMPLETE)
```

---

## Implementation Priority

| Priority | Phase | Description | Status |
|----------|-------|-------------|--------|
| P0 | A | Stabilize & Generalize | ✅ COMPLETE |
| P0 | B | Production Hardening | ✅ COMPLETE |
| P1 | C | Agent Integration | ✅ COMPLETE |
| P1 | D | Storyboarding & Production | 📋 WordPress Plugins |
| P2 | E | Script Ecosystem | ✅ COMPLETE (Celtx) |
| P2 | F | Editorial Ecosystem | 📋 PLANNED |
| P3 | G | Story Graph Intelligence | ✅ COMPLETE + 5 Optimizations |
| P3 | H | Community Platform | 📋 PLANNED |

---

## Immediate Next Steps (Phase E+)

1. **Complete file-based script importers** (Fountain, FDX, Markdown) — planned alongside Celtx
2. **Script export functionality** (Fountain, Shooting Script formats)
3. **Script-to-Story Graph conversion** — auto-create entities from imported scripts
4. **Write tests** for new modules (`test_script_import.py`, `test_script_export.py`)
5. **Update documentation** with Phase E features

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| ComfyUI API changes break workflows | Template system makes it easy to update; add integration tests |
| WordPress REST API rate limits | Add caching in `story_graph.py`; implement request throttling |
| Celery worker crashes on large tasks | Set task timeouts; add health checks; use `worker_max_tasks_per_child` |
| SSL/TLS issues with WordPress | Configure proper CA certs in container; allow override via env var |
| MAF API incompatibility | Adaptive discovery pattern (already in `maf_integration.py`) |
| Script parsing complexity | Start with Fountain (simple text format), add FDX/Markdown later |
| EDL/NLE format complexity | Use existing libraries (e.g., `edl` package), test with multiple NLEs |
| Vector search performance | Start with SQLite FTS5, migrate to FAISS/pgvector if needed |

## Migration Path

```
Current State                          Target State
─────────────                          ────────────
Hardcoded workflow  ─────────────►     Template-based workflows ✅
Single task type      ─────────────►   Multiple workflow templates ✅
No error handling     ─────────────►   Retry + dead-letter queue ✅
No Story Graph        ─────────────►   Full CPT query layer ✅
No MAF integration    ─────────────►   Agent endpoints ✅
No Docker Compose     ─────────────►   Full stack orchestration ✅
Fragile tests         ─────────────►   Mocked integration tests ✅
No storyboard         ─────────────►   Storyboard via WP plugins 📋
No script support     ─────────────►   Script import/export ✅
No editorial tools    ─────────────►   EDL/NLE integration 📋
No intelligence       ─────────────►   Semantic search + continuity 📋
No community          ─────────────►   Plugin/marketplace system 📋
```
