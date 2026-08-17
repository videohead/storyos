# StoryOS Build Instructions

> Build Your Story Once. Create Everywhere.

This file defines the active development conventions for StoryOS. StoryOS is a
WordPress application whose canonical data model is the Story Graph. ComfyUI is
an optional external generation service used by the relevant plugin.

## Local Entry Points

When Lando is already running, use these service entry points for local
validation:

- WordPress app: http://storyos.lndo.site/ or https://storyos.lndo.site/
- ComfyUI service: http://localhost:32778
- phpMyAdmin: http://localhost:32773

If the environment is already running, prefer `lando info` to refresh the URLs
before testing. WordPress is the application and control plane; do not assume a
separate Python, queue, or orchestration service exists.

## Project Scope

- WordPress core and the StoryOS plugin live under `wordpress/`.
- StoryOS custom post types, Structured Content Fields, REST endpoints, and
  integrations live under `wordpress/wp-content/plugins/storyos/`.
- ComfyUI integration lives in the relevant StoryOS plugin and should fail
  clearly when the optional service is unavailable.
- The Story Graph is the canonical model for projects, story worlds,
  characters, locations, scenes, shots, and assets.
- Keep architecture and API changes synchronized with the specifications in
  `about/`.

## Current Roadmap

### Script Ecosystem

- Celtx GEM API bi-directional sync through the `storyos-celtx` plugin.
- CPT synchronization for projects, characters, locations, scenes, and shots.
- Persistent StoryOS to Celtx ID mapping in post meta.
- WordPress REST API endpoints and settings UI for Celtx credentials.
- Planned file-based import and export for Fountain, FDX, Fade In, Highland,
  Markdown, screenplay, and shooting-script formats.

### Editorial Ecosystem

- EDL export.
- Timeline metadata and scene/shot mapping.
- NLE integrations such as XML and AAF.

### Story Graph Intelligence

- Semantic search.
- Continuity validation.
- Relationship analytics and narrative reasoning.

### AI Editor

The planned AI Editor is a Gutenberg sidebar backed by a direct LLM connection
layer and Story Graph context. Keep its boundary inside WordPress:

- Chat, analysis, generation, and continuity-check REST endpoints.
- A context builder that assembles data for the current post.
- Local vLLM support with optional cloud fallback.
- WordPress Abilities API registration for AI capabilities.
- Settings for backend selection, credentials, and model configuration.

Do not add a router, framework bridge, or separate execution service to this
module. The current module files are:

- `class-ai-editor.php` - Main bootstrap/controller.
- `class-ai-llm-client.php` - LLM communication.
- `class-ai-context-builder.php` - Story Graph context assembly.
- `class-ai-editor-rest.php` - REST API endpoints.
- `class-ai-abilities.php` - Abilities API registration.
- `assets/ai-editor/js/ai-editor.js` - React Gutenberg sidebar panel.
- `assets/ai-editor/css/ai-editor.css` - Panel styles.

The full feature specification is in `about/Phase_8_AI_Editor.md`.

## Coding Conventions

### WordPress

- Use WordPress Coding Standards (WPCS).
- Register custom post types in the StoryOS plugin's established registration
  surface.
- Use Structured Content Fields via SCF in
  `wordpress/wp-content/plugins/secure-custom-fields`.
- Register REST endpoints under the `/api/storyos/v1/` namespace used by the
  existing plugin.
- All StoryOS custom post types must support the REST API.
- Use WordPress nonces for form submissions.
- Sanitize input and escape output.
- Keep sub-plugins under `storyos/plugins/`.

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
- Use `lando ssh` or the service-specific Lando command for tests.

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

Multiple utility files in the `StoryOS\\Utils` namespace may be loaded by the
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

## Reference Documents

- Story Graph: `about/Story_Graph_Specification.md`
- Content model: `about/Content_Model_Specification.md`
- REST API: `about/REST_API_Specification.md`
- Roadmap: `about/ROADMAP_StoryOS.md`
- AI Editor: `about/Phase_8_AI_Editor.md`