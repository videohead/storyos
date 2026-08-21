# Text to Video with Local ComfyUI MCP

Use this sequence to enable WAN and LTX local templates through the ComfyUI
Connection.

## 1. Configure local endpoints

In WordPress Admin, open World Graph Studio > Setup & Settings and select
Local ComfyUI HTTP API + MCP.

- Local ComfyUI API URL: `http://host.lando.internal:8188`
- Local ComfyUI MCP URL: `http://host.lando.internal:9000/mcp`

If your MCP service runs on a different host/port/path, use that real value.

## 2. Save and open the managed Connection

Saving setup creates or updates the managed local ComfyUI Connection with both:

- `endpoint_url` (ComfyUI HTTP API)
- `mcp_endpoint_url` (separate MCP service)

## 3. Prepare provider templates

From the Connection configurator:

1. Click Sync Catalog.
2. Click Auto-Prepare Mappable Templates.

This performs a guided flow: sync, enable, then materialize every mappable
entry.

For this to be fully automatic, the MCP server should advertise:

- `list_templates`
- `get_template`
- `download_models`

## 4. Validate requirements

Open each generated Template and run Check ComfyUI requirements.

- If download URLs are available, use Download Requirements.
- If URLs are not advertised, install missing files manually in ComfyUI and
	re-check.

## 5. Headless workflow (optional)

The same flow is available through REST for headless UIs:

- `POST /wp-json/worldgraph/v1/connections/{id}/catalog/sync`
- `POST /wp-json/worldgraph/v1/connections/{id}/catalog/prepare`
- `POST /wp-json/worldgraph/v1/connections/{id}/catalog/entries/{entry_id}/materialize`

## Node and npm note

Use container-managed Node/npm for project commands (Lando `cli` or `headless`
services). Avoid installing or changing host Node versions unless you are
intentionally running the headless app outside Lando.