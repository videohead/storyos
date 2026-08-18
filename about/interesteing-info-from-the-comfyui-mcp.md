Automatically Discovering and Downloading Requirements for a ComfyUI Template via MCP
You can use Comfy MCP to programmatically find and download the model and file requirements for a ComfyUI template without manually checking each node.

1. Connect to Comfy MCP
First, set up your MCP connection:

Cloud MCP: Sign in to Comfy Cloud and connect via your AI agent (e.g., Claude Desktop, Cursor, Codex) docs.comfy.org.

Local MCP: Run the local MCP server (e.g., comfy-mcp or artokun/comfyui-mcp) on your machine Github+1.

2. Use the Template System to Discover Requirements
Comfy MCP’s template system supports three sources of workflows deepwiki.com:

Built-in Templates (SD1.5, SDXL, Flux)

Example Workflows (70+ from ComfyUI docs)

Custom Templates (user-saved in SQLite)

You can query the system to list templates and extract their required nodes and model types:

listTemplates({ modelType: "flux", taskType: "txt2img" })
This returns a ranked list of templates, each with:

model_type (e.g., flux)

task_type (e.g., txt2img)

workflow (JSON workflow graph)

required_nodes (node types needed)

parameters (default settings)

3. Extract Model Requirements
From the workflow JSON, look for model loader nodes and their models field. These contain the model file paths or URLs docs.comfy.org. For example:

"models": [
  "https://huggingface.co/stabilityai/stable-diffusion-2-1/discussion/1234567890123456789012345678901234567890/flux.ckpt"
]
You can also use the get_template tool to load a specific template and inspect its default_settings and required_nodes deepwiki.com.

4. Automate Downloads
Once you have the model links:

Comfy Cloud: Use the MCP’s download or upload tools to fetch models directly into your Comfy Cloud workspace docs.comfy.org.

Local MCP: Use comfy-cli or the MCP server’s API to download models to your local ComfyUI/models directory Github.

Agent-native: If your agent supports it, call the MCP’s download_models function with the list of URLs.

5. Validate Before Running
After downloading:

Check that each model exists in the correct subfolder (e.g., models/diffusion_models/, models/vae/, models/text_encoders/) docs.comfy.org.

If missing, the MCP can prompt or auto-download them on first use.

6. Example Workflow
Query listTemplates({ modelType: "flux", taskType: "txt2img" }) to find a target template.

Call get_template({ templateId: "...", parameters: { prompt: "..." } }) to get the workflow JSON.

Parse required_nodes and models fields.

Use MCP’s download tool to fetch all models.

Run the workflow once all files are present.

Tip: The template system is connection-independent, so you can run discovery and downloads without a running ComfyUI instance deepwiki.com.

By combining the template search and model extraction features of Comfy MCP with its download/upload tools, you can fully automate the process of finding and fetching all requirements for a ComfyUI template.