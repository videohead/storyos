# World Graph Studio Build Instructions

> Your ideas. Your assets. No credits needed.

This file defines the active development conventions for World Graph Studio. World Graph Studio is a
WordPress application whose canonical data model is the Story Graph. ComfyUI is
an optional external generation service used by the relevant plugin.

## Local Entry Points

When Lando and docker containers are already running, use these service entry points for local
validation:

- WordPress app: http://worldgraph.lndo.site/ or https://worldgraph.lndo.site/
- ComfyUI service: http://localhost:8188
- ComfyUI MCP: http://localhost:8188
- Local LLM: http://localhost:11434

If the environment is already running, prefer `lando info` to refresh the URLs
before testing. WordPress is the application and control plane; do not assume a
separate Python, queue, or orchestration service exists.

## Lando Runtime Ownership

Use the service that owns the runtime required by the command:

| Runtime | Lando service | Project path |
| --- | --- | --- |
| WordPress, PHP, and WP-CLI | `appserver` | `/app/wordpress` |
| Node.js, npm, Playwright, and JavaScript checks | `cli` | `/app` |
| MariaDB | `database` | N/A |

WP-CLI belongs in `appserver`, not the Node-based `cli` service. The intended
project command is:

```bash
lando wp <command> [arguments]
```

The `wp` tooling entry in `.lando.yml` selects `appserver` and runs WP-CLI from
`/app/wordpress`. A pinned WP-CLI Phar is installed and checksum-verified by
the appserver build. Existing containers created before that build step was
added will still lack the executable. If `lando wp` fails with an OCI error such as
`exec: "wp": executable file not found in $PATH`, confirm the container state:

```bash
lando ssh -s appserver -c '/bin/sh -lc "command -v wp"'
```

An empty result means WP-CLI is not installed in the PHP runtime. Do not retry
the command in `cli`: that service intentionally provides Node.js and does not
own the WordPress PHP runtime. Run `lando rebuild -y` to apply the appserver
build and install `/usr/local/bin/wp`, then use `lando wp`. WP-CLI should run
against `/app/wordpress`; pass `--path=/app/wordpress` when invoking the binary
outside the Lando tooling wrapper.

For PHP-only diagnostics that do not require WP-CLI, use the installed PHP
runtime directly:

```bash
lando exec appserver -- php -r '<php code>'
```

This is a diagnostic fallback, not a general replacement for WP-CLI commands.

## Project Scope

- WordPress core and the World Graph Studio plugin live under `wordpress/`.
- World Graph Studio custom post types, Structured Content Fields, REST endpoints, and
  integrations live under `wordpress/wp-content/plugins/worldgraph/`.
- ComfyUI integration lives in the relevant World Graph Studio plugin and should fail
  clearly when the optional service is unavailable.
- ComfyUI MCP is the authority on what ComfyUI can do, WordPress and PHP should be aligned as closely with the MCP as possible.
- The Story Graph is the canonical model for projects, story worlds,
  characters, locations, scenes, shots, and assets.
- Keep architecture and API changes synchronized with the specifications in
  `about/`.

## Project Structure

### Root Level
- `.github/` - GitHub configuration, including agent definitions and testing utilities
  - `agents/` - VS Code Copilot Agent definitions (builder, code-reviewer, feature-builder, implementer, planner, researcher, thorough-reviewer)
  - `instructions/` - Build and development instructions (this file)
  - `testing/` - Testing documentation and utilities
- `about/` - Comprehensive documentation, specifications, and roadmap
- `scripts/` - Setup and utility scripts (database, initialization, etc.)
- `wordpress/` - WordPress core and plugins
  - `wp-content/plugins/worldgraph/` - Main World Graph Studio plugin with expanded structure:
    - `includes/admin/` - WordPress admin functionality
    - `includes/agents/` - Agent-related code and integrations
    - `includes/ai-editor/` - AI Editor implementation
    - `includes/cpts/` - Custom Post Type definitions and handlers
      - `includes/exporter/` - Markdown screenplay and storyboard export
      - `includes/importer/` - World Graph Studio JSON import
    - `includes/rest-api/` - REST API controllers and endpoints
    - `includes/taxonomies/` - Custom taxonomy definitions
    - `includes/utils/` - Utility functions and helpers (generation, search, relationships, continuity)
    - `plugins/` - Sub-plugins and integrations:
      - `celtx/` - Celtx GEM API integration
      - `edl/` - EDL parsing, preview, timecode, and generation utilities
      - `web-stories/` - Web Stories connector prototype source
    - `assets/` - Frontend assets
      - `ai-editor/` - AI Editor React components and styles
      - `css/` - Stylesheets
      - `js/` - JavaScript files
    - `tests/` - Test files and test utilities
  - `wp-content/plugins/secure-custom-fields/` - Structured Content Fields (SCF) plugin

## Current Delivery Scope

The current repository is delivered. Optional provider accounts and external
services still require deployment-specific credentials and configuration; that
does not make their World Graph Studio integration unfinished. Use
`about/Delivery_Status.md` as the status source of truth.

### Script Ecosystem

- CPT synchronization for projects, characters, locations, scenes, and shots.
- World Graph Studio JSON import and Markdown screenplay/storyboard export.
- Additional file-based formats such as FDX, Fade In, Highland, and Story
  Architect are on hold.
- Persistent World Graph Studio to Celtx ID mapping in post meta.
- Outbound World Graph Studio-to-Celtx sync through the bundled Celtx plugin.
- WordPress REST API endpoints and settings UI for Celtx credentials.

### Editorial Ecosystem

- CMX-style text and XML EDL parsing, preview, timecode, and generation tools.
- Timeline persistence and live Project/Episode export remain adapter work.
- Editorial artifact, scene, shot, track, and timecode metadata.
- AAF, OMF, and provider-specific NLE panels are extension points, not current
  delivery commitments.

### Story Graph Intelligence

- Semantic search.
- Continuity validation.
- Relationship analytics and narrative reasoning.

### AI Editor

The AI Editor is a Gutenberg sidebar backed by a direct LLM connection
layer and Story Graph context. Keep its boundary inside WordPress:

- Chat, analysis, generation, and continuity-check REST endpoints.
- A context builder that assembles data for the current post.
- Local vLLM support with optional cloud fallback.
- WordPress Abilities API registration for AI capabilities.
- Settings for backend selection, credentials, and model configuration.

Do not add a router, framework bridge, or separate execution service to this
module. Implementation files are located in:

- `includes/ai-editor/` - AI Editor PHP implementation
  - `class-ai-editor.php` - Main bootstrap/controller
  - `class-ai-llm-client.php` - LLM communication
  - `class-ai-context-builder.php` - Story Graph context assembly
  - `class-ai-editor-rest.php` - REST API endpoints
  - `class-ai-abilities.php` - Abilities API registration
- `assets/ai-editor/` - Frontend assets
  - `js/` - React Gutenberg sidebar components
  - `css/` - Panel and component styles

The full feature specification is in `about/AI_Editor.md`.

## Coding Conventions

### WordPress

- Use WordPress Coding Standards (WPCS).
- Register custom post types in the World Graph Studio plugin's established registration
  surface.
- Use Structured Content Fields via SCF in
  `wordpress/wp-content/plugins/secure-custom-fields`.
- Register core REST endpoints under the `worldgraph/v1` namespace, exposed by
  WordPress beneath `/wp-json/worldgraph/v1/`.
- All World Graph Studio custom post types must support the REST API.
- Use WordPress nonces for form submissions.
- Sanitize input and escape output.
- Keep sub-plugins under `worldgraph/plugins/`.

### WordPress REST Controllers: Static Method Pitfall

- `WP_REST_Controller::register_routes()` is non-static in WordPress core.
- Child classes must not override it as static; PHP 8.x treats that as a fatal
  signature error.
- Use an instance for route registration:

  ```php
  public static function init(): void {
      $instance = new self();
      add_action( 'rest_api_init', [ $instance, 'register_routes' ] );
  }

  public function register_routes() {
      // Register routes here.
  }
  ```

- Apply this pattern to every REST controller in `includes/rest-api/`.

### Docker and Lando

- Use Lando for local environment management where possible.
- Keep WordPress and database data in named volumes; never bind-mount them.
- Run ComfyUI behind its GPU-enabled service when GPU support is available.
- Never commit sensitive `.env` files.
- Restart containers only when changing Docker configuration, dependencies, or
  infrastructure. PHP changes take effect on the next request.

### Git

- Use conventional commit messages.
- Humans review changes before committing.
- Keep feature work on an appropriate branch.
- Tests must pass before merging.

## Testing

### Tool Calling

- Lando is the preferred environment for local validation.
- Run WordPress and PHP commands in `appserver`; run Node.js commands in `cli`.
- Use `lando wp` only after `command -v wp` succeeds in `appserver`. An OCI
  `executable file not found` error means the image lacks WP-CLI; it does not
  mean WP-CLI belongs in the Node `cli` service.
- Node.js is available in Lando's `cli` service, not the host or `appserver` service. Run JavaScript checks with `lando node --check /app/path/to/file.js`.

### WordPress: Do Not Restart

- WordPress runs PHP directly and does not need restarting after PHP changes.
- Do not run `lando restart wordpress` for PHP changes.
- If old code is still served, clear OPcache with:

  ```bash
  lando exec appserver -- php -r "opcache_reset();"
  ```

### WordPress: WP_Widget Method Signatures

WordPress's `WP_Widget` methods have no parameter type hints. Child classes must
preserve compatible signatures:

- `form( $instance )`, not `form( array $instance )`.
- `widget( $args, $instance )`, not typed parameters.
- `update( $new_instance, $old_instance )`, not typed parameters.

Return types are acceptable only when compatible with the installed WordPress
version.

### WordPress: Duplicate Function Declarations

Multiple utility files in the `WorldGraph\\Utils` namespace may be loaded by the
plugin. Before adding a shared helper in `includes/utils/`, check for an
existing definition and guard duplicates with the established
`function_exists()` pattern.

## Build Rules

1. Read existing files before writing.
2. Keep new entities aligned with the Story Graph and its specification.
3. Make the smallest change that satisfies the requested behavior.
4. Validate after each code change with the narrowest relevant test.
5. Update the relevant specification when an architecture or API contract
   changes.
6. Ask before guessing when a specification is ambiguous.
7. Preserve working code unless its removal is explicitly required.

## VS Code Agent System

The project includes agent definitions in `.github/agents/` for use with VS Code
Copilot. These agents are specialized for different development tasks:

- `builder.agent.md` - Build and deployment tasks
- `code-reviewer.agent.md` - Code review and quality checks
- `feature-builder.agent.md` - Feature implementation
- `implementer.agent.md` - Implementation details
- `planner.agent.md` - Project planning and architecture
- `researcher.agent.md` - Research and investigation
- `thorough-reviewer.agent.md` - Comprehensive review and analysis

See `AGENTS.md` in the project root for agent instructions and usage guidance.

## Testing and Quality Assurance

Testing documentation and utilities are maintained in `.github/testing/`. The
World Graph Studio plugin includes a `tests/` directory for unit and integration tests.

Key testing principles:
- Run tests locally via Lando to ensure environment consistency
- Test narrowly after each code change
- Ensure all tests pass before merging changes
- Use the testing utilities and documentation in `.github/testing/` for setup and execution

## Reference Documents

- Story Graph: `about/Story_Graph_Specification.md`
- Content model: `about/Content_Model_Specification.md`
- REST API: `about/REST_API_Specification.md`
- Delivery status: `about/Delivery_Status.md`
- Roadmap: `about/ROADMAP_World_Graph_Studio.md`
- AI Editor: `about/AI_Editor.md`
- Story Graph Intelligence: `about/Story_Graph_Intelligence.md`
- Script EDL Integration: `about/Script_EDL_Integration.md`
- CPT and SCF Schema: `about/CPT_and_SCF_Schema.md`
- Deployment: `about/Deployment_and_Connections.md`

## Key Utilities and Components

### Story Graph and Relationships
- `includes/utils/relationship-graph.php` - Story graph relationship management
- `includes/utils/relationships.php` - Relationship utilities
- `includes/utils/story-search.php` - Semantic search within Story Graph
- `includes/utils/continuity-checker.php` - Continuity validation and analysis

### Generation and ComfyUI Integration
- `includes/utils/generation-log.php` - Generation history tracking
- `includes/utils/generation-batch.php` - Batch generation handling
- `includes/utils/generation-modality.php` - Media type and modality management
- `includes/utils/comfy-bootstrap.php` - ComfyUI initialization
- `includes/utils/comfy-cloud-mcp.php` - Cloud ComfyUI MCP integration
- `includes/utils/local-comfyui.php` - Local ComfyUI instance management
- `includes/utils/comfy-manifest.php` - ComfyUI node/workflow manifest
- `includes/utils/connection_tester.php` - Service connection validation

### Data and Model Management
- `includes/utils/model_family.php` - Model family definitions and handling
- `includes/utils/template_bindings.php` - Template binding utilities
- `includes/utils/capability_sync.php` - Capability synchronization
- `includes/utils/class-asset-generator.php` - Asset generation utilities
- `includes/utils/connection_repository.php` - Connection configuration repository
- `includes/utils/helpers.php` - General helper functions
