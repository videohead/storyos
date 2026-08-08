# Phase 8: AI Editor — WordPress Content × LLM × Multi-Agent Framework

> Build Your Story Once. Create Everywhere.

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
wordpress/wp-content/plugins/storyos/includes/
├── admin/
│   └── ai-editor/
│       ├── class-ai-editor-panel.php      # Gutenberg sidebar panel
│       ├── class-ai-editor-rest.php       # REST API endpoints
│       ├── class-ai-context-builder.php   # Story Graph context gathering
│       ├── class-ai-agent-router.php      # Agent selection/routing
│       └── class-ai-settings.php          # LLM configuration UI
├── cpts/
│   └── (existing CPTs — AI panel hooks into all)
└── rest-api/
    └── ai-editor-controller.php           # REST controller
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

## Phase 8.1: Foundation (Weeks 1-2)

- [ ] Create `ai-editor` module structure in StoryOS plugin
- [ ] Implement `AI_Context_Builder` class
- [ ] Create REST API endpoints for AI communication
- [ ] Add AI Settings page in WordPress admin
- [ ] Configure local LLM connection (vLLM)
- [ ] Add cloud LLM fallback support

## Phase 8.2: Agent Skills Integration (Weeks 3-4)

- [ ] Clone WordPress/agent-skills repository
- [ ] Implement skill detection and loading system
- [ ] Integrate core skills: `wp-block-development`, `wp-rest-api`, `wordpress-router`
- [ ] Create skill augmentation for system prompts
- [ ] Add skill caching for performance

## Phase 8.3: Gutenberg Panel (Weeks 5-6)

- [ ] Build React sidebar panel component
- [ ] Implement chat interface with streaming responses
- [ ] Add agent selection UI
- [ ] Create context preview panel
- [ ] Add response history
- [ ] Style with WordPress Design System (wpds)

## Phase 8.4: MAF Bridge (Weeks 7-8)

- [ ] Implement ExecutiveOrchestrator PHP wrapper
- [ ] Create agent routing system
- [ ] Connect to orchestrator FastAPI service
- [ ] Add async task handling for long operations
- [ ] Implement health checks for LLM connectivity
- [ ] Add error handling and fallback logic

## Phase 8.5: Polish & Testing (Weeks 9-10)

- [ ] Add keyboard shortcuts for AI panel
- [ ] Implement content generation actions (insert into editor)
- [ ] Add AI-generated content labeling
- [ ] Performance optimization (caching, rate limiting)
- [ ] Accessibility audit
- [ ] Security audit (input sanitization, output escaping)
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
