# StoryOS Roadmap

> Build Your Story Once. Create Everywhere.

This roadmap outlines the planned evolution of StoryOS from a WordPress and ComfyUI integration into a complete open-source storytelling operating system.

---

# Guiding Vision

StoryOS will provide a single platform for:

- Story Development
- Script Management
- AI Asset Generation
- Storyboarding
- Production Planning
- Editorial Workflows
- Story Graph Intelligence

The Story Graph remains the canonical source of truth throughout all phases.

---

# Phase 1: Story Core

## Objective

Establish the foundational Story Graph and WordPress content model.

## Deliverables

- Projects
- Story Worlds
- Characters
- Locations
- Props
- Scenes
- Assets
- WordPress CPT Architecture
- SCF Data Models
- Taxonomies
- REST API Foundation

## Primary Repository

- storyos/

---

# Phase 2: Generation Core

## Objective

Connect story data to AI asset generation workflows.

## Deliverables

- ComfyUI Integration
- Workflow Templates
- Prompt Storage
- Generation History
- Asset Versioning
- Concept Art Generation
- Character Generation
- Environment Generation
- Lookbook Generation
- Storyboard Asset Generation

## Primary Repository

- storyos/

---

# Phase 3: Agent Core

## Objective

Introduce AI advisors and multi-agent orchestration.

## Deliverables

- Microsoft Agent Framework Integration
- Context Routing
- Advisor Memory
- Tool Integration
- Project Context Retrieval

### Initial Advisors

- Project Advisor
- Story Advisor
- Prompt Advisor
- Technical Advisor

## Primary Repository

- maf-agent-framework

---

# Phase 4: Storyboarding & Production (WordPress Plugins)

## Approach

Storyboarding and production planning are handled via WordPress plugins and extensions rather than built-in StoryOS modules. This keeps StoryOS focused on the Story Graph while leveraging the WordPress plugin ecosystem.

## Recommended Plugins

- **Storyboarder** — Visual storyboard management with shot frames
- **The Events Calendar / Modern Tribe** — Production scheduling and call sheets
- **Advanced Custom Fields (ACF)** — Custom shot list and production breakdown fields
- **WP All Import/Export** — Import/export production data
- **Custom Post Type UI** — Extend CPTs for storyboards, shot lists, call sheets

## Integration

- Story Graph entities (Scenes, Shots, Assets) remain the source of truth
- Plugins query Story Graph via WordPress REST API
- Asset-to-Scene/Shot mapping via CPT relationships
- Generated assets accessible through WordPress media library

---

# Phase 5: Script Ecosystem ⏸️ ON HOLD

## Objective

Integrate with industry-standard writing tools and enable bidirectional script synchronization.

## Celtx Integration (Primary)

Full bi-directional synchronization with Celtx via the Celtx GEM API:

- **CPT Synchronization**: Projects, Characters, Locations, Scenes, Shots sync between StoryOS and Celtx
- **Bidirectional Sync**: Changes in either platform propagate to the other
- **API Authentication**: API key, Basic Auth, and Cookie Auth support
- **Settings UI**: WordPress admin interface for Celtx API credentials
- **REST API**: Sync endpoints via `wp-json/storyos-celtx/v1/*`
- **Element Mapping**: Persistent StoryOS ↔ Celtx ID mapping stored in post meta

## Import Support (Planned)

- [ ] Fountain — scene headings, action, dialogue, character extraction
- [ ] Final Draft (FDX) — XML parsing → Story Graph entities
- [ ] Fade In — import screenplay format
- [ ] Highland — import screenplay format
- [ ] Story Architect — import project data
- [ ] Markdown — basic scene detection
- [ ] PDF — text extraction (future)

## Export Support (Planned)

- [ ] Fountain — export Scene CPTs to Fountain syntax
- [ ] Screenplay — formatted screenplay export
- [ ] Shooting Script — scene numbers, shot descriptions, asset references
- [ ] Production Script — call sheet data, location info
- [ ] Markdown — structured markdown export

## Script-to-Story Graph Conversion (Planned)

- [ ] Parse script → extract entities (characters, locations, props)
- [ ] Parse script → extract scene structure and numbering
- [ ] Auto-create Story Graph entities from script content
- [ ] Entity deduplication and relationship inference
- [ ] Import preview before committing

## Current Status (as of 2026-08-08)

**On Hold.** Celtx bi-directional sync is operational but no further development planned at this time. Import/export for Fountain, FDX, and other screenplay formats deferred.

---

# Phase 6: Editorial Ecosystem ⏸️ ON HOLD

## Objective

Extend StoryOS into post-production workflows.

## Deliverables

- ✅ EDL Export (CMX 3600 ASCII & SMPTE 436m XML)
- ✅ Drop-Frame Timecode for 29.97/59.94fps NTSC
- ✅ Frame Handles (Pre-Roll / Post-Roll) for Unreal Engine
- ✅ 32-Character Clip Names for Premiere Pro
- ✅ Multi-Track Support (Video + Audio)
- ✅ NLE Compatibility (Unreal Engine, Premiere Pro, DaVinci Resolve, Avid, FCP)
- [ ] Timeline Metadata
- [ ] Scene Mapping
- [ ] Shot Mapping
- [ ] Asset References

## Future Deliverables

- [ ] AAF Export
- [ ] OMF Export
- [ ] NLE-Specific Plugins (Premiere Pro Panel, DaVinci Resolve Plugin)
- [ ] Direct Media Linking (EDL with absolute file paths)

## Current Status (as of 2026-08-08)

**On Hold.** EDL export is implemented and functional. Timeline metadata, scene/shot mapping, asset references, AAF/OMF export, and NLE-specific plugins are deferred.

---

# Phase 7: Story Graph Intelligence ✅ COMPLETE

## Objective

Transform StoryOS into a narrative intelligence platform.

## Deliverables

- ✅ Semantic Search (hybrid/semantic/keyword modes, WP_Query integration, admin bar)
- ✅ Continuity Validation (auto-check on save, admin panel, severity levels)
- ✅ Relationship Analytics (network density, co-occurrence, isolated entity detection)
- ✅ Knowledge Graph Queries (orchestrator `/intelligence/*` endpoints)
- ✅ Story Consistency Checks (structured issue storage, filter by error/warning/info)
- ✅ Narrative Reasoning (orchestrator intelligence engine)

## Planned Improvements

- [ ] Incremental indexing (WP-Cron based) — currently full re-index on each call
- [ ] Embedding cache with TTL-based invalidation
- [ ] Search result caching (WordPress transients)
- [ ] Performance benchmarks with production-scale data
- [ ] E2E tests with Playwright
- [ ] Real-time search suggestions (debounced input)
- [ ] Knowledge graph database integration (Neo4j) — future

## Current Status (as of 2026-08-08)

Phase 7 is fully implemented and operational. Core intelligence engine (story_intelligence.py) provides hybrid search, continuity validation (6 check categories), and relationship analytics. REST endpoints are live in the orchestrator FastAPI service. Planned improvements focus on performance and scalability.

---

# Phase 8: AI Editor ✅ COMPLETE

## Objective

Connect the WordPress content editor to local/API-driven LLMs and the multi-agent framework, enabling creators to interact with AI advisors directly from the WordPress admin UI.

## Deliverables

- ✅ WordPress Gutenberg AI Editor panel (React sidebar, CSS/JS assets)
- ✅ 8 REST API endpoints (`/storyos/v1/ai/chat`, `/ai/analyze`, `/ai/generate`, `/ai/continuity`, `/ai/context`, `/ai/agents`, `/ai/settings`, `/ai/health`)
- ✅ Local LLM integration (Qwen3.6 via vLLM/Ollama)
- ✅ Cloud LLM fallback (OpenAI, Anthropic)
- ✅ Multi-agent framework bridge (32+ specialized agents)
- ✅ Context builder for Story Graph data (characters, scenes, projects)
- ✅ Agent routing system (keyword-based: story, prompt, production, technical, editorial)
- ✅ AI Settings configuration UI
- ✅ Agent skills loader (`.agent.md` parsing)
- ✅ Response caching & rate limiting
- ✅ WordPress Abilities API (4 Tool, 3 Resource, 2 Prompt abilities)
- ✅ MCP integration documentation (VS Code, Cursor, Claude)

## Planned Polish

- [ ] Clone WordPress/agent-skills repository
- [ ] Copy MAF agent `.agent.md` files to plugin's agents directory
- [ ] Install & configure WordPress MCP Adapter plugin
- [ ] Test MCP discovery flow (discover-abilities, execute-ability)
- [ ] Configure MCP client connections (VS Code, Cursor)
- [ ] Keyboard shortcuts for AI panel
- [ ] Content generation actions (insert into editor)
- [ ] AI-generated content labeling
- [ ] Performance optimization (caching, rate limiting)
- [ ] Accessibility audit
- [ ] Security audit (input sanitization, output escaping)
- [ ] E2E tests with real content

## Integration Points

- WordPress/agent-skills repository — expert WordPress knowledge for AI assistants (documented, not yet cloned)
- Orchestrator FastAPI service — multi-agent orchestration
- Qwen3.6-35B vLLM instance — local LLM backend
- Multi-agent framework — 32+ specialized filmmaking agents

## Detailed Specification

See [Phase_8_AI_Editor.md](Phase_8_AI_Editor.md) for full architecture, implementation plan, and testing strategy.

---

# Phase 9: Community Platform ⏸️ ON HOLD

## Objective

Grow the StoryOS ecosystem.

## Deliverables

- [ ] Plugin Marketplace
- [ ] Workflow Marketplace
- [ ] Advisor Marketplace
- [ ] Community Templates
- [ ] Educational Resources
- [ ] Contributor Programs

---

# Success Metrics

## Community

- Contributors
- GitHub Stars
- Pull Requests
- Community Discussions

## Product

- Projects Created
- Story Graphs Managed
- Assets Generated
- Advisors Used

## Ecosystem

- Third-Party Integrations
- Community Plugins
- Advisor Extensions

---

# Long-Term Goal

StoryOS is not another AI generation platform.

StoryOS is an open storytelling operating system that enables creators to manage stories, assets, production, and editorial workflows from a unified Story Graph.

**The future of storytelling is structured.**

**The future of storytelling is open.**
