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

# Phase 5: Script Ecosystem ✅ COMPLETE

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

---

# Phase 6: Editorial Ecosystem

## Objective

Extend StoryOS into post-production workflows.

## Deliverables

- ✅ EDL Export (CMX 3600 ASCII & SMPTE 436m XML)
- ✅ Drop-Frame Timecode for 29.97/59.94fps NTSC
- ✅ Frame Handles (Pre-Roll / Post-Roll) for Unreal Engine
- ✅ 32-Character Clip Names for Premiere Pro
- ✅ Multi-Track Support (Video + Audio)
- ✅ NLE Compatibility (Unreal Engine, Premiere Pro, DaVinci Resolve, Avid, FCP)
- Timeline Metadata
- Scene Mapping
- Shot Mapping
- Asset References

## Future Deliverables

- AAF Export
- OMF Export
- NLE-Specific Plugins (Premiere Pro Panel, DaVinci Resolve Plugin)
- Direct Media Linking (EDL with absolute file paths)

---

# Phase 7: Story Graph Intelligence

## Objective

Transform StoryOS into a narrative intelligence platform.

## Deliverables

- Semantic Search
- Continuity Validation
- Relationship Analytics
- Knowledge Graph Queries
- Story Consistency Checks
- Narrative Reasoning

---

# Phase 8: AI Editor

## Objective

Connect the WordPress content editor to local/API-driven LLMs and the multi-agent framework, enabling creators to interact with AI advisors directly from the WordPress admin UI.

## Deliverables

- WordPress Gutenberg AI Editor panel
- REST API endpoints for AI communication
- Local LLM integration (Qwen3.6 via vLLM)
- Cloud LLM fallback (OpenAI, Anthropic)
- WordPress/agent-skills integration
- Multi-agent framework bridge
- Context builder for Story Graph data
- Agent routing system
- AI Settings configuration UI

## Integration Points

- WordPress/agent-skills repository — expert WordPress knowledge for AI assistants
- Orchestrator FastAPI service — multi-agent orchestration
- Qwen3.6-35B vLLM instance — local LLM backend
- Multi-agent framework — 32+ specialized filmmaking agents

## Detailed Specification

See [Phase_8_AI_Editor.md](Phase_8_AI_Editor.md) for full architecture, implementation plan, and testing strategy.

---

# Phase 9: Community Platform

## Objective

Grow the StoryOS ecosystem.

## Deliverables

- Plugin Marketplace
- Workflow Marketplace
- Advisor Marketplace
- Community Templates
- Educational Resources
- Contributor Programs

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
