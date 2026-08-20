# World Graph Studio ↔ Celtx Integration

> **Version:** 1.0.0  
> **Date:** 2026-08-08  
> **Status:** Draft — for Celtx technical team review

---

## 1. Overview

World Graph Studio is an open-source production management platform for interactive storytelling, games, and transmedia projects. This document describes the integration between World Graph Studio and the **Celtx GEM Bi-Directional API** (v1.0.4), enabling bidirectional synchronization of production elements between the two platforms.

### Goals

- Synchronize World Graph Studio custom post types (CPTs) with Celtx elements (characters, locations, props, scenes, scripts).
- Maintain a persistent mapping between World Graph Studio and Celtx element IDs for bidirectional sync.
- Provide a WordPress-native integration using only WordPress core HTTP functions (`wp_remote_get`, `wp_remote_post`).

---

## 2. Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                      WordPress / World Graph Studio                      │
│                                                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│  │ worldgraph_      │  │ worldgraph_     │  │ worldgraph_         │   │
│  │ project      │  │ character    │  │ scene            │   │
│  │ worldgraph_     │  │ worldgraph_     │  │ worldgraph_         │   │
│  │ location     │  │ shot         │  │ asset            │   │
│  └──────┬───────┘  └──────┬───────┘  └────────┬─────────┘   │
│         │                  │                    │             │
│         └──────────────────┼────────────────────┘             │
│                            │                                   │
│                    ┌───────▼────────┐                          │
│                    │  Sync Service   │                         │
│                    │  (class-celtx-  │                          │
│                    │   sync.php)     │                          │
│                    └───────┬────────┘                          │
│                            │                                   │
│                    ┌───────▼────────┐                          │
│                    │  API Client     │                          │
│                    │  (class-celtx-  │                          │
│                    │   api.php)      │                          │
│                    └───────┬────────┘                          │
│                            │                                   │
│                    ┌───────▼────────┐                          │
│                    │  wp_remote_*    │                          │
│                    │  (WordPress)    │                          │
│                    └───────┬────────┘                          │
└────────────────────────────┼──────────────────────────────────┘
                             │
                    HTTPS / JSON
                             │
                             ▼
┌──────────────────────────────────────────────────────────────┐
│                    Celtx GEM API                              │
│              games-api.celtx.com/api                          │
│                                                               │
│  /project  /episode  /scene  /element  /script                │
│  /comment  /catalog  /breakdown  /custom_field                │
└──────────────────────────────────────────────────────────────┘
```

---

## 3. Authentication

### 3.1 API Key (Primary)

The integration uses the `x-api-key` header for authentication. This is the recommended method for server-to-server integration.

```
GET /api/project HTTP/1.1
Accept: application/json
x-api-key: YOUR_API_TOKEN
X-Project-ID: YOUR_PROJECT_ID
```

**How to obtain:**
1. Navigate to **Project View → Management → Settings** in the Celtx app.
2. Under **Security**, find or generate an API token.
3. Copy the token and store it securely in World Graph Studio settings.

### 3.2 Basic Auth (Alternative)

```
Authorization: Basic base64(username:password)
```

### 3.3 Cookie Auth (Session-based)

```
Cookie: cx_session=YOUR_SESSION_TOKEN
```

### 3.4 Required Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Accept` | **Yes** | Must be `application/json`. Without it, responses may be base64-encoded. |
| `x-api-key` | **Yes** (if no cookie) | API token for authentication. |
| `X-Project-ID` | **Yes** | The Celtx project identifier. |
| `Content-Type` | **Yes** (POST/PUT) | Must be `application/json` when sending payloads. |
| `Accept-Encoding` | Conditional | Required for large payloads. Supports `gzip` and `deflate`. |

---

## 4. Element Mapping

The integration maps World Graph Studio CPTs to Celtx elements as follows:

| World Graph Studio CPT | Celtx Target | Storage |
|-------------|-------------|---------|
| `worldgraph_project` | Celtx Project (Elements) | `_worldgraph_celtx_mapping` post meta |
| `worldgraph_character` | Celtx Element (`category=character`) | `_worldgraph_celtx_mapping` post meta |
| `worldgraph_location` | Celtx Element (`category=location`) | `_worldgraph_celtx_mapping` post meta |
| `worldgraph_scene` | Celtx Scene (episode/scene endpoints) | `_worldgraph_celtx_mapping` post meta |
| `worldgraph_shot` | Celtx Scene Comment (no direct shot concept) | `_worldgraph_celtx_mapping` post meta |
| `worldgraph_asset` / `worldgraph_prop` | Celtx Element (`category=prop`) | `_worldgraph_celtx_mapping` post meta |

### Mapping Data Structure

Each World Graph Studio post stores its Celtx mapping in post meta under the key `_worldgraph_celtx_mapping`:

```json
{
  "character": {
    "element_id": "abc123def456",
    "synced_at": "2026-08-08 12:00:00"
  },
  "location": {
    "element_id": "xyz789ghi012",
    "synced_at": "2026-08-08 12:00:05"
  }
}
```

---

## 5. Sync Behavior

### 5.1 Project Sync

- **First sync:** Creates a new project element in Celtx.
- **Subsequent syncs:** Updates the existing project element.
- The project acts as the container for all other synced elements.

**World Graph Studio fields → Celtx mapping:**

| World Graph Studio Meta Key | Celtx Field |
|------------------|-------------|
| `project_name` | `name` |
| `description` | `description` |
| `post_title` | `name` (fallback) |
| `post_content` | `description` (fallback) |

### 5.2 Character Sync

- **First sync:** Creates a new Celtx element with `category: "character"`.
- **Subsequent syncs:** Updates the existing character element.

**World Graph Studio fields → Celtx mapping:**

| World Graph Studio Meta Key | Celtx Field |
|------------------|-------------|
| `display_name` | `name` |
| `biography` | `description` |
| `backstory` | `description` (fallback) |
| `age` | `attributes.age` |
| `appearance` | `attributes.appearance` |
| `personality` | `attributes.personality` |
| `motivation` | `attributes.motivation` |
| `voice_profile` | `attributes.voice_profile` |

### 5.3 Location Sync

- **First sync:** Creates a new Celtx element with `category: "location"`.
- **Subsequent syncs:** Updates the existing location element.

**World Graph Studio fields → Celtx mapping:**

| World Graph Studio Meta Key | Celtx Field |
|------------------|-------------|
| `location_name` | `name` |
| `description` | `description` |
| `environment_type` | `attributes.environment_type` |
| `geography` | `attributes.geography` |
| `mood` | `attributes.mood` |

### 5.4 Scene Sync

- **First sync:** Creates a new scene via the Celtx episode/scene endpoint.
- **Subsequent syncs:** Updates the existing scene.

### 5.5 Shot Sync

- **Rationale:** Celtx has no direct "shot" concept.
- **Approach:** Shots are stored as **scene comments** in Celtx.
- Each World Graph Studio shot creates or updates a comment on the corresponding Celtx scene.

### 5.6 Asset / Prop Sync

- **First sync:** Creates a new Celtx element with `category: "prop"`.
- **Subsequent syncs:** Updates the existing prop element.

---

## 6. API Endpoints Used

The integration uses the following Celtx GEM API endpoints:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/project` | `GET` | Retrieve project list / verify connection |
| `/api/status` | `GET` | Health check / API status |
| `/api/episode` | `GET` | List episodes in a project |
| `/api/episode/{episode_id}` | `GET` | Get episode details |
| `/api/episode/{episode_id}/scene` | `GET` | List scenes in an episode |
| `/api/episode/{episode_id}/scene` | `POST` | Create a new scene |
| `/api/scene/{scene_id}` | `GET` | Get scene details |
| `/api/scene/{scene_id}` | `PUT` | Update a scene |
| `/api/element` | `GET` | List all elements |
| `/api/element/{element_id}` | `GET` | Get element details |
| `/api/element` | `POST` | Create a new element |
| `/api/element/{element_id}` | `PUT` | Update an element |
| `/api/element/{element_id}` | `DELETE` | Delete an element |
| `/api/comment/{element_id}` | `GET` | Get comments on an element |
| `/api/comment/{element_id}` | `POST` | Add a comment (used for shots) |

---

## 7. REST API Endpoints (World Graph Studio Side)

World Graph Studio exposes the following internal REST API endpoints for managing Celtx sync:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/worldgraph/v1/celtx/test` | Test Celtx API connection |
| `POST` | `/worldgraph/v1/celtx/sync` | Sync all World Graph Studio elements |
| `GET` | `/worldgraph/v1/celtx/sync` | Get sync status |
| `POST` | `/worldgraph/v1/celtx/sync/{type}` | Sync all elements of a type |
| `POST` | `/worldgraph/v1/celtx/sync/{type}/{id}` | Sync an individual element |
| `GET` | `/worldgraph/v1/celtx/mapping/{type}/{id}` | Get Celtx mapping for an element |
| `DELETE` | `/worldgraph/v1/celtx/unsync/{type}/{id}` | Remove Celtx mapping |

**Authentication:** Requires WordPress admin privileges (`manage_options` capability).

**Example — Test Connection:**

```bash
curl -X GET "http://worldgraph.local/wp-json/worldgraph/v1/celtx/test" \
  -H "Authorization: Bearer YOUR_WP_JWT_TOKEN"
```

**Example — Sync All:**

```bash
curl -X POST "http://worldgraph.local/wp-json/worldgraph/v1/celtx/sync" \
  -H "Authorization: Bearer YOUR_WP_JWT_TOKEN"
```

---

## 8. Error Handling

### 8.1 HTTP Errors

The integration handles WordPress `WP_Error` objects and Celtx API error responses:

```php
// Response parsing returns:
[
    'status'  => 404,
    'headers' => (object)[...],
    'body'    => null,
    'error'   => 'Not Found',
]
```

### 8.2 Common Error Scenarios

| Scenario | Response | Action |
|----------|----------|--------|
| Missing credentials | 400 Bad Request | Configure API key in World Graph Studio settings |
| Invalid API key | 401 Unauthorized | Regenerate API token in Celtx |
| Project not found | 404 Not Found | Verify project ID is correct |
| Rate limiting | 429 Too Many Requests | Retry with exponential backoff |
| Network failure | `WP_Error` | Log and retry on next sync cycle |

### 8.3 Known Celtx Limitations

From the Celtx API documentation:

- **New Projects:** Projects must be loaded via the app editor and have changes saved before being usable via API.
- **Mass Transactions:** Excessive repeated calls against a single element may compromise data integrity. Sync operations should be throttled.

---

## 9. Request/Response Examples

### 9.1 Create Character Element

**Request:**

```http
POST /api/element HTTP/1.1
Accept: application/json
Content-Type: application/json
x-api-key: YOUR_API_TOKEN
X-Project-ID: YOUR_PROJECT_ID

{
  "name": "Dr. Elena Vasquez",
  "category": "character",
  "description": "Lead scientist and protagonist...",
  "attributes": {
    "age": "35",
    "appearance": "Tall, dark hair, lab coat",
    "personality": "Determined, empathetic",
    "motivation": "Discover the truth",
    "voice_profile": "Calm, authoritative"
  }
}
```

**Response:**

```json
{
  "id": "elena_vasquez_001",
  "name": "Dr. Elena Vasquez",
  "category": "character",
  "description": "Lead scientist and protagonist...",
  "attributes": {
    "age": "35",
    "appearance": "Tall, dark hair, lab coat",
    "personality": "Determined, empathetic",
    "motivation": "Discover the truth",
    "voice_profile": "Calm, authoritative"
  },
  "created_at": "2026-08-08T12:00:00Z",
  "updated_at": "2026-08-08T12:00:00Z"
}
```

### 9.2 Add Shot as Scene Comment

**Request:**

```http
POST /api/comment/{scene_id} HTTP/1.1
Accept: application/json
Content-Type: application/json
x-api-key: YOUR_API_TOKEN
X-Project-ID: YOUR_PROJECT_ID

{
  "content": "Shot 1A: Wide establishing shot of the lab. Camera pans left to reveal Dr. Vasquez at the console.",
  "type": "shot_note"
}
```

---

## 10. Open Questions for Celtx

We would appreciate clarification on the following:

1. **Project Creation via API:** The docs mention new projects must be loaded via the app editor first. Is there a planned API endpoint for programmatic project creation, or is this a permanent limitation?

2. **Element Categories:** Beyond `character`, `location`, and `prop`, what other element categories are supported? Can we define custom categories?

3. **Webhooks / Callbacks:** Does Celtx support webhooks for real-time sync notifications when elements are modified in the Celtx app? This would enable bidirectional sync in the reverse direction (Celtx → World Graph Studio).

4. **Rate Limits:** What are the official rate limits for the GEM API? Are there different limits for free vs. paid tiers?

5. **Batch Operations:** Are batch create/update endpoints planned? This would significantly improve sync performance for large productions.

6. **Scene Comments for Shots:** Is there a preferred or alternative approach for mapping shot-level data to Celtx? We're currently using comments, but there may be a better pattern.

7. **Custom Fields:** How should custom fields be mapped and synchronized? Are there conventions we should follow?

---

## 11. File Structure

```
wordpress/wp-content/plugins/worldgraph/plugins/celtx/
├── celtx-sync.php                          # Main plugin file (header, autoloader, init)
├── instructions.md                         # This file
└── includes/
    ├── class-celtx-api.php                 # API client (HTTP layer)
    ├── class-celtx-settings.php            # Admin settings page
    ├── class-celtx-sync.php                # Sync service (mapping, element sync)
    └── rest-api/
        └── sync-controller.php             # WordPress REST API endpoints
```

---

## 12. Technical Stack

| Component | Technology |
|-----------|-----------|
| Platform | WordPress (PHP) |
| HTTP Layer | WordPress native `wp_remote_get`, `wp_remote_post`, `wp_remote_request` |
| Data Format | JSON |
| API Protocol | REST over HTTPS |
| Celtx API | GEM Bi-Directional API v1.0.4 (OpenAPI 3.0.0) |
| Base URL | `https://games-api.celtx.com/api` |

---

## 13. Contact

For questions or support regarding this integration, please reach out to the World Graph Studio development team.

---

*This document is a living document and will be updated as the integration evolves and as feedback is received from the Celtx team.*
