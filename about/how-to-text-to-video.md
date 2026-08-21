To enable both WAN 2.2 and LTX 2.5 through local ComfyUI MCP, use this flow:

Set local generation mode in Setup Wizard
Go to WordPress Admin → World Graph Studio → Setup & Settings.
In Generation Connection, choose Local ComfyUI HTTP API + MCP.

Enter both local endpoints
Local ComfyUI API URL: http://host.lando.internal:8188
Local ComfyUI MCP URL: http://host.lando.internal:9000/mcp
Use your actual MCP port/path if different.

Save setup
This creates/updates the managed local ComfyUI connection with both endpoint_url and mcp_endpoint_url.

Sync provider catalog from the connection
Open the managed ComfyUI connection and run Sync Catalog.
For automatic template provisioning, your MCP must advertise:

list_templates
get_template
download_models
Enable and materialize both families
In the catalog UI, enable and materialize entries for:
WAN 2.2 workflows
LTX 2.5 workflows
This creates separate World Graph Studio templates linked to the same local connection.
Validate each template and install requirements
Open each materialized template and run the ComfyUI requirement check.
If models/nodes are missing, use Install missing models (if download_models is available) or install manually in ComfyUI, then re-check.