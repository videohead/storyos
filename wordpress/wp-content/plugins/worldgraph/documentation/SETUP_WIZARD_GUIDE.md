# World Graph Studio Setup Wizard - API Key Configuration

## Overview

All API keys for the World Graph Studio plugin can now be configured through the integrated setup wizard that appears on plugin activation. This eliminates the need for manual environment variable configuration or direct WordPress option editing.

## What's New

### Comprehensive Wizard Integration

The setup wizard now includes **complete API key configuration** for all World Graph Studio components:

#### 1. **Comfy Cloud MCP** (Optional)
- Configure image/video generation provider
- Set API credentials in wizard
- Supports environment variable override

#### 2. **Primary LLM** (Required for AI Agents)
Configure your main AI language model:
- **Provider Selection**
  - OpenAI-compatible (local: Ollama, llama.cpp, vLLM, LM Studio)
  - OpenAI API
  - Anthropic API  
  - Dual mode (local + fallback cloud)
- **Endpoint Configuration**
  - Base URL for compatible services
  - Model name/identifier
  - API credentials
- **Advanced Settings** (Optional)
  - Max tokens (default: 2048)
  - Temperature/creativity (default: 0.7)

#### 3. **Fallback LLM** (Optional)
Configure backup AI provider for failover:
- **Provider Selection** (OpenAI or Anthropic)
- **Credentials** for automatic failover scenarios

## Using the Setup Wizard

### First-Time Setup (Automatic)

1. Activate the plugin
2. Wizard redirects to **World Graph Studio > Setup**
3. Fill in all configuration sections
4. Click "Save All Configurations"
5. Settings saved, setup complete

### Reconfiguring Later

```bash
# Re-open wizard (if needed)
wp option update worldgraph_setup_complete false
```

Then navigate to **World Graph Studio > Setup** in WordPress admin.

## API Key Fields

| Setting | Location | Purpose | Environment Var |
|---------|----------|---------|-----------------|
| Comfy Cloud API Key | Section 2 | Generation service | `WORLDGRAPH_COMFY_API_KEY` |
| LLM API Key | Section 3 (Primary) | Main AI provider | `WORLDGRAPH_AI_API_KEY` |
| Fallback API Key | Section 3 (Fallback) | Backup AI provider | `WORLDGRAPH_AI_FALLBACK_API_KEY` |

## Environment Variable Support

The wizard respects environment variables for production deployments:

```php
// In wp-config.php
define( 'WORLDGRAPH_COMFY_API_KEY', getenv( 'WORLDGRAPH_COMFY_API_KEY' ) );
define( 'WORLDGRAPH_AI_API_KEY', getenv( 'WORLDGRAPH_AI_API_KEY' ) );
define( 'WORLDGRAPH_AI_FALLBACK_API_KEY', getenv( 'WORLDGRAPH_AI_FALLBACK_API_KEY' ) );
```

When constants are defined:
- Wizard displays fields as **disabled** (read-only)
- Shows note: "Configured through the deployment environment"
- Settings cannot be changed in WordPress admin
- Ideal for production security

## Configuration Storage

All settings are stored as WordPress options:

| Option | Value Type | Default |
|--------|-----------|---------|
| `worldgraph_gen_connection_mode` | string | `none` |
| `worldgraph_comfy_connection_mode` | string | `none` |
| `worldgraph_comfy_api_key` | string | empty |
| `worldgraph_ai_backend` | string | `openai_compatible` |
| `worldgraph_ai_url` | string | `http://localhost:11434/v1` |
| `worldgraph_ai_model` | string | empty |
| `worldgraph_ai_api_key` | string | empty |
| `worldgraph_ai_max_tokens` | integer | `2048` |
| `worldgraph_ai_temperature` | float | `0.7` |
| `worldgraph_ai_fallback_backend` | string | `openai` |
| `worldgraph_ai_fallback_api_key` | string | empty |
| `worldgraph_setup_complete` | boolean | `true` (after wizard) |

## Security Best Practices

### For Development
- Use wizard to save API keys
- Keys stored in WordPress options (database)
- Accessible via WordPress admin panel
- Good for local/staging environments

### For Production
- Define constants in `wp-config.php` using environment variables
- Keys NOT stored in database
- Wizard shows fields as disabled
- Recommended for security and compliance
- Environment variables can be managed by deployment system

## Wizard Form Flow

```
1. WordPress Runtime
   ↓ (Status: Connected)
2. Generation Connection (Optional)
	├─ Preferred Connection dropdown
	└─ Hosted provider API key (fal, Comfy Cloud, or ElevenLabs)
3. LLM Connection (Required for AI Agents)
   ├─ Primary Configuration
   │  ├─ Provider selection
   │  ├─ Endpoint/URL
   │  ├─ Model name
   │  └─ API credentials
   ├─ Advanced Settings
   │  ├─ Max tokens
   │  └─ Temperature
   └─ Fallback Configuration
      ├─ Fallback provider
      └─ Fallback API key
4. External Generator Workflow
   └─ (Instructions only)
   ↓
Save All Configurations → Setup Complete
```

## Troubleshooting

### API Key Not Appearing in Wizard

**Check if using environment variables:**
```php
// If defined in wp-config.php
if ( defined( 'WORLDGRAPH_COMFY_API_KEY' ) ) {
    // Field is disabled/read-only in wizard
    echo "Using environment variable";
}
```

### Lost Wizard Access

To restore setup wizard access:
```bash
wp option update worldgraph_setup_complete false
```

### Verifying Configuration

```bash
# Check what's saved
wp option get worldgraph_comfy_connection_mode
wp option get worldgraph_ai_backend
wp option get worldgraph_ai_fallback_backend

# Verify environment variables
wp eval 'echo defined("WORLDGRAPH_COMFY_API_KEY") ? "Set" : "Not set";'
wp eval 'echo defined("WORLDGRAPH_AI_API_KEY") ? "Set" : "Not set";'
wp eval 'echo defined("WORLDGRAPH_AI_FALLBACK_API_KEY") ? "Set" : "Not set";'
```

## Migration from Manual Configuration

If you previously set API keys manually:

### Option 1: Use Wizard (Recommended)
```bash
# Reset setup flag
wp option update worldgraph_setup_complete false

# Complete wizard at World Graph Studio > Setup
```

### Option 2: Keep Existing Settings
Existing WordPress options are preserved. Wizard will load them on next access.

### Option 3: Use Environment Variables
Define constants in `wp-config.php`. Wizard will display as read-only.

## Next Steps

1. ✅ Activate plugin
2. ✅ Complete setup wizard with all API keys
3. ✅ Test generation workflow
4. ✅ Configure WP-Cron for production
5. ✅ (Optional) Switch to environment variables for production

## Support

For issues with the setup wizard:
- Check SETUP_GUIDE.md for troubleshooting
- Visit World Graph Studio > Setup to reconfigure
- Review WordPress options for saved settings
- Verify environment variables if using constants
