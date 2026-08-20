## User guide for World Graph Studio

This guide describes a recommended first-time onboarding flow for World Graph Studio. It is a concrete example of how a project can begin, not a requirement that every deployment uses the exact same sequence.

## Hosting and setup

WordPress hosting and installation are beyond the scope of this document. There are many useful setup references available, including:

https://wordpress.org/documentation/article/faq-installation/

World Graph Studio can be useful for story development, production planning, continuity, collaboration, and asset tracking even without a live generation service. A working LLM connection is only required for AI-assisted workflows, and a ComfyUI or Comfy Cloud connection is only required when generating visual assets.

## ComfyUI and generation

ComfyUI Cloud is a straightforward option for generation and is well suited to World Graph Studio workflows.

- Sign up here: https://cloud.comfy.org/cloud/signup
- Create an API key
- Enter the key and endpoint in the World Graph Studio setup flow when you are ready to generate visuals

A local ComfyUI setup accessed through an MCP-capable client is also supported as an optional creator workflow.

## World Graph Studio example workflow

The sample workflow below demonstrates the most common first-time path for evaluating World Graph Studio:

1. Install and launch World Graph Studio in WordPress
2. Add LLM credentials for AI advisor features
3. Optionally add ComfyUI or Comfy Cloud credentials for generation
4. Import the example story
5. World Graph Studio creates the following records from the sample content:
   - Project
   - World
   - Characters
   - Locations
   - Props
   - Scenes
   - Shots
   - Storyboard frames
   - Editorial assets
   - Sequence
6. AI assistants analyze the story and align it to the Story Graph
7. Review character arcs, dialogue, continuity, and style direction with production terminology
8. Generate visual assets through ComfyUI or Comfy Cloud
9. Import generated assets back into World Graph Studio
10. Render or assemble a final sequence based on the story structure

This workflow is intended to show how World Graph Studio preserves a single canonical story data model while supporting generation, review, and production planning.

## Advanced workflows

The additional chapters of this guide describe more advanced workflows, including:

- Sequencing assets in World Graph Studio and exporting an EDL
- Exporting the example story as a script

Advanced or future functionality includes:

- Importing scripts into a World Graph Studio builder workflow that creates structured JSON from written scripts
- Importing EDLs and existing media into World Graph Studio and linking them with scenes
- Building a character LoRA or other consistency model for character-driven generation

These are extensions of the core World Graph Studio workflow, not the minimum setup required to use the platform.


