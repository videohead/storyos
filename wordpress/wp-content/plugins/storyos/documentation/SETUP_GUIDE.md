# StoryOS Plugin Setup & Migration Guide

## Quick Start

### Prerequisites
- WordPress 6.0+ (WordPress 6.9+ recommended for full AI Abilities support)
- PHP 8.1+
- Secure Custom Fields (SCF) plugin
- API keys for:
  - **Comfy Cloud MCP** (for image/video generation) - optional
  - **LLM Provider** (OpenAI, Anthropic, or local compatible) - required for AI agents
  - **Fallback LLM** (optional, for failover)

### Installation

1. **Activate the plugin:**
   ```bash
   wp plugin activate storyos
   ```

2. **Complete the Setup Wizard:**
   - Navigate to **StoryOS > Setup** after plugin activation
   - Enter all API keys and configuration options
   - The wizard guides you through:
     - **Generation Connection**: Comfy Cloud MCP (optional)
     - **Primary LLM**: Local or cloud-based LLM configuration
     - **Advanced Settings**: Token limits, creativity settings
     - **Fallback LLM**: Backup provider for failover
   - Submit the form to save all configurations

### Configuration via Setup Wizard

The setup wizard now includes comprehensive API key configuration:

#### 1. Comfy Cloud MCP (Optional)
- **Field**: Comfy Cloud API Key
- **Purpose**: Enable image/video generation via Comfy Cloud MCP
- **Environment Override**: Set `STORYOS_COMFY_API_KEY` constant to skip form input

#### 2. Primary LLM (Required for AI Agents)
- **Provider**: Choose from:
  - OpenAI-compatible (Ollama, llama.cpp, vLLM, LM Studio)
  - OpenAI API
  - Anthropic API
  - Dual (local + fallback cloud)
- **Base URL**: Endpoint for compatible services
- **Model Name**: Model identifier
- **API Key**: Authentication token
- **Environment Override**: Set `STORYOS_AI_API_KEY` constant to skip form input

#### 3. Advanced LLM Settings (Optional)
- **Max Tokens**: Maximum response length (default: 2048)
- **Temperature**: Creativity level 0.0-1.0 (default: 0.7)

#### 4. Fallback LLM (Optional)
- **Fallback Provider**: OpenAI or Anthropic
- **Fallback API Key**: Credentials for backup provider
- **Purpose**: Automatic failover if primary LLM becomes unavailable
- **Environment Override**: Set `STORYOS_AI_FALLBACK_API_KEY` constant to skip form input

### Running the Setup Wizard

After activation, navigate to **StoryOS > Setup** to configure all connections and API keys in one place.

### Verify WP-Cron:
- Configure provider connections
- Set up initial projects and story worlds
- Configure analytics preferences

## Setup Wizard Overview

The StoryOS Setup Wizard provides a comprehensive configuration interface for all API keys and connections. It appears automatically on plugin activation.

### Wizard Sections

#### 1. WordPress Runtime
- Verifies WordPress is properly configured
- Instructions for setting up WP-Cron on production hosts
- Local development setup notes for Lando users

#### 2. Generation Connection (Optional)
Configure media generation via Comfy Cloud MCP:
- **Connection Mode**: Cloud MCP, Local MCP, or None
- **API Key**: Comfy Cloud authentication
- Supports environment variable override: `STORYOS_COMFY_API_KEY`

#### 3. LLM Connection (Required for AI Agents)
Configure AI language models with four subsections:

**Primary LLM Configuration**
- Provider selection (OpenAI-compatible, OpenAI, Anthropic, Dual)
- Base URL or endpoint
- Model name/ID
- API credentials
- Environment override: `STORYOS_AI_API_KEY`

**Advanced LLM Settings**
- Max tokens for responses
- Temperature (creativity level)
- Optional: customize for your use case

**Fallback LLM**
- Backup provider (OpenAI or Anthropic)
- Fallback API credentials
- Enables automatic failover
- Environment override: `STORYOS_AI_FALLBACK_API_KEY`

#### 4. External Generator Workflow
Instructions for manual asset generation when not using API-connected providers.

### Saving Configuration

Clicking "Save All Configurations" will:
1. Validate all inputs
2. Store settings in WordPress options
3. Respect environment variable constants (for production)
4. Redirect to setup page with success message
5. Complete initial setup (setup wizard no longer required)

### Re-Running the Wizard

To reconfigure settings at any time:
```bash
# Re-open setup wizard
wp option update storyos_setup_complete false

# Or navigate to StoryOS > Setup in WordPress admin
```

### Environment Variable Configuration

For production deployments, use environment variables instead of WordPress options:

```bash
# .env or deployment config
STORYOS_COMFY_API_KEY=sk_live_xxx...
STORYOS_AI_API_KEY=sk-xxx...
STORYOS_AI_FALLBACK_API_KEY=sk-xxx...
```

Then in `wp-config.php`:
```php
define( 'STORYOS_COMFY_API_KEY', getenv( 'STORYOS_COMFY_API_KEY' ) );
define( 'STORYOS_AI_API_KEY', getenv( 'STORYOS_AI_API_KEY' ) );
define( 'STORYOS_AI_FALLBACK_API_KEY', getenv( 'STORYOS_AI_FALLBACK_API_KEY' ) );
```

## Configuration without Setup Wizard

If you need to configure settings programmatically or skip the wizard:

### Comfy Cloud MCP
```php
// Set API key
update_option( 'storyos_comfy_api_key', 'sk_live_...' );

// Set connection mode (cloud, local_mcp, or none)
update_option( 'storyos_comfy_connection_mode', 'cloud' );
```

### Primary LLM
```php
update_option( 'storyos_ai_backend', 'openai_compatible' ); // or 'openai', 'anthropic', 'dual'
update_option( 'storyos_ai_url', 'http://localhost:11434/v1' );
update_option( 'storyos_ai_model', 'gpt-4' );
update_option( 'storyos_ai_api_key', 'sk-...' );
update_option( 'storyos_ai_max_tokens', 2048 );
update_option( 'storyos_ai_temperature', 0.7 );
```

### Fallback LLM
```php
update_option( 'storyos_ai_fallback_backend', 'openai' ); // or 'anthropic'
update_option( 'storyos_ai_fallback_api_key', 'sk-...' );
```

### Mark Setup Complete
```php
update_option( 'storyos_setup_complete', true );
```

## Migration from Python Orchestrator

### Overview of Changes

| Component | Old (Python Orchestrator) | New (ComfyUI MCP + WP-Cron) |
|-----------|------------------------|----------------------------|
| **Image Generation** | Python service + external orchestrator | Comfy Cloud MCP + WP-Cron |
| **Job Processing** | Orchestrator handles all tasks | WordPress WP-Cron only |
| **Analytics** | Fetched from orchestrator | Computed locally from posts |
| **Continuity Checks** | Orchestrator intelligence | Local validation rules |
| **Provider Connections** | Managed by orchestrator | WordPress CPT + UI |
| **API Keys** | Orchestrator resolves from env | WordPress options or constants |

### Step-by-Step Migration

#### 1. Backup Everything
```bash
# Backup WordPress database
wp db export backup-$(date +%Y%m%d-%H%M%S).sql

# Backup uploads
tar -czf wp-content-backup-$(date +%Y%m%d-%H%M%S).tar.gz wp-content/
```

#### 2. Complete the StoryOS Setup Wizard
After activating the plugin, the setup wizard will automatically redirect you to **StoryOS > Setup**.

**Configure all API keys in the wizard:**
- Comfy Cloud MCP API key (if using Comfy for generation)
- Primary LLM provider and credentials
- Advanced LLM settings (optional)
- Fallback LLM credentials (optional for cloud failover)

**All settings are saved to WordPress options automatically.**

#### 3. Import Existing Connections
If you have existing provider configurations, they should be imported as `storyos_connection` posts:

```php
// Example: Import a connection programmatically
$connection_id = wp_insert_post([
    'post_type'  => 'storyos_connection',
    'post_title' => 'Comfy Cloud',
    'post_status' => 'publish',
]);

update_post_meta( $connection_id, 'connection_name', 'Comfy Cloud MCP' );
update_post_meta( $connection_id, 'provider_type', 'comfy_cloud_mcp' );
update_post_meta( $connection_id, 'environment', 'production' );
update_post_meta( $connection_id, 'status', 'verified' );
```

#### 4. Migrate Generation Jobs
Old generation jobs stored in the orchestrator will need to be handled separately. The new system stores jobs as `storyos_generation` posts in WordPress.

```bash
# List existing generation jobs
wp post list --post_type=storyos_generation --format=csv
```

#### 5. Test Generation Workflow
```bash
# 1. Create a test asset
ASSET_ID=$(wp post create --post_type=storyos_asset --post_title="Test Asset" \
  --post_status=publish --porcelain)

# 2. Submit a generation request
curl -X POST http://localhost/wp-json/storyos/v1/generation \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $(wp eval 'echo wp_create_nonce("wp_rest");')" \
  -d '{
    "type": "image",
    "prompt": "A dynamic action scene",
    "provider_type": "comfy_cloud_mcp",
    "connection_id": 1,
    "workflow": "character-sheet",
    "asset_id": '$ASSET_ID',
    "params": {
      "style": "cinematic",
      "aspect_ratio": "16:9"
    }
  }'

# 3. Check job status
JOB_ID=1  # From response above
curl http://localhost/wp-json/storyos/v1/generation/$JOB_ID

# 4. Wait for WP-Cron to process
# Job will be picked up automatically by WP-Cron (typically within 60 seconds)
```

#### 6. Verify WP-Cron Processing

Monitor generation job processing:

```php
// In WordPress dashboard or via CLI
$generation_jobs = get_posts([
    'post_type'      => 'storyos_generation',
    'posts_per_page' => -1,
    'meta_query'     => [[
        'key'   => '_storyos_generation_status',
        'value' => ['queued', 'submitted'],
        'compare' => 'IN'
    ]]
]);

echo "Active jobs: " . count( $generation_jobs );
foreach ( $generation_jobs as $job ) {
    $status = get_post_meta( $job->ID, '_storyos_generation_status', true );
    echo "Job {$job->ID}: $status\n";
}
```

### Troubleshooting

#### WP-Cron Not Running

**Problem:** Generation jobs stay in "queued" status indefinitely.

**Solution 1: Enable real WP-Cron**
```bash
# Add to wp-config.php
define( 'DISABLE_WP_CRON', false );
```

**Solution 2: Set up external cron**
```bash
# Add to system crontab (run every 5 minutes)
*/5 * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron=1 > /dev/null 2>&1
```

**Solution 3: Use WP-CLI for manual processing**
```bash
# Manually trigger processing
wp eval 'StoryOS\Utils\Generation_Batch::process();'
```

#### API Key Configuration Issues

**Problem:** "API key is not configured" error

**Solution:**

The setup wizard handles all API key configuration. However, you can also set them programmatically:

```bash
# Comfy Cloud MCP API Key
wp option update storyos_comfy_api_key 'sk_live_xxx...'

# LLM API Key
wp option update storyos_ai_api_key 'sk-xxx...'

# Fallback LLM API Key
wp option update storyos_ai_fallback_api_key 'sk-xxx...'

# Or define constants in wp-config.php (takes precedence)
define( 'STORYOS_COMFY_API_KEY', 'sk_live_xxx...' );
define( 'STORYOS_AI_API_KEY', 'sk-xxx...' );
define( 'STORYOS_AI_FALLBACK_API_KEY', 'sk-xxx...' );
```

Verify configuration:
```bash
wp eval 'echo "Comfy: " . (defined("STORYOS_COMFY_API_KEY") || get_option("storyos_comfy_api_key") ? "OK" : "NOT SET") . PHP_EOL;'
wp eval 'echo "LLM: " . (defined("STORYOS_AI_API_KEY") || get_option("storyos_ai_api_key") ? "OK" : "NOT SET") . PHP_EOL;'
wp eval 'echo "Fallback: " . (defined("STORYOS_AI_FALLBACK_API_KEY") || get_option("storyos_ai_fallback_api_key") ? "OK" : "NOT SET") . PHP_EOL;'
```

#### Connection Testing Fails

**Problem:** "Connection verified" doesn't appear

**Solution:**
```bash
# Test Comfy Cloud API directly
curl -X POST https://cloud.comfy.org/mcp \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "jsonrpc": "2.0",
    "id": "test",
    "method": "initialize",
    "params": {
      "protocolVersion": "2025-03-26",
      "capabilities": {},
      "clientInfo": {"name": "Test", "version": "1.0"}
    }
  }'
```

## Development Guide

### Adding Custom Workflows

Workflows are referenced by slug in generation requests. Define custom workflows:

**Via REST API:**
```php
$workflow_id = wp_insert_post([
    'post_type'  => 'storyos_template',
    'post_title' => 'My Custom Workflow',
    'post_status' => 'publish',
]);

update_post_meta( $workflow_id, '_storyos_template_category', 'workflow' );
update_post_meta( $workflow_id, '_storyos_template_slug', 'my-custom-workflow' );
update_post_meta( $workflow_id, '_storyos_template_schema', [
    'inputs' => [
        'style' => [ 'type' => 'string', 'default' => 'cinematic' ],
        'quality' => [ 'type' => 'integer', 'default' => 100 ],
    ]
]);
```

### Custom Abilities

Add custom AI abilities to expose via WordPress Agent API:

**File: `includes/ai-editor/abilities/my-ability.php`**
```php
<?php
namespace StoryOS\AI\Abilities;

class My_Ability extends AbstractAbilityGroup {
    protected $slug = 'storyos_my_custom';
    protected $label = 'My Custom Group';
    
    public function register(): void {
        $this->register_ability( 'storyos/my_action', [
            'label'       => 'My Custom Action',
            'description' => 'Does something cool',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'input' => [ 'type' => 'string' ]
                ]
            ],
            'execute_callback' => [ $this, 'execute' ],
        ]);
    }
    
    public function execute( $input ) {
        return [ 'result' => $input ];
    }
}
```

### Monitoring Generation Jobs

```php
// Get all active jobs
$active = get_posts([
    'post_type' => 'storyos_generation',
    'meta_query' => [[
        'key' => '_storyos_generation_status',
        'value' => ['queued', 'submitted'],
        'compare' => 'IN'
    ]]
]);

// Get failed jobs
$failed = get_posts([
    'post_type' => 'storyos_generation',
    'meta_key' => '_storyos_generation_status',
    'meta_value' => 'failed'
]);

// Get completion stats
echo "Active: " . count( $active ) . "\n";
echo "Failed: " . count( $failed ) . "\n";
```

## Performance Considerations

### WP-Cron Timing
- Default interval: 60 seconds between batch processing
- Can handle 5 queued jobs and 10 submitted jobs per cycle
- Adjust in `Generation_Batch::process()` as needed

### Analytics Caching
- Graph analytics are cached in transients
- Cache expires in 1 hour by default
- Manual refresh via "Clear Cache" button in analytics panel

### Database Optimization
```sql
-- Index generation jobs for faster status queries
ALTER TABLE wp_postmeta ADD INDEX idx_generation_status 
  (post_id, meta_key, meta_value(50));

-- Index relationships
ALTER TABLE wp_posts ADD INDEX idx_post_type_status 
  (post_type, post_status);
```

## Next Steps

1. ✓ Set Comfy Cloud MCP API key
2. ✓ Verify WP-Cron is working
3. ✓ Create test provider connection
4. ✓ Submit test generation job
5. ✓ Monitor WP-Cron processing
6. ✓ Review Story Graph analytics
7. ✓ Configure custom workflows (optional)
8. ✓ Set up monitoring and alerts (optional)

## Support & Resources

- **Documentation:** See `ARCHITECTURE.md`
- **Issues:** Check plugin health via "Connections" admin panel
- **Logs:** Generation jobs are stored as posts, check post meta for errors
- **REST API:** Full OpenAPI documentation available at `/wp-json/storyos/v1/`
