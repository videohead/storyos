# Phase 8: AI Editor

> Build Your Story Once. Create Everywhere.

**Status: Implemented — Live end-to-end validation pending**

The AI Editor implementation is complete, and the LLM connection is
established. Live end-to-end testing of the internal AI chat has **not** yet
been completed.

## Implementation Update

The AI Editor is a WordPress plugin module. It combines Story Graph context,
WordPress filmmaking abilities, and an API-connected LLM behind Gutenberg,
WordPress REST, and the WordPress Abilities API.

## Objective

Give creators useful AI assistance without leaving WordPress. The AI Editor
supports chat, content analysis, generation assistance, continuity checks, and
context inspection for posts and Story Graph entities.

The Story Graph remains the source of truth. The AI Editor may suggest content,
prompts, relationships, or editorial actions, but it must not silently change
canonical story data.

## Architecture

```text
WordPress Admin
  Gutenberg post and CPT editors
  AI Editor sidebar
          |
          +------------------------------+
          |                              |
          v                              v
StoryOS REST API                    WordPress Abilities API
  /storyos/v1/ai/*                  tools, resources, prompts
          |                              |
          +--------------+---------------+
                         v
                  StoryOS AI Editor
                  - Context Builder
                  - LLM Client
                  - Ability callbacks
                  - Permission and schema checks
                         |
                         v
                  Configured LLM endpoint
                  local or hosted
```

WordPress owns authentication, permissions, context assembly, request
validation, response formatting, and user-visible history. The configured LLM
only receives the approved context and prompt for the current operation.

## User Experience

The Gutenberg sidebar can provide:

- Chat about the current post or Story Graph entity.
- Analyze content and return structured observations.
- Generate draft text, prompts, or editorial suggestions.
- Run a continuity check and link findings to affected entities.
- Preview the Story Graph context that will be sent to the LLM.
- Apply an accepted suggestion explicitly to an editor field.
- Show response history for the current editing session.

The panel must distinguish generated suggestions from saved WordPress content.
Actions that modify content require an explicit user action and appropriate
WordPress capability.

## Plugin Structure

The active implementation surfaces are:

```text
wordpress/wp-content/plugins/storyos/
├── storyos.php
├── includes/
│   └── ai-editor/
│       ├── class-ai-editor.php
│       ├── class-ai-llm-client.php
│       ├── class-ai-context-builder.php
│       ├── class-ai-editor-rest.php
│       └── class-ai-abilities.php
└── assets/
    └── ai-editor/
        ├── js/ai-editor.js
        └── css/ai-editor.css
```

Responsibilities:

- `class-ai-editor.php` bootstraps the module, settings, and service objects.
- `class-ai-llm-client.php` calls the configured LLM endpoint and normalizes
  responses and errors.
- `class-ai-context-builder.php` assembles bounded Story Graph context for a
  post, character, scene, or related entity.
- `class-ai-editor-rest.php` registers and handles AI Editor REST routes.
- `class-ai-abilities.php` registers tools, resources, and prompts with the
  WordPress Abilities API.
- `assets/ai-editor/js/ai-editor.js` provides the Gutenberg sidebar UI.
- `assets/ai-editor/css/ai-editor.css` provides its presentation layer.

## Story Graph Context

The context builder should include only data required for the requested action.
For a character post, a context object may contain:

```php
$context = [
    'post_type'    => 'storyos_character',
    'post_id'      => 123,
    'entity'       => $character_data,
    'relationships' => [
        'appears_in_scenes'    => [ 45, 67, 89 ],
        'associated_locations' => [ 12, 34 ],
        'related_characters'   => [ 56, 78 ],
    ],
    'project'      => $project_data,
    'story_world'  => $world_data,
];
```

Context rules:

- Respect the current user's ability to read each entity.
- Prefer registered CPT and SCF data over ad hoc metadata reads.
- Include relationship IDs and concise labels rather than entire unrelated
  posts.
- Bound context size before sending it to an LLM.
- Redact credentials, nonces, private settings, and unrelated user data.
- Record the source post and context version for auditability.

## LLM Connection Layer

Implementation:

`wordpress/wp-content/plugins/storyos/includes/ai-editor/class-ai-llm-client.php`

Supported connection modes are configured in **StoryOS > AI Settings**:

| Connection | Endpoint | Credential |
| --- | --- | --- |
| OpenAI | OpenAI API | OpenAI API key |
| Claude | Anthropic API | Anthropic API key |
| Local or hosted compatible API | Provider `/v1` endpoint | Optional or provider-specific key |

Deployment guidance is documented in
[Deployment and Connections](Deployment_and_Connections.md).

Environment variables may override WordPress settings for deployed sites:

- `STORYOS_AI_API_KEY` for the primary connection.
- `STORYOS_AI_FALLBACK_API_KEY` for an optional fallback.

The client must:

- Use the selected backend's documented request format.
- Set bounded timeouts and response sizes.
- Normalize successful responses into the AI Editor response shape.
- Convert transport and provider failures into sanitized `WP_Error` values.
- Never log or persist API keys, authorization headers, or full raw responses.

Local model support is optional. StoryOS remains useful for story management,
continuity, planning, and asset organization when no LLM is configured.

## REST API

The AI Editor REST controller is:

`wordpress/wp-content/plugins/storyos/includes/ai-editor/class-ai-editor-rest.php`

Routes use the `storyos/v1` namespace:

```text
POST /storyos/v1/ai/chat
POST /storyos/v1/ai/analyze
POST /storyos/v1/ai/generate
POST /storyos/v1/ai/continuity
GET  /storyos/v1/ai/context
GET  /storyos/v1/ai/agents
GET  /storyos/v1/ai/settings
GET  /storyos/v1/ai/health
```

Each route must validate its arguments and use the appropriate WordPress
permission callback. The `agent` argument identifies a filmmaking ability or
profile when one is selected; it is not a request to create a separate runtime
or dispatch service.

REST responses should provide stable fields such as:

- `success` and a bounded response message.
- The requested action.
- Source post or entity ID when applicable.
- Structured analysis, continuity findings, or generated suggestions.
- Sanitized error code and message when unsuccessful.

## WordPress Abilities API

The Abilities API registration is in:

`wordpress/wp-content/plugins/storyos/includes/ai-editor/class-ai-abilities.php`

The module registers three groups.

### Tools

- `storyos/chat`
- `storyos/analyze`
- `storyos/generate`
- `storyos/continuity-check`

### Resources

- `storyos/post-context`
- `storyos/character-context`
- `storyos/scene-context`
- `storyos/templates-manifest`

### Prompts

- `storyos/story-review-prompt`
- `storyos/continuity-prompt`

Each ability must define:

- Human-readable label and description.
- JSON input and output schemas.
- A PHP execute callback that uses existing StoryOS services.
- A permission callback based on the requested entity and action.
- MCP metadata identifying whether it is a tool, resource, or prompt.
- Accurate `readonly`, `destructive`, and `idempotent` annotations.

The WordPress MCP Adapter may discover public abilities and expose them to
MCP-compatible clients. StoryOS should register abilities through WordPress and
should not duplicate them in a separate integration server.

## Ability Behavior

### `storyos/chat`

Accepts a prompt and optional post context, then returns a response from the
configured filmmaking ability and LLM connection. Chat does not write content.

### `storyos/analyze`

Returns structured observations about the current post, Story Graph context,
prompt, or selected editorial concern. Analysis should identify evidence from
the supplied context.

### `storyos/generate`

Returns a draft, prompt, or other explicitly requested content. It should
return proposed values for the editor to review rather than silently saving
them.

### `storyos/continuity-check`

Calls the same WordPress continuity services used by the Phase 7 admin and REST
surfaces. Results should include severity, rule, affected entities, evidence,
and suggested next steps.

### Context resources

Context resources are read-only and permission-checked. They provide compact
JSON representations suitable for an AI client and should not expose secrets or
private entities to a user who cannot read them in WordPress.

### Prompt resources

Prompt abilities return reusable templates for story review and continuity
work. Prompt text must make the Story Graph context boundary and output format
clear.

### Generation template discovery

`storyos/templates-manifest` is a read-only resource for MCP clients that need
to discover available generation templates before preparing an asset request.
It is exposed at `storyos://templates-manifest` and returns only published
`storyos_template` records with `status` set to `active`. Entries include the
template identity, revision/version, generation structure, provider type,
configuration schema, and default values.

The manifest does not expose credentials or raw executable ComfyUI workflows,
and it does not queue generation. A client must use the discovered metadata to
prepare a validated WordPress-owned request package through the Generation
Engine contract.

## Security

- Require the appropriate WordPress capability for every REST route and
  ability.
- Verify nonces for browser-originated state-changing requests.
- Sanitize prompts and parameters before processing.
- Escape generated output before rendering it in admin screens.
- Keep API keys in environment variables or non-autoloaded protected options.
- Do not put credentials in JavaScript, context resources, prompts, logs, or
  response history.
- Mark AI-generated content and preserve its source context where it is saved.
- Apply request limits per user and backend.
- Treat generated text as untrusted content and sanitize it before insertion.

## Performance and Failure Handling

- Keep context assembly bounded and avoid loading unrelated Story Graph data.
- Cache safe context and configuration data with invalidation on content save.
- Use bounded request timeouts and clear user-facing failure messages.
- Do not block WordPress page loads on long-running generation work.
- Return partial structured results only when their completeness is clear.
- Make health checks report configuration and connectivity without exposing
  credentials.
- Preserve normal WordPress editing when an LLM or optional MCP client is
  unavailable.

## Testing Strategy

### Context Builder

- Builds correct context for posts, characters, and scenes.
- Includes approved relationships and excludes unauthorized entities.
- Redacts secrets and bounds output size.
- Produces stable context for equivalent WordPress data.

### LLM Client

- Handles successful compatible, OpenAI, and Anthropic responses.
- Handles malformed responses, timeouts, authentication failures, and provider
  errors as sanitized `WP_Error` values.
- Does not leak credentials in errors or logs.
- Applies configured backend and fallback settings correctly.

### REST and Abilities

- Routes validate required fields and post IDs.
- Unauthorized users cannot read context or invoke AI actions.
- Ability schemas register on supported WordPress versions.
- Tool callbacks return structured responses.
- Resource callbacks enforce post and entity permissions.
- Prompt callbacks return valid prompt definitions.

### Gutenberg Panel

- Loads on supported StoryOS post types.
- Displays loading, success, empty, and failure states.
- Shows context before sending when requested.
- Does not overwrite editor content without explicit confirmation.
- Handles unavailable LLM configuration accessibly.

## Definition of Done

- [x] AI Editor module is bootstrapped by the StoryOS plugin.
- [x] Gutenberg sidebar provides chat and context-aware actions.
- [x] Story Graph context is assembled in WordPress.
- [x] Local and hosted LLM connection settings are supported.
- [x] AI Editor REST routes validate and permission-check requests.
- [x] Four tool abilities are registered.
- [x] Three context resources are registered.
- [x] Active generation templates are discoverable through a read-only MCP resource.
- [x] Two prompt abilities are registered.
- [x] MCP metadata and permission callbacks are defined.
- [ ] Complete live MCP Adapter discovery tests.
- [ ] Complete browser and accessibility coverage for the sidebar.
- [ ] Add durable audit records for accepted AI edits.

## Relationship to Other Phases

| Phase | Relationship |
| --- | --- |
| Story Core | Supplies the posts, SCF fields, and relationships used as context. |
| Story Graph Intelligence | Supplies search, continuity, and relationship results. |
| Generation Engine | Uses approved prompts and Story Graph context for media workflows. |
| Script Ecosystem | Provides imported script entities for analysis and continuity. |
| Editorial Ecosystem | Provides timeline and EDL context for editorial assistance. |

## Long-Term Direction

The AI Editor should remain a focused WordPress feature: a permission-aware
context layer, a reliable LLM client, an ergonomic Gutenberg panel, and a clear
Abilities API contract. New capabilities should be added as typed WordPress
abilities or editor actions that reuse existing StoryOS services.