# Text to Video with Local ComfyUI MCP

Use this sequence to make local text-to-video workflows, such as LTX or WAN,
available through a World Graph Studio ComfyUI Connection.

ComfyUI can feel noisy: the browser UI may show warnings from unrelated custom
nodes, missing optional packages, or example workflows you are not using. For
World Graph Studio, the important path is narrower:

- the **ComfyUI HTTP API** runs the workflow;
- the separate **ComfyUI MCP service** discovers workflow JSON files and can ask
	ComfyUI to run them; and
- World Graph Studio turns discovered workflow JSON into generation Templates.

MCP discovery does not currently invent a workflow from installed nodes, crawl
every ComfyUI screen, or save a new workflow into ComfyUI for you. A workflow
must already exist as a `.json` file in the MCP service's template folder.
After that, discovery and Template creation can be automatic.

## What can be automatic

Once a workflow JSON file is visible to the MCP service, World Graph Studio can
sync the catalog, enable mappable entries, materialize Templates, check local
requirements, and ask MCP to download advertised model URLs.

What is not automatic in the current local helper is the first workflow authoring
step. ComfyUI's documented API path submits an existing API-format workflow to
`POST /prompt`; it does not provide a one-click World Graph Studio flow that
chooses a WAN graph, saves it into the MCP template folder, and installs every
custom node. ComfyUI also documents read/update server routes such as
`/workflow_templates` and `/userdata`, but the bundled local MCP helper currently
uses a simpler file-backed catalog.

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

## 3. Add workflow JSON files to the MCP template folder

The local MCP server discovers templates by reading JSON files from its template
folder. In the development setup used by this repository, that folder is:

`/home/videohead/www/ComfyUI/user/default/workflows`

Each workflow should be saved as one `.json` file in that folder, for example:

- `ltx-text-to-video.json`
- `wan-2-2-text-to-video.json`

ComfyUI's own API documentation recommends API-format workflows for
programmatic execution. Use API format whenever possible.

| Format | How to create it | Best use |
| --- | --- | --- |
| API workflow JSON | `File -> Export Workflow (API)` | Recommended for World Graph Studio generation. This is the format ComfyUI's `/prompt` API accepts. |
| UI workflow JSON | `File -> Save` or `Ctrl+S` | Useful for reopening and editing in ComfyUI. Convert/export it to API format before relying on it for generation. |

You can recognize the formats quickly:

- API workflow JSON has top-level numeric node IDs and `class_type` values.
- UI workflow JSON has a top-level `nodes` array and layout information.

The MCP server can list both formats. When a workflow is materialized into a
World Graph Studio Template, the requirement checker reads the workflow's node
classes and referenced model files. For actual local execution, prefer API
format so the workflow can be submitted directly to ComfyUI.

### Easiest path: use an existing workflow JSON

If someone gives you a known-good WAN or LTX workflow JSON file:

1. Copy the file into the MCP template folder.
2. Use a short lowercase filename with dashes, such as
	`wan-2-2-text-to-video.json`.
3. Restart the MCP service if it does not pick up new files immediately.
4. Return to WordPress and sync the catalog.

You do not need to open the ComfyUI browser interface for this path.

### If you only have a workflow open in ComfyUI

Use the ComfyUI UI only to save the workflow JSON, then return to WordPress:

1. Open the workflow in ComfyUI.
2. Ignore unrelated warning panels unless the workflow itself will not load.
3. Choose **File -> Export Workflow (API)**.
4. Place the exported `.json` file in the MCP template folder.
5. Use a filename that describes the workflow, such as
	`wan-2-2-image-to-video.json`.

If you accidentally use **File -> Save** instead, ComfyUI creates UI-format
JSON. Reopen that file in ComfyUI and export it again with **File -> Export
Workflow (API)**.

Common ComfyUI UI warnings are not automatically Word Graph Studio blockers.
The authoritative check happens later in the Template editor with **Check
ComfyUI requirements**.

## 4. Prepare provider templates

From the Connection configurator:

1. Click Sync Catalog.
2. Click Auto-Prepare Mappable Templates.

This performs a guided flow: sync, enable, then materialize every mappable
entry.

For this to be fully automatic, the MCP server should advertise:

- `list_templates`
- `get_template`
- `download_models`

If the newly added workflow does not appear, check that:

- the file is directly inside the MCP template folder, not in a nested folder;
- the filename ends in `.json`;
- the JSON is valid;
- the MCP service is using the same template folder you edited; and
- the MCP service was restarted if it caches its file list.

## 5. Validate requirements

Open each generated Template and run Check ComfyUI requirements.

- If download URLs are available, use Download Requirements.
- If URLs are not advertised, install missing files manually in ComfyUI and
	re-check.

For WAN workflows, missing files often belong under `models/diffusion_models`,
`models/text_encoders`, `models/vae`, or `models/loras`, depending on the nodes
the workflow uses. Follow the missing model report instead of guessing from the
workflow name.

If the requirement check reports missing nodes, install the matching custom node
package in ComfyUI, restart ComfyUI, and check again. World Graph Studio does
not install custom nodes automatically.

## 6. Headless workflow (optional)

The same flow is available through REST for headless UIs:

- `POST /wp-json/worldgraph/v1/connections/{id}/catalog/sync`
- `POST /wp-json/worldgraph/v1/connections/{id}/catalog/prepare`
- `POST /wp-json/worldgraph/v1/connections/{id}/catalog/entries/{entry_id}/materialize`

The REST flow still depends on the workflow JSON already being visible to the
MCP service. It automates WordPress catalog sync and Template creation; it does
not upload a new ComfyUI workflow JSON into the MCP template folder.

## ComfyUI documentation reference

ComfyUI documents the same boundary in its API workflow guide: use
**File -> Export Workflow (API)** when a workflow will be submitted through an
API. Its server API examples then load that API-format JSON and send it to
`POST /prompt` as the `prompt` payload.

- `https://docs.comfy.org/development/api-development/workflow-api-format`
- `https://docs.comfy.org/development/comfyui-server/api-examples`
- `https://docs.comfy.org/development/comfyui-server/comms_routes`

## Node and npm note

Use container-managed Node/npm for project commands (Lando `cli` or `headless`
services). Avoid installing or changing host Node versions unless you are
intentionally running the headless app outside Lando.