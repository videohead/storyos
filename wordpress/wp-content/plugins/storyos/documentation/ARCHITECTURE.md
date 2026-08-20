# StoryOS Plugin Architecture

## Overview

StoryOS is a WordPress plugin that manages the Story Graph — a comprehensive data model for AI-powered storytelling. The plugin has been revised to remove Python orchestrator dependencies and align with modern WordPress standards.

## Core Architecture

### 1. ComfyUI MCP Integration

**File:** `includes/utils/comfy-cloud-mcp.php`

The plugin communicates with Comfy Cloud MCP (Model Context Protocol) via HTTP for generative AI tasks. This replaces the previous Python orchestrator dependency.

**Key Methods:**
- `run_template(template, prompt, parameters)` - Submit generation jobs
- `get_job_status(job_id)` - Poll job status
- `call_tool(name, arguments)` - Generic tool invocation

**Configuration:**
- API Key: `STORYOS_COMFY_API_KEY` (constant) or `storyos_comfy_api_key` (option)
- Endpoint: `https://cloud.comfy.org/mcp`

### 2. WordPress Cron (WP-Cron) Job Processing

**File:** `includes/utils/generation-batch.php`

Generation jobs are processed asynchronously via WordPress Cron, not via a separate Python service.

**Process Flow:**
1. User submits generation via REST API → `POST /wp-json/storyos/v1/generation`
2. Job stored in `storyos_generation` CPT with status `queued`
3. `Generation_Batch::schedule()` schedules WP-Cron event
4. WP-Cron processor calls `Generation_Batch::process()` every 60 seconds
5. Processor submits queued jobs to Comfy Cloud MCP
6. Processor polls submitted jobs for completion
7. Results stored in post meta when complete

**WP-Cron Hook:** `storyos_process_generation_batch`

### 3. Local Story Graph Analytics

All analytics functions operate on local WordPress data without requiring external services:

**Files:**
- `includes/utils/relationship-graph.php` - Network analysis
- `includes/utils/continuity-checker.php` - Continuity validation
- `includes/utils/story-search.php` - Keyword + semantic search
- `includes/utils/capability_sync.php` - Provider capability tracking

**Key Functions:**
- `fetch_relationship_graph()` - Build graph from posts and relationships
- `fetch_graph_analytics()` - Calculate network metrics (density, connectivity)
- `fetch_continuity_validation()` - Local content validation
- `fetch_keyword_search()` - WordPress post search with filters

### 4. WordPress Agent Abilities API

**File:** `includes/ai-editor/class-ai-abilities.php`

For WordPress 6.9+, StoryOS exposes capabilities via the WordPress Abilities API for integration with AI tools and MCP adapters.

**Features:**
- Structured ability definitions with input/output schemas
- Execute callbacks for ability implementation
- Permission callbacks for access control
- Integration with WordPress REST API

**Categories:**
- `storyos-ai-editor` - Main AI editor category

**Initialization:**
```php
if ( function_exists( 'wp_register_ability' ) ) {
    \StoryOS\AI\Abilities\Abilities::instance()->init();
}
```

### 5. Provider Connections

**File:** `includes/cpts/connection.php`

Manages API connections to providers like Comfy Cloud MCP.

**Fields:**
- `connection_name` - Human-readable name
- `provider_type` - Currently: `comfy_cloud_mcp`
- `environment` - Local/Dev/Staging/Production
- `status` - Unverified/Verified/Error
- `configuration` - Provider-specific config

**Validation:**
- `includes/utils/connection_tester.php` - Verifies credentials
- Tests Comfy Cloud MCP API key configuration

## Custom Post Types (CPTs)

| CPT | Purpose |
|-----|---------|
| `storyos_project` | Top-level story project container |
| `storyos_story_world` | Story world/universe settings |
| `storyos_character` | Character definitions |
| `storyos_location` | Location/setting data |
| `storyos_prop` | Prop/object catalog |
| `storyos_organization` | Organization/group data |
| `storyos_episode` | Episode/chapter structure |
| `storyos_scene` | Scene content and metadata |
| `storyos_shot` | Shot/sequence data |
| `storyos_sound` | Planned soundtrack cues linked to scenes, shots, characters, and rendered audio assets |
| `storyos_storyboard_frame` | Storyboard visual frames |
| `storyos_asset` | Generated/managed assets |
| `storyos_editorial_artifact` | Editorial notes, scripts, etc. |
| `storyos_template` | Story templates/patterns |
| `storyos_connection` | Provider connections |
| `storyos_generation` | Generation job tracking |

## REST API Endpoints

All endpoints use namespace: `/wp-json/storyos/v1/`

### Generation API
- `POST /generation` - Submit generation job
- `GET /generation/{id}` - Get job status
- `POST /generation/{id}/cancel` - Cancel job
- `GET /generation/asset/{asset_id}/history` - Get generation history

### Agents API
- `GET /agents` - List available agents
- `GET /agents/{id}` - Get agent details
- `POST /agents/{id}/execute` - Execute agent action

### Graph API
- `GET /graph` - Get story graph analytics
- `GET /graph/relationships` - Get relationship data
- `GET /graph/connections` - Get network connections

### CRUD Endpoints
All CPTs have standard REST endpoints:
- `GET /projects`, `POST /projects`, `GET /projects/{id}`, `PUT /projects/{id}`, `DELETE /projects/{id}`
- `GET /sounds`, `POST /sounds`, `GET /sounds/{id}`, `PUT /sounds/{id}`, `DELETE /sounds/{id}`
- Similar for characters, locations, scenes, shots, etc.

## Admin Panels

| Location | Purpose |
|----------|---------|
| `StoryOS > Dashboard` | Overview and statistics |
| `StoryOS > Connections` | Provider connection management |
| `Tools > Story Graph Analytics` | Analytics and insights |
| `StoryOS > Setup Wizard` | Initial configuration |

## Job Processing Flow (WP-Cron)

```
User submits generation request
           ↓
REST API stores job in storyos_generation CPT
           ↓
Generation_Batch::schedule() schedules WP-Cron event
           ↓
WP-Cron calls storyos_process_generation_batch hook
           ↓
Generation_Batch::process() runs:
  1. submit_queued_jobs() → Comfy Cloud MCP
  2. poll_submitted_jobs() → Check status
  3. Schedule next event if jobs remain
           ↓
Results stored in post meta
           ↓
REST API returns job results to client
```

## Removed Components

The following have been removed or deprecated:
- ❌ Python orchestrator service
- ❌ subprocess/shell_exec execution
- ❌ Orchestrator health check endpoints
- ❌ Provider discovery from external orchestrator

## Plugin Dependencies

### Required
- WordPress 6.0+ (6.9+ for full Abilities API support)
- PHP 8.1+
- Secure Custom Fields (SCF) plugin

### Recommended
- WordPress 6.9+ (for WordPress Agent Abilities API)

## Configuration

### Setup Wizard

All API keys and provider connections are configured through the **StoryOS Setup Wizard** that appears automatically on plugin activation.

**Access the wizard:**
- Automatic redirect after plugin activation
- Manual access: Navigate to **StoryOS > Setup**

**Configured items:**
1. Comfy Cloud MCP API key (optional)
2. Primary LLM provider and credentials
3. Advanced LLM settings (max tokens, temperature)
4. Fallback LLM provider and credentials

**Security:**
- API keys can be defined as environment variables in `wp-config.php`
- When constants are defined, wizard displays fields as read-only
- Production recommended: Use environment variables, not database storage

See [SETUP_WIZARD_GUIDE.md](SETUP_WIZARD_GUIDE.md) for comprehensive wizard documentation.

### Environment Variables (Optional for Production)
```php
define( 'STORYOS_COMFY_API_KEY', 'your-comfy-api-key' );
define( 'STORYOS_AI_API_KEY', 'your-llm-api-key' );
define( 'STORYOS_AI_FALLBACK_API_KEY', 'your-fallback-key' );
```

### WordPress Options (Set via Wizard or Programmatically)
```php
// Comfy Cloud MCP
get_option( 'storyos_comfy_api_key' )
get_option( 'storyos_generation_connection_mode' ) // Current preferred Connection choice.
get_option( 'storyos_comfy_connection_mode' ) // Legacy compatibility mirror.

// Primary LLM
get_option( 'storyos_ai_backend' )
get_option( 'storyos_ai_url' )
get_option( 'storyos_ai_model' )
get_option( 'storyos_ai_api_key' )
get_option( 'storyos_ai_max_tokens' )
get_option( 'storyos_ai_temperature' )

// Fallback LLM
get_option( 'storyos_ai_fallback_backend' )
get_option( 'storyos_ai_fallback_api_key' )
```

## Development Notes

### Adding New Abilities
Edit `includes/ai-editor/abilities/` and implement `AbstractAbilityGroup`:
```php
class My_Ability_Group extends AbstractAbilityGroup {
    protected $slug = 'storyos_my_group';
    public function register(): void {
        $this->register_ability( 'storyos/my_ability', [
            'label' => 'My Ability',
            'input_schema' => [...],
            'execute_callback' => [$this, 'execute_my_ability'],
        ]);
    }
}
```

### Job Status Values
- `queued` - Waiting to submit
- `submitted` - Sent to Comfy Cloud MCP
- `completed` - Job finished successfully
- `failed` - Job encountered error
- `cancelled` - User cancelled job

## Testing

```bash
# Test plugin activation
wp plugin activate storyos

# Test generation endpoint
curl -X POST http://localhost/wp-json/storyos/v1/generation \
  -H "Content-Type: application/json" \
  -d '{
    "type": "image",
    "prompt": "A dramatic scene",
    "provider_type": "comfy_cloud_mcp",
    "connection_id": 1,
    "workflow": "character-sheet"
  }'
```

## Migration Notes

If upgrading from Python orchestrator version:
1. Backup all post data
2. Ensure Comfy Cloud MCP API key is set
3. Run capability sync to cache provider descriptors
4. Test WP-Cron is functional
5. Submit test generation job and verify WP-Cron processing
