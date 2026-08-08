# StoryOS Web Stories Sync Plugin

## Overview

The StoryOS Web Stories Sync plugin enables bidirectional synchronization between StoryOS elements (Scenes, Storyboard Frames) and Google Web Stories. Creators can export StoryOS scenes to interactive Web Stories for web publishing, or import completed Web Stories back into StoryOS for production tracking and asset management.

**Status**: 📋 Implemented — StoryOS Plugin (requires Web Stories plugin by Google)

## Supported Integrations

| Source | Target | Direction | Status |
|--------|--------|-----------|--------|
| StoryOS Scene | Web Story | Export | ✅ |
| Web Story | StoryOS Scene | Import | ✅ |
| StoryOS Storyboard Frame | Web Story Page | Export | ✅ |
| Web Story Page | StoryOS Scene | Import | ✅ |

## Features

### Export: StoryOS → Web Stories
- Convert StoryOS Scenes to Web Stories with one click
- Map scene content (summary, script, production notes) to Web Story pages
- Optional Storyboard Frame integration — each frame becomes a Web Story page
- Configurable content source: summary, script content, post content, or combined
- Automatic scene numbering and metadata preservation
- Support for draft, published, and archived story statuses

### Import: Web Stories → StoryOS
- Import Web Stories as StoryOS Scenes for production tracking
- Extract text content from Web Story pages into scene script content
- Preserve page structure and element counts
- Bidirectional sync — updates in either system propagate to the other
- Mapping persistence — tracked via post meta for reliable sync

### Sync Management
- **Bidirectional sync** — changes flow both ways
- **One-way sync** — StoryOS to Web Stories only, or Web Stories to StoryOS only
- **Auto-sync on save** — automatically sync when a Scene or Story is saved
- **Manual sync** — trigger sync via REST API or admin UI
- **Sync status dashboard** — view mapped items, sync counts, and configuration

### REST API
- Full REST API for programmatic sync operations
- Endpoints for syncing individual items, bulk sync, and status checks
- Settings management via API
- Permission-based access control

## Architecture

```
StoryOS (WordPress)                    Web Stories (WordPress)
┌─────────────────────┐               ┌─────────────────────┐
│  Scene CPT          │  ←── sync ──→ │  web-story CPT      │
│  - summary          │   mapping     │  - title            │
│  - script_content   │               │  - story_data (JSON)│
│  - location         │               │    - pages[]        │
│  - time_of_day      │               │  - poster_image     │
│  - emotional_tone   │               └─────────────────────┘
│  - production_notes │
├─────────────────────┤
│  Storyboard Frame   │  ←── sync ──→ │  web-story pages[]  │
│  CPT                │   optional    │  (one per frame)    │
│  - frame_number     │               └─────────────────────┘
│  - frame_description│
│  - image_asset      │
├─────────────────────┤
│  Shot CPT           │               ┌─────────────────────┐
│  (future support)   │               │  REST API           │
└─────────────────────┘               │  /sync/story/{id}   │
                                      │  /sync/scene/{id}   │
  Post Meta:                          │  /sync/all          │
  _storyos_web_stories_mapping        │  /mapping/{id}      │
  {                                  │  /status            │
    "scene_id": 42,                   │  /settings          │
    "story_id": 137,                  │  }
    "synced_at": "2026-08-08"         └─────────────────────┘
  }
```

## Installation

### Prerequisites

1. **StoryOS** — installed and activated
2. **Web Stories by Google** — installed and activated ([WordPress Plugin](https://wordpress.org/plugins/web-stories/))
3. **PHP 8.1+** — required for type declarations
4. **WordPress 6.0+** — required for REST API features

### Setup

1. The plugin is included with StoryOS at `wordpress/wp-content/plugins/storyos/plugins/web-stories/`
2. Activate both StoryOS and Web Stories plugins
3. Navigate to **StoryOS → Plugins → Web Stories Sync**
4. Configure sync settings:
   - **Enable Sync** — toggle to activate synchronization
   - **Sync Direction** — choose bidirectional or one-way
   - **Auto Sync on Save** — enable automatic sync on content changes
   - **Sync Storyboard Frames** — include storyboard frames as additional pages
   - **Default Status** — draft, published, or archived for new stories
   - **Create Pages From** — choose content source for Web Story pages
5. Save settings and begin syncing

## Usage

### Syncing a Scene to Web Story

1. Navigate to **StoryOS → Scenes**
2. Open or create a Scene
3. Click **Sync to Web Story** (or save to trigger auto-sync)
4. A new Web Story is created with content from the scene
5. The mapping is stored — future changes sync automatically

### Syncing a Web Story to Scene

1. Navigate to **Web Stories → All Stories**
2. Open or create a Web Story
3. Click **Sync to StoryOS** (or save to trigger auto-sync)
4. A new StoryOS Scene is created with content from the story
5. The mapping is stored — future changes sync automatically

### Bulk Sync

1. Go to **StoryOS → Web Stories → Sync**
2. Click **Sync All Mapped Items**
3. All mapped Scenes and Web Stories are synchronized
4. Review sync results and any errors

### REST API Usage

```bash
# Sync a specific scene to Web Story
curl -X POST https://yoursite.com/wp-json/storyos-web-stories/v1/sync/scene/42 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Sync a specific Web Story to Scene
curl -X POST https://yoursite.com/wp-json/storyos-web-stories/v1/sync/story/137 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Sync all mapped items
curl -X POST https://yoursite.com/wp-json/storyos-web-stories/v1/sync/all \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get sync status
curl https://yoursite.com/wp-json/storyos-web-stories/v1/status \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get sync settings
curl https://yoursite.com/wp-json/storyos-web-stories/v1/settings \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update sync settings
curl -X POST https://yoursite.com/wp-json/storyos-web-stories/v1/settings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sync_enabled": true, "sync_direction": "bidirectional"}'
```

## Content Mapping

### Scene → Web Story Page Content

The plugin converts StoryOS scene content into Web Story pages based on the configured **Create Pages From** setting:

| Setting | Content Source |
|---------|---------------|
| Summary | Scene summary field only |
| Script | Scene script content only |
| Content | Post content only |
| Combined | Summary + Script + Content (each paragraph becomes a page) |

### Web Story Page → Scene Content

Web Story pages are converted to StoryOS scene content:

- **Text elements** → Extracted and combined into script content
- **Media elements** → Noted in page structure (image assets not auto-imported)
- **Page count** → Stored as metadata for reference

### Metadata Preservation

The following metadata is preserved during sync:

| StoryOS Field | Web Stories Equivalent |
|---------------|----------------------|
| `scene_number` | Mapped to story ordering |
| `title` | Web Story title |
| `summary` | Web Story excerpt |
| `script_content` | Web Story page content |
| `time_of_day` | Stored as scene metadata |
| `location` | Stored as scene metadata |
| `emotional_tone` | Stored as scene metadata |

## Settings Reference

| Setting | Default | Description |
|---------|---------|-------------|
| `sync_enabled` | `false` | Enable/disable synchronization |
| `sync_direction` | `bidirectional` | Sync direction: `bidirectional`, `storyos_to_web`, `web_to_storyos` |
| `auto_sync_on_save` | `true` | Automatically sync on content save |
| `sync_storyboard` | `false` | Include Storyboard Frames as additional pages |
| `default_status` | `draft` | Default status for new Web Stories |
| `create_pages_from` | `summary` | Content source for Web Story pages |

## REST API Reference

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/storyos-web-stories/v1/sync/story/{id}` | Sync Web Story → Scene |
| `POST` | `/storyos-web-stories/v1/sync/scene/{id}` | Sync Scene → Web Story |
| `POST` | `/storyos-web-stories/v1/sync/all` | Sync all mapped items |
| `GET` | `/storyos-web-stories/v1/mapping/{id}` | Get sync mapping for a post |
| `GET` | `/storyos-web-stories/v1/status` | Get sync status and counts |
| `GET` | `/storyos-web-stories/v1/settings` | Get sync settings |
| `POST` | `/storyos-web-stories/v1/settings` | Update sync settings |

### Response Format

All endpoints return a consistent JSON response:

```json
{
  "success": true,
  "message": "Story synced successfully.",
  "data": {
    "action": "created",
    "scene_id": 42,
    "story_id": 137,
    "pages": 5,
    "mapping": {
      "scene_id": 42,
      "story_id": 137,
      "synced_at": "2026-08-08T12:00:00"
    }
  }
}
```

## File Structure

```
wordpress/wp-content/plugins/storyos/plugins/web-stories/
├── web-stories.php                          # Main plugin file
├── includes/
│   ├── class-web-stories-api.php            # REST API client
│   ├── class-web-stories-settings.php       # Admin settings
│   ├── class-web-stories-sync.php           # Sync service
│   └── rest-api/
│       └── class-sync-controller.php        # REST endpoints
```

## Limitations

- **Media assets** — Images and media from Web Story pages are not automatically imported into StoryOS media library
- **Storyboard Frames** — Optional feature, must be enabled in settings
- **Shots** — Shot CPT sync is planned for future releases
- **Web Stories plugin required** — Both StoryOS and Web Stories plugins must be active

## Future Enhancements

- [ ] Shot CPT sync support
- [ ] Media asset import/export
- [ ] Custom Web Story templates from StoryOS
- [ ] Batch import from Web Stories
- [ ] Web Story embedding in StoryOS Scene editor
- [ ] Custom color schemes and branding
- [ ] Web Story analytics integration
- [ ] Support for Web Stories video pages

## Dependencies

| Dependency | Version | Required |
|------------|---------|----------|
| WordPress | 6.0+ | ✅ |
| PHP | 8.1+ | ✅ |
| StoryOS | Latest | ✅ |
| Web Stories by Google | Latest | ✅ |

## License

GPL v2 or later — same as WordPress.

## Support

For issues, feature requests, or contributions, please refer to the StoryOS documentation and governance guidelines.
