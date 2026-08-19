# Comfy MCP Template Discovery — Research Notes

> These are the original exploratory notes. The actionable process derived from
> them lives in `about/plugins/COMFY_TEMPLATE_CATALOG.md`. Read that first; this
> file is kept for provenance.

## Source observations

Comfy MCP's template system draws workflows from three sources:

- Built-in templates (SD1.5, SDXL, Flux)
- Example workflows (70+ from the ComfyUI docs)
- Custom templates (user-saved)

### Discovery

`list_templates({ modelType, taskType })` returns a ranked list, each entry
carrying `model_type`, `task_type`, `workflow` (JSON graph), `required_nodes`,
and `parameters`. `get_template({ templateId, parameters })` loads one template
including its default settings and required nodes.

### Model requirements

Model loader nodes in the workflow JSON carry the model file names, and the
template descriptor may carry download URLs.

### Downloads

- Comfy Cloud: the MCP `download_models` tool fetches models into the cloud
  workspace.
- Local: `comfy-cli`, or a local MCP server exposing the same tool.

### Validation

Confirm each file landed in the correct subfolder — `models/diffusion_models/`,
`models/vae/`, `models/text_encoders/`, and so on.

### Caveat

The template system is described as connection-independent, so discovery can run
without a live ComfyUI instance. This claim is untested against a local MCP
server and is tracked as an open question in the specification.
