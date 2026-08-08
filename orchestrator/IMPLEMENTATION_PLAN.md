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

## Phase D: Storyboarding & Production (Weeks 7-8) 📋 PLANNED

### D1. Storyboard Management

**Files to create:**
- `orchestrator/storyboard.py`
- `orchestrator/workflows/templates/animatic.json`
- `orchestrator/workflows/templates/shot-reference.json`

**Features:**
- [ ] Storyboard CPT integration (query, create, update via WordPress REST API)
- [ ] Shot list generation from Scene CPTs
- [ ] Shot type classification (wide, medium, close-up, tracking, etc.)
- [ ] Storyboard frame generation workflow template (animatic-style)
- [ ] Shot reference sheet generation
- [ ] Asset-to-shot mapping (link generated assets to specific shots)
- [ ] `GET /storyboards` — list storyboards by project/episode
- [ ] `POST /storyboards` — create storyboard from scene data
- [ ] `GET /storyboards/{id}/shots` — get shot list for storyboard
- [ ] `POST /storyboards/{id}/shots/generate` — generate shot reference assets

**Story Graph Integration:**
- Query Scene CPTs → extract shot descriptions
- Build context dict with scene number, title, location, characters, shot type
- Render shot-reference.json template with context
- Upload generated assets back to WordPress as storyboard frames

### D2. Production Breakdowns & Scheduling

**Files to create:**
- `orchestrator/production.py`
- `orchestrator/scheduling.py`

**Features:**
- [ ] Production breakdown engine (analyze scenes for required assets, locations, characters)
- [ ] Resource allocation tracking (which assets needed per scene/day)
- [ ] Call sheet generation (daily shot list with location, cast, props)
- [ ] Production schedule builder (scene ordering, location grouping)
- [ ] Bottleneck identification (scenes waiting on asset generation)
- [ ] `GET /production/breakdown/{project_id}` — get production breakdown
- [ ] `GET /production/schedule/{project_id}` — get production schedule
- [ ] `POST /production/call-sheet` — generate call sheet for day
- [ ] `GET /production/bottlenecks` — identify production blockers

**Production Advisor Integration:**
- Use `ProductionAdvisor.assess_production_status()` for breakdown analysis
- Use `ProductionAdvisor.plan_asset_generation()` for scheduling
- Use `ProductionAdvisor.optimize_pipeline()` for bottleneck resolution

### D3. Asset-to-Scene/Shot Mapping

**Files to modify:**
- `orchestrator/story_graph.py` (add shot/asset relationship queries)
- `orchestrator/asset_lineage.py` (add shot_id field)

**Features:**
- [ ] Track which generated assets belong to which shots
- [ ] Asset versioning per shot (keep all generations, mark best)
- [ ] Shot approval workflow (mark shots as approved/rejected)
- [ ] Asset selection UI support (list all versions for a shot)
- [ ] `GET /shots/{shot_id}/assets` — list assets for a shot
- [ ] `POST /shots/{shot_id}/approve/{asset_id}` — approve an asset for a shot
- [ ] `GET /projects/{id}/asset-summary` — summary of asset status by scene

---

## Phase E: Script Ecosystem (Weeks 9-10) 📋 PLANNED

### E1. Script Import

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

### E2. Script Export

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

### E3. Script-to-Story Graph Conversion

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

## Phase G: Story Graph Intelligence (Weeks 13-14) 📋 PLANNED

### G1. Semantic Search

**Files to create:**
- `orchestrator/search/__init__.py`
- `orchestrator/search/embeddings.py`
- `orchestrator/search/query.py`

**Features:**
- [ ] Character description embedding (for semantic character search)
- [ ] Location description embedding
- [ ] Scene description embedding
- [ ] Asset metadata embedding (prompts, tags, descriptions)
- [ ] Vector store integration (SQLite FTS5 for MVP, optional FAISS for scale)
- [ ] `POST /search/characters` — semantic character search
- [ ] `POST /search/locations` — semantic location search
- [ ] `POST /search/scenes` — semantic scene search
- [ ] `POST /search/assets` — semantic asset search

### G2. Continuity Validation

**Files to create:**
- `orchestrator/continuity.py`

**Features:**
- [ ] Character appearance validation (check if character appears in scenes where they should)
- [ ] Location consistency (scenes in same location should have consistent visual references)
- [ ] Timeline validation (scene ordering matches narrative chronology)
- [ ] Prop tracking (track which props appear in which scenes)
- [ ] Visual consistency checks (compare generated assets for same character/location)
- [ ] `POST /continuity/check/{project_id}` — run continuity check
- [ ] `GET /continuity/issues/{project_id}` — list continuity issues
- [ ] `POST /continuity/issues/{issue_id}/resolve` — mark issue as resolved

### G3. Relationship Analytics

**Files to create:**
- `orchestrator/analytics.py`

**Features:**
- [ ] Character relationship graph (which characters appear together)
- [ ] Scene density analysis (which scenes have most characters/props)
- [ ] Location usage statistics (which locations are used most)
- [ ] Asset generation statistics (by type, status, timestamp)
- [ ] Production progress tracking (scenes completed vs total)
- [ ] `GET /analytics/characters/{project_id}` — character relationship data
- [ ] `GET /analytics/production/{project_id}` — production progress
- [ ] `GET /analytics/assets/{project_id}` — asset generation stats

### G4. Story Consistency Checks

**Files to create:**
- `orchestrator/consistency.py`

**Features:**
- [ ] Worldbuilding consistency (check character/location attributes against world rules)
- [ ] Theme consistency (check scenes align with project themes)
- [ ] Tone consistency (check scene descriptions match project tone)
- [ ] Character voice consistency (check dialogue style across scenes)
- [ ] `POST /consistency/world/{project_id}` — check worldbuilding consistency
- [ ] `POST /consistency/theme/{project_id}` — check theme consistency
- [ ] `POST /consistency/tone/{project_id}` — check tone consistency

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
├── storyboard.py               # Storyboard management (📋 Phase D)
├── production.py               # Production planning (📋 Phase D)
├── scheduling.py               # Scheduling (📋 Phase D)
├── search/                     # Semantic search (📋 Phase G)
│   ├── __init__.py
│   ├── embeddings.py
│   └── query.py
├── continuity.py               # Continuity validation (📋 Phase G)
├── analytics.py                # Relationship analytics (📋 Phase G)
├── consistency.py              # Story consistency (📋 Phase G)
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
    ├── test_workflows.py       # Workflow template tests (📋 Phase D)
    ├── test_story_graph.py     # Story Graph tests (📋 Phase D)
    ├── test_agents.py          # Agent tests (📋 Phase D)
    ├── test_scripts.py         # Script import/export tests (📋 Phase E)
    ├── test_editorial.py       # Editorial tests (📋 Phase F)
    └── test_search.py          # Search tests (📋 Phase G)
```

---

## Implementation Priority

| Priority | Phase | Description | Status |
|----------|-------|-------------|--------|
| P0 | A | Stabilize & Generalize | ✅ COMPLETE |
| P0 | B | Production Hardening | ✅ COMPLETE |
| P1 | C | Agent Integration | ✅ COMPLETE |
| P1 | D | Storyboarding & Production | 📋 PLANNED |
| P2 | E | Script Ecosystem | 📋 PLANNED |
| P2 | F | Editorial Ecosystem | 📋 PLANNED |
| P3 | G | Story Graph Intelligence | 📋 PLANNED |
| P3 | H | Community Platform | 📋 PLANNED |

---

## Immediate Next Steps (Phase D)

1. **Create storyboard workflow templates** (`animatic.json`, `shot-reference.json`)
2. **Build storyboard management** (`storyboard.py`) — query/create storyboards from scenes
3. **Implement production breakdowns** (`production.py`) — analyze scenes for asset requirements
4. **Add scheduling support** (`scheduling.py`) — call sheets, production schedules
5. **Create asset-to-shot mapping** — extend `asset_lineage.py` with shot_id tracking
6. **Write tests** for new modules (`test_storyboard.py`, `test_production.py`)
7. **Update documentation** with Phase D features

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
No storyboard         ─────────────►   Storyboard management 📋
No script support     ─────────────►   Script import/export 📋
No editorial tools    ─────────────►   EDL/NLE integration 📋
No intelligence       ─────────────►   Semantic search + continuity 📋
No community          ─────────────►   Plugin/marketplace system 📋
```
