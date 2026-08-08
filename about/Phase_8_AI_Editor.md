# Phase 8: AI Editor — WordPress Content × LLM × Multi-Agent Framework

> Build Your Story Once. Create Everywhere.

**Status: ✅ Complete**

## Objective

Connect the WordPress content editor to local/API-driven LLMs and the multi-agent framework, enabling creators to interact with AI advisors directly from the WordPress admin UI. This phase bridges the gap between content creation and AI intelligence.

---

# Guiding Vision

StoryOS creators should never need to leave WordPress to access AI capabilities. The AI Editor embeds the full power of the multi-agent framework — story analysis, prompt generation, production planning, technical advice — directly into the WordPress content editor experience.

---

# Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                    WordPress Admin UI                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │ Post Editor │  │ CPT Editor  │  │ Media Editor        │  │
│  │ (Gutenberg) │  │ (Gutenberg) │  │ (Gutenberg)         │  │
│  └──────┬──────┘  └──────┬──────┘  └────────┬────────────┘  │
│         │                │                   │               │
│  ┌──────┴────────────────┴───────────────────┴────────────┐  │
│  │              AI Editor Panel (Gutenberg Sidebar)       │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │  Agent Skills Router                           │  │  │
│  │  │  ┌──────────┐ ┌──────────┐ ┌────────────────┐  │  │  │
│  │  │  │ Story    │ │ Prompt   │ │ Production     │  │  │  │
│  │  │  │ Advisor  │ │ Advisor  │ │ Advisor        │  │  │  │
│  │  │  └──────────┘ └──────────┘ └────────────────┘  │  │  │
│  │  │  ┌──────────┐ ┌──────────┐ ┌────────────────┐  │  │  │
│  │  │  │ Technical│ │ Editorial│ │ Executive      │  │  │  │
│  │  │  │ Advisor  │ │ Advisor  │ │ Orchestrator   │  │  │  │
│  │  │  └──────────┘ └──────────┘ └────────────────┘  │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
                          │
                    REST API / AJAX
                          │
┌─────────────────────────┼──────────────────────────────────┐
│                         ▼                                  │
│              StoryOS WordPress Plugin                       │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  AI Editor Controller (PHP)                          │  │
│  │  - REST API endpoints                                │  │
│  │  - Context builder (Story Graph query)               │  │
│  │  - Response formatting                               │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────┬──────────────────────────────────┘
                          │
              ┌───────────┴───────────┐
              ▼                       ▼
    ┌─────────────────┐    ┌──────────────────┐
    │  Local LLM (vLLM)│    │  API LLM (OpenAI │
    │  Qwen3.6-35B     │    │  /Anthropic/etc.)│
    │  :11434          │    │  (fallback)      │
    └────────┬─────────┘    └────────┬─────────┘
             │                       │
             └───────────┬───────────┘
                         ▼
              ┌──────────────────┐
              │ Multi-Agent      │
              │ Framework (MAF)  │
              │ - 32+ Agents     │
              │ - Department     │
              │   Routing        │
              └──────────────────┘
```

---

# Components

## 1. AI Editor Plugin Module

A new module within the existing `storyos` WordPress plugin that provides:

- **Gutenberg Sidebar Panel**: AI-powered assistant panel in the block editor
- **REST API Endpoints**: Secure endpoints for AI communication
- **Context Builder**: Automatically gathers Story Graph context for the current post
- **Agent Skills Integration**: Loads WordPress agent-skills for AI assistant guidance

### File Structure

```
wordpress/wp-content/plugins/storyos/
├── storyos.php                          # Main plugin — wires in AI Editor + Abilities
├── includes/
│   ├── ai-editor/
│   │   ├── class-ai-editor.php          # Main bootstrap/controller
│   │   ├── class-ai-llm-client.php      # LLM communication (local + cloud)
│   │   ├── class-ai-maf-bridge.php      # Multi-agent framework bridge
│   │   ├── class-ai-context-builder.php # Story Graph context assembly
│   │   ├── class-ai-agent-router.php    # Keyword-based agent routing
│   │   ├── class-ai-agent-skills.php    # Agent skills loader
│   │   ├── class-ai-editor-rest.php     # REST API endpoints (8 endpoints)
│   │   └── class-ai-abilities.php       # Abilities API registration (3 groups)
│   ├── admin/
│   │   └── dashboard.php                # Existing admin UI (extends with AI settings)
│   └── rest-api/
│       └── base-controller.php          # Base REST controller pattern
├── assets/
│   └── ai-editor/
│       ├── js/ai-editor.js              # React Gutenberg sidebar panel
│       ├── js/ai-editor.asset.php       # Script dependency manifest
│       └── css/ai-editor.css            # Panel styles
└── agents/                              # MAF agent .agent.md files (to be copied)
```

### Gutenberg Panel

The AI Editor panel appears in the block editor sidebar for all StoryOS CPTs and standard posts. It provides:

- **Chat Interface**: Conversational AI assistant
- **Quick Actions**: Context-aware action buttons
- **Agent Selection**: Choose specific advisor or let orchestrator decide
- **Context Preview**: See what Story Graph data is being sent to the LLM
- **Response History**: Scroll through previous AI interactions

### REST API Endpoints

```
POST /storyos/v1/ai/chat          — Send message to AI advisor
POST /storyos/v1/ai/analyze       — Analyze current post content
POST /storyos/v1/ai/generate      — Generate content (prompts, text, etc.)
POST /storyos/v1/ai/continuity    — Run continuity check on post
GET  /storyos/v1/ai/context       — Get current post's AI context
GET  /storyos/v1/ai/agents        — List available agents
POST /storyos/v1/ai/settings      — Update LLM configuration
```

### Context Builder

Automatically assembles Story Graph context based on the current post being edited:

```php
// Example context assembly for a Character post
$context = [
    'post_type' => 'storyos_character',
    'post_id'   => 123,
    'entity'    => $character_data,
    'relationships' => [
        'appears_in_scenes' => [45, 67, 89],
        'associated_locations' => [12, 34],
        'related_characters' => [56, 78],
    ],
    'project'   => $project_data,
    'story_world' => $world_data,
    'recent_changes' => $revision_history,
];
```

## 2. WordPress Agent Skills Integration

Integrate the [WordPress/agent-skills](https://github.com/WordPress/agent-skills) repository to give the AI assistant expert-level WordPress knowledge when working within StoryOS.

### Installation

```bash
# Clone agent-skills into StoryOS
git clone https://github.com/WordPress/agent-skills.git \
    wordpress/wp-content/plugins/storyos/assets/agent-skills

# Or add as submodule
git submodule add https://github.com/WordPress/agent-skills.git \
    assets/agent-skills
```

### Skills to Integrate

| Skill | Purpose | Priority |
|-------|---------|----------|
| `wp-block-development` | Block editor guidance for AI-generated content | High |
| `wp-plugin-development` | Plugin architecture when extending StoryOS | Medium |
| `wp-rest-api` | REST API best practices for integrations | High |
| `wp-phpstan` | Code quality for AI-generated PHP | Low |
| `wp-abilities-api` | Security and permissions | Medium |
| `wp-interactivity-api` | Frontend interactivity patterns | Low |
| `wordpress-router` | Classify content type for routing | High |
| `wp-project-triage` | Auto-detect project context | Medium |

### Integration Approach

The AI Editor loads relevant agent-skills based on the current editing context:

```php
// Load skills relevant to current post type
$skills = AI_Editor_Skills::detect_relevant_skills( $post_type );

// Skills provide system prompt augmentation
$system_prompt .= AI_Editor_Skills::load_instructions( $skills );
```

Skills are stored in `assets/agent-skills/skills/` and loaded dynamically by the AI context builder.

## 3. LLM Connection Layer

Supports both local and API-driven LLM backends with automatic fallback.

### Configuration UI

Settings page in WordPress admin: `StoryOS → AI Settings`

| Setting | Description |
|---------|-------------|
| **LLM Backend** | Local vLLM / OpenAI / Anthropic / Custom |
| **Local LLM URL** | `http://localhost:11434` (vLLM/Qwen) |
| **API Key** | For cloud LLM providers |
| **Model** | `qwen3.6:35b-a3b-q4_K_M` (local) or cloud model |
| **Max Tokens** | Output token limit |
| **Temperature** | Creativity setting (0.0–1.0) |
| **Fallback** | Enable/disable cloud fallback |
| **Agent Skills Path** | Path to agent-skills directory |

### Connection Modes

```php
// Mode 1: Local LLM (primary)
define( 'STORYOS_AI_BACKEND', 'local' );
define( 'STORYOS_AI_URL', 'http://localhost:11434' );

// Mode 2: Cloud API (fallback)
define( 'STORYOS_AI_BACKEND', 'openai' );
define( 'STORYOS_AI_API_KEY', 'sk-...' );

// Mode 3: Dual (try local, fallback to cloud)
define( 'STORYOS_AI_BACKEND', 'dual' );
```

### Local LLM (vLLM)

Uses existing Qwen3.6-35B-A3B-NVFP4 setup at `llm/qwen35MOE/`:

```yaml
# docker-compose.yaml (existing)
services:
  qwen35moe-vllm:
    ports:
      - "11434:11434"
    command:
      - nvidia/Qwen3.6-35B-A3B-NVFP4
      # ... existing config
```

WordPress connects via OpenAI-compatible endpoint at `http://localhost:11434/v1/chat/completions`.

### API LLM (Fallback)

Supports OpenAI, Anthropic, and any OpenAI-compatible API:

```php
// OpenAI
$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
    'headers' => [ 'Authorization' => 'Bearer ' . $api_key ],
    'body'    => json_encode( $payload ),
] );

// Anthropic
$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
    'headers' => [
        'x-api-key'         => $api_key,
        'anthropic-version' => '2023-06-01',
    ],
    'body'    => json_encode( $payload ),
] );
```

## 4. Multi-Agent Framework Bridge

Connects WordPress to the existing MAF Python framework via REST API or direct execution.

### Integration Options

#### Option A: Orchestrator API (Recommended)

Use the existing orchestrator FastAPI service as the bridge:

```
WordPress → /storyos/v1/ai/chat → Orchestrator FastAPI → MAF Python
```

The orchestrator already has:
- ExecutiveOrchestrator with 5 advisors
- Story Graph context building
- Celery task queue for async operations
- Health checking

WordPress sends a request to the orchestrator which routes to the appropriate MAF agent.

#### Option B: Direct PHP Implementation

Implement advisor logic directly in PHP for simpler deployments:

```php
// PHP-based advisors (no Python dependency)
class AI_Story_Advisor {
    public function analyze( array $context ): string {
        $prompt = $this->build_prompt( $context );
        return $this->call_llm( $prompt );
    }
}
```

#### Option C: Hybrid (Best of Both)

- Simple queries handled by PHP → LLM direct call
- Complex multi-agent workflows routed to Python orchestrator
- WordPress decides based on request complexity

### Agent Routing

The AI Editor includes an agent router that determines which advisor should handle each request:

```php
class AI_Agent_Router {
    private $routing_rules = [
        'story'       => ['narrative', 'plot', 'character arc', 'story'],
        'prompt'      => ['generate', 'prompt', 'image', 'comfyui', 'visual'],
        'production'  => ['schedule', 'shot list', 'budget', 'production'],
        'technical'   => ['api', 'integration', 'error', 'debug', 'code'],
        'editorial'   => ['review', 'quality', 'consistency', 'edit'],
    ];

    public function route( string $message ): string {
        foreach ( $this->routing_rules as $agent => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( stripos( $message, $keyword ) !== false ) {
                    return $agent;
                }
            }
        }
        return 'executive_orchestrator'; // Default
    }
}
```

## 5. WordPress Abilities API & MCP Integration

StoryOS exposes its AI capabilities through the **WordPress Abilities API** (WP 6.9+), which the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) then converts into MCP Tools, Resources, and Prompts. This enables AI agents in VS Code Copilot, Cursor, Claude, and other MCP-compatible clients to interact with StoryOS directly.

### Hybrid Architecture

```
┌────────────────────────────────────────────────────────────────┐
│              AI Clients (Dual Exposure)                        │
│  ┌─────────────────────┐    ┌──────────────────────────────┐  │
│  │  Gutenberg Sidebar  │    │  MCP Clients (VS Code,       │  │
│  │  (REST API)         │    │  Cursor, Claude Code)        │  │
│  │                     │    │                              │  │
│  │  • Chat UI          │    │  • Tools: chat, analyze,     │  │
│  │  • Quick Actions    │    │    generate, continuity-check│  │
│  │  • Context Preview  │    │  • Resources: post-context,  │  │
│  └──────────┬──────────┘    │    character-context,        │  │
│             │               │    scene-context             │  │
│             │               │  • Prompts: story-review,    │  │
│             │               │    continuity-prompt         │  │
│             ▼               └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼────────────────┘
              │                              │
         REST API                      Abilities API
         /storyos/v1/ai/*            wp_register_ability()
              │                              │
              └──────────┬───────────────────┘
                         ▼
              ┌──────────────────────┐
              │  StoryOS AI Editor   │
              │  • Context Builder   │
              │  • Agent Router      │
              │  • LLM Client        │
              │  • MAF Bridge        │
              └──────────────────────┘
```

### MCP Component Types

| MCP Component | WordPress Ability Type | StoryOS Example | Access |
|---------------|----------------------|-----------------|--------|
| **Tool** | Executable action | `storyos/chat`, `storyos/analyze` | Call and get result |
| **Resource** | Read-only data | `storyos/post-context`, `storyos/character-context` | Fetch by URI |
| **Prompt** | Structured template | `storyos/story-review-prompt` | Get system/user prompt |

### Ability Registration Pattern

StoryOS uses an ability group pattern (inspired by SCF's implementation) to organize abilities by domain:

```php
// Main Abilities class — registers groups
\StoryOS\AI\Abilities\Abilities::instance()->init();

// Groups:
// 1. Chat_Abilities      → storyos/chat, storyos/analyze, storyos/generate, storyos/continuity-check
// 2. Context_Resources   → storyos/post-context, storyos/character-context, storyos/scene-context
// 3. Prompt_Templates    → storyos/story-review-prompt, storyos/continuity-prompt
```

Each ability is registered with:
- **label/description** — Human-readable name
- **input_schema/output_schema** — JSON Schema for validation
- **execute_callback** — PHP callable that returns results
- **permission_callback** — Capability check (`edit_posts`)
- **meta** — MCP configuration:
  - `public` — Whether exposed to REST/MCP/agents
  - `mcp.type` — `'tool'`, `'resource'`, or `'prompt'`
  - `mcp.uri` — For resources (e.g., `storyos://post-context/{id}`)
  - `mcp.arguments` — For prompts (parameter definitions)
  - `annotations` — `{readonly, destructive, idempotent}` for MCP hints

### MCP Adapter Integration

The [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) (v0.5.0, requires WP 6.9+) automatically discovers StoryOS abilities and exposes them to MCP clients:

```php
// MCP Adapter auto-discovers public abilities on plugins_loaded
// No manual server configuration needed for default server

// Default server endpoints:
// HTTP:  GET /wp-json/mcp/mcp-adapter-default-server
// STDIO: wp mcp-adapter serve --server=mcp-adapter-default-server

// Built-in meta-tools available:
// - mcp-adapter/discover-abilities   → List all StoryOS + WP abilities
// - mcp-adapter/get-ability-info     → Get schema for specific ability
// - mcp-adapter/execute-ability      → Execute an ability by name
```

**MCP Adapter Configuration** (optional custom server):

```php
add_action( 'mcp_adapter_init', function( $adapter ) {
    $adapter->create_server(
        'storyos',                    // Server ID
        'storyos/v1',                 // REST namespace
        'mcp',                        // REST route
        'StoryOS MCP Server',         // Display name
        'AI-powered storytelling tools', // Description
        '1.0.0',                      // Version
        [ \WP\MCP\Transport\HttpTransport::class ],
        null,                         // Error handler (default)
        null,                         // Observability (default)
        [                               // Abilities to expose
            'storyos/chat',
            'storyos/analyze',
            'storyos/generate',
            'storyos/continuity-check',
            'storyos/post-context',
            'storyos/character-context',
            'storyos/scene-context',
            'storyos/story-review-prompt',
            'storyos/continuity-prompt',
        ]
    );
} );
```

### MCP Client Configuration

**VS Code Copilot / Cursor (STDIO):**

```json
// Cursor config (~/.cursor/config.json)
{
  "mcpServers": {
    "storyos": {
      "command": "wp",
      "args": [
        "mcp-adapter",
        "serve",
        "--server=storyos",
        "--user=admin"
      ]
    }
  }
}
```

**VS Code Copilot (HTTP):**

```json
// VS Code settings.json
{
  "copilot.advision.mcpServers": {
    "storyos": {
      "url": "http://localhost/wp-json/mcp/mcp-adapter-default-server"
    }
  }
}
```

### MCP Annotations

StoryOS abilities include MCP annotations for client behavior hints:

| Annotation | Values | StoryOS Usage |
|------------|--------|---------------|
| `readonly` | `true/false/null` | All abilities are `true` (no DB writes) |
| `destructive` | `true/false/null` | All `false` (no content deletion) |
| `idempotent` | `true/false/null` | `true` for analyze/continuity, `false` for chat/generate |

These annotations let MCP clients:
- Warn users before destructive operations
- Cache idempotent results
- Show read-only indicators for informational tools

### Discovery Flow

```
1. MCP Client connects to WordPress MCP Adapter
2. Client calls mcp-adapter/discover-abilities
3. Adapter returns all public abilities including StoryOS:
   {
     "tools": [
       {"name": "storyos/chat", "description": "AI Chat..."},
       {"name": "storyos/analyze", "description": "Analyze Content..."},
       ...
     ],
     "resources": [
       {"uri": "storyos://post-context/{post_id}", "description": "Post Context..."},
       {"uri": "storyos://character-context/{character_id}", ...},
       ...
     ],
     "prompts": [
       {"name": "storyos/story-review-prompt", "description": "Story Review..."},
       ...
     ]
   }
4. Client can then call individual tools, read resources, or get prompts
```

---

# User Experience

## Editing a Character

1. Creator opens Character editor in WordPress
2. AI Editor panel appears in sidebar
3. Creator types: "Help me develop this character's arc across the three acts"
4. AI Editor:
   - Gathers character data from SCF fields
   - Queries Story Graph for scenes where character appears
   - Sends context to LLM via MAF (Story Advisor)
   - Returns narrative analysis with specific scene references
5. Creator asks: "Generate a character sheet prompt for ComfyUI"
6. AI Editor routes to Prompt Advisor, generates optimized prompt
7. Creator clicks "Apply to SCF" — prompt fills positive_prompt field

## Editing a Scene

1. Creator opens Scene editor
2. AI Editor panel shows "Scene Intelligence" actions:
   - **Check Continuity**: Validates against Story Graph
   - **Analyze Pacing**: Reviews scene length and content
   - **Generate Shot List**: Creates shot suggestions
   - **Create Prompts**: Generates ComfyUI prompts for assets
3. Creator clicks "Check Continuity"
4. AI Editor queries Story Graph Intelligence, returns issues

## Creating Content at Scale

1. Creator selects multiple characters in list view
2. Clicks "AI → Generate Character Sheets"
3. AI Editor generates prompts for all selected characters
4. Prompts are saved to SCF fields
5. Creator can then batch-generate via ComfyUI integration

---

# Implementation Plan

## Phase 8.1: Foundation (Complete)

- [x] Create `ai-editor` module structure in `includes/ai-editor/` (8 PHP classes)
- [x] Implement `AI_Context_Builder` — Story Graph context assembly
- [x] Create 8 REST API endpoints in `class-ai-editor-rest.php`
- [x] Add AI Settings page in WordPress admin
- [x] Configure local LLM connection via `AI_LLM_Client` (vLLM/Ollama/OpenAI/Anthropic)
- [x] Add cloud LLM fallback support (dual mode)
- [x] Wire AI Editor into main plugin file (`storyos.php`)
- [x] Create autoloader for AI Editor classes

## Phase 8.2: Agent Skills & MAF Bridge (Complete)

- [x] Implement `AI_MAF_Bridge` class — orchestrator REST API bridge
- [x] Implement `AI_Agent_Router` — keyword-based agent routing
- [x] Implement `AI_Agent_Skills` — agent skills loader
- [x] REST endpoints use MAF bridge for chat, analyze, generate, continuity
- [x] 5 advisor routing rules (story, prompt, production, technical, editorial)
- [ ] Clone WordPress/agent-skills repository (documented but not yet cloned)
- [ ] Copy MAF agent `.agent.md` files to plugin's agents directory

## Phase 8.3: Gutenberg Panel (Complete)

- [x] React sidebar panel component in `assets/ai-editor/js/ai-editor.js`
- [x] Script dependency manifest in `ai-editor.asset.php`
- [x] Panel styles in `assets/ai-editor/css/ai-editor.css`
- [x] Chat interface with response handling
- [x] Agent selection UI
- [x] Context preview panel
- [x] Response history

## Phase 8.4: Abilities API & MCP Integration (Complete)

- [x] `AI_Abilities` class with 3 ability groups (Chat, Context, Prompts)
- [x] Register StoryOS AI Editor category via `wp_register_ability_category()`
- [x] Register 4 Tool abilities: chat, analyze, generate, continuity-check
- [x] Register 3 Resource abilities: post-context, character-context, scene-context
- [x] Register 2 Prompt abilities: story-review-prompt, continuity-prompt
- [x] Wire Abilities into main plugin file with WP 6.9+ feature detection
- [ ] Install and configure WordPress MCP Adapter plugin (documented but not yet installed)
- [ ] Test MCP discovery flow (discover-abilities, execute-ability)
- [ ] Configure MCP client connections (VS Code, Cursor)
- [ ] Add custom MCP server configuration option
- [ ] Validate MCP annotations (readonly, destructive, idempotent)

## Phase 8.5: Polish & Testing (Planned)

- [ ] Add keyboard shortcuts for AI panel
- [ ] Implement content generation actions (insert into editor)
- [ ] Add AI-generated content labeling
- [ ] Performance optimization (caching, rate limiting)
- [ ] Accessibility audit
- [ ] Security audit (input sanitization, output escaping)
- [ ] Test REST endpoints with StoryOS CPTs
- [ ] Test Gutenberg panel with real content
- [ ] Test MCP integration with live clients
- [ ] Documentation and examples

---

# Security Considerations

## Input/Output

- All user input sanitized with `sanitize_text_field()`, `wp_kses_post()`
- All AI output escaped before display with `esc_html__()`, `esc_html__()`, `wp_kses()`
- AI-generated content marked with metadata `ai_generated = true`

## API Security

- REST endpoints require `edit_posts` or custom capability `use_ai_editor`
- Nonce verification on all AJAX/REST requests
- Rate limiting: 10 requests/minute per user
- API key stored in `wp_options` with `autoload = no`

## LLM Security

- Local LLM preferred for sensitive story content
- Cloud fallback only with explicit user consent
- No content stored by cloud providers (clear instructions in prompt)
- Option to disable cloud fallback entirely

---

# Performance Considerations

## Caching

- LLM responses cached in transients (configurable TTL)
- Story Graph context cached per post (invalidated on save)
- Agent skills loaded once, cached in memory

## Async Operations

- Long-running AI operations queued via WordPress cron
- Progress indicators in UI
- Webhook-style notifications when complete

## Rate Limiting

- Configurable request limits per backend
- Queue system for high-volume operations
- Graceful degradation when LLM unavailable

---

# Testing Strategy

## Unit Tests

- Context builder accuracy
- Agent routing correctness
- LLM connection health checks
- Skill loading and parsing

## Integration Tests

- WordPress → Orchestrator API flow
- Local LLM connectivity
- Cloud LLM fallback
- Agent skills integration

## E2E Tests

- Chat interface interactions
- Content generation workflows
- Multi-agent conversations
- Error handling scenarios

---

# Dependencies

## Existing

- WordPress 6.0+
- StoryOS plugin (existing)
- Secure Custom Fields (existing)
- Orchestrator FastAPI service (existing)
- Qwen3.6-35B vLLM instance (existing)
- Multi-agent framework Python package (existing)

## New

- WordPress/agent-skills repository
- React (for Gutenberg panel — already available via WordPress)
- WordPress Data Controls (for chat UI — core blocks)

---

# Future Extensions

## Real-time Collaboration

- Multiple creators seeing AI responses simultaneously
- Shared AI conversation history per project

## Voice Interface

- Voice input to AI Editor
- Text-to-speech for AI responses
- Voice-activated agent commands

## Visual AI

- Image generation directly in media editor
- Visual style transfer on uploaded images
- AI-powered image tagging and organization

## Multi-language

- AI responses in creator's preferred language
- Translation assistance for scripts
- Cross-language story development

## Plugin Ecosystem

- Third-party advisor plugins
- Custom agent definitions
- Community skill packs

---

# Success Metrics

## Adoption

- % of creators using AI Editor daily
- Average sessions per creator
- Most-used agents/advisors

## Quality

- User satisfaction ratings
- AI response accuracy (manual audit)
- Reduction in manual revision cycles

## Performance

- Average response time (< 3s for simple, < 15s for complex)
- Cache hit rates
- LLM uptime/availability

## Ecosystem

- Number of agent skills installed
- Third-party advisors created
- Community contributions to agent-skills

---

# Long-Term Vision

The AI Editor transforms StoryOS from a content management system into an **AI-native storytelling platform**. Creators work in the environment they know (WordPress) with the intelligence they need (multi-agent AI). The Story Graph remains the source of truth, now enhanced by AI reasoning, generation, and advisory capabilities.

**The future of storytelling is structured.**
**The future of storytelling is open.**
**The future of storytelling is intelligent.**
