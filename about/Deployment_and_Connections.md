# StoryOS Deployment and Connections

StoryOS keeps stories, Story Graph data, and helpful filmmaking agents in WordPress. Generative media workflows run through your favorite generative tools including ComfyUI. Neither a local GPU nor ComfyUI is required to use StoryOS for writing, planning, continuity, collaboration, or asset tracking, but the majority of the tools are organizaed around having both chat-based AI assistance AND generative AI.

## Before You Start

Every StoryOS user needs:

1. A WordPress.org-capable host, WP Local, or a local Docker/Lando deployment.
2. A local ComfyUI installation operated through an MCP client, Comfy Cloud account with an API key, additional API keys for your favorite generative tools, or no connection while using StoryOS for story-only work (no agentic assistance or visual assets can be generated in this mode).
3. An API-connected LLM: a local OpenAI-compatible server such as llama.cpp, Ollama, vLLM, or LM Studio; or a hosted provider API such as OpenAI or Anthropic.

Browser-only subscriptions, including ChatGPT, Claude, and Claude Code subscriptions without an API credential, are not supported by the StoryOS server integration at this time. Hosted LLM providers require an API key; a local LLM must expose an OpenAI-compatible API endpoint and any credential it requires.

## Core Runtime

The standard deployment contains WordPress, MariaDB, and the StoryOS plugin. WordPress stores generation jobs and uses WP-Cron to process bounded batches.
A node-driven CLI container is also included for testing and development.
The SCF plugin is also required in order to extend StoryOS capabilities.

## Connection Adapters

Provider implementations are registered in the StoryOS Connection adapter
manifest and loaded conditionally. An adapter loads when WordPress has a saved,
non-disabled Connection for its provider, or when an admin explicitly selects,
tests, or configures that provider. Merely installing StoryOS does not load all
provider API clients.

The Setup Wizard's **Preferred Connection** dropdown is generated from the same
manifest. It contains the small set of adapters that support guided setup;
additional provider types remain available on **StoryOS > Connections**. The
Plugins screen lists executable Connection adapters and their configured state,
but does not give them a second enable/disable control. Connection status is the
single source of truth for whether an adapter should load.

Third-party code can extend the manifest through
`storyos_connection_adapters`, provide a callable `loader` or plugin-relative
`files`, and declare guided setup choices with `setup_options`.

For reliable production scheduling, invoke `wp-cron.php` from the host scheduler. Local Lando users can run due events with `lando wp-cron`.

## ComfyUI MCP

Local ComfyUI or Comfy Cloud both have a helpful MCP for discovering and using Templates.

### Reaching a local ComfyUI from Lando

WordPress runs inside the `appserver` container, so `localhost` in the ComfyUI
URL fields refers to that container, not your development host. Use Lando's
built-in host hostname instead:

- **ComfyUI API URL:** `http://host.lando.internal:8188`
- **LLM base URL** (Ollama, llama.cpp, LM Studio, vLLM): `http://host.lando.internal:11434/v1`

`host.lando.internal` (Lando ≥ 3.22) resolves to the Lando host machine from
every service in every running app, on every platform, without extra
Docker network or `extra_hosts` configuration. Prefer it over
`host.docker.internal`, which is Linux/Docker-Engine-version-specific and
only works when the app's Landofile explicitly adds
`extra_hosts: ["host.docker.internal:host-gateway"]`.

If ComfyUI runs in its own Docker Compose project rather than directly on the
host, publish its ports to the host (e.g. `"8188:8188"`) so `host.lando.internal`
can reach it — Lando does not automatically join unrelated Compose projects'
networks.

### Automatic Template discovery and model downloads

Setting **Local ComfyUI MCP URL** enables automatic Template discovery and
model installation, but ComfyUI's own HTTP API (port 8188) does not speak MCP —
appending `/mcp` to it will not work. That URL must point at a separate
streamable-HTTP MCP server process advertising at least a `download_models`
tool (and ideally `list_templates` / `get_template` for full auto-discovery).
Leaving it empty falls back to the built-in `Generation_Modality` catalog and
manual model installs.

## fal MCP

StoryOS can use fal as a hosted generation Connection through fal's Streamable
HTTP MCP endpoint at `https://mcp.fal.ai/mcp`. Configure the Connection with:

- Provider Type: `fal`
- Endpoint URL and MCP Endpoint URL: `https://mcp.fal.ai/mcp`
- Credential: a fal API key, or an `env://FAL_KEY` reference when the key is
  supplied to the WordPress runtime
- Model: an optional default fal endpoint ID
- Model Access: an optional JSON allowlist of endpoint IDs
- Environment: `production`

fal authenticates every MCP request with `Authorization: Bearer <FAL_KEY>`.
Testing the Connection performs MCP initialization and verifies that the server
advertises `submit_job` and `check_job`.

Each fal model is represented by a StoryOS Template, but StoryOS normally
creates and updates these records automatically. Saving a fal Connection
schedules MCP catalog/schema discovery. Testing it performs the same sync
immediately. A Connection-level Model selects one endpoint; Model Access is an
authoritative JSON allowlist and provisions one Template per endpoint. With
neither configured, fal MCP supplies a current text-to-image model.

The generated Template keeps runtime inputs separate from the full provider
schema in Configuration JSON:

```json
{
  "input": {
    "image_size": "landscape_16_9",
    "num_images": 1
  }
}
```

StoryOS supplies `prompt` and resolved Template input bindings at runtime,
submits the work with `submit_job`, polls with `check_job`, and imports returned
image or video URLs into the WordPress media library. A generation job is not
marked complete unless every returned media URL has been downloaded and stored
as a WordPress attachment.

## ElevenLabs API

StoryOS supports ElevenLabs as a conditionally loaded generative-audio Connection.
The guided Setup Wizard choice requires only an ElevenLabs API key; it creates a
Connection using `https://api.elevenlabs.io/v1`. A production deployment may
instead use `env://ELEVENLABS_API_KEY` as the Connection credential reference.

ElevenLabs authenticates with the `xi-api-key` request header. Saving or testing
the Connection reads `/v1/models` and `/v2/voices`, prefers
`eleven_multilingual_v2` when no Model is configured, and provisions active
Templates for:

- Text to speech (`POST /v1/text-to-speech/{voice_id}`), one Template per
  selected voice
- Text to dialogue (`POST /v1/text-to-dialogue`)
- Sound effects (`POST /v1/sound-generation`)
- Music (`POST /v1/music`)
- Voice design (`POST /v1/text-to-voice/design`)

Each Template stores that method's request defaults and provider schema under
Configuration JSON. To constrain speech voices, set Model Access to a JSON array
of voice IDs. Transformation and analysis APIs that require multipart source
media or asynchronous project lifecycles—such as Voice Changer, Audio Isolation,
Dubbing, and Speech to Text—are not treated as prompt-generation Templates.

Returned audio is written into the WordPress media library and linked to the
source Asset before generation is marked complete. Voice Design returns several
previews, so every preview is imported. Raw audio bytes are never persisted in
generation post meta.

## Local ComfyUI HTTP API

StoryOS can reach a local ComfyUI server through its HTTP API. In the Setup
wizard, choose **Local ComfyUI HTTP API + MCP**, set the endpoint that is reachable
from WordPress, and use **Test ComfyUI** to check `/system_stats`. In a Lando
development environment where ComfyUI runs on the host, use
`http://host.lando.internal:8188`; `localhost` refers to the WordPress
container and will not reach the host service.


## LLM Connections

Configure the AI Editor in WordPress under **StoryOS > AI Settings**.

| Connection | Backend selection | Base URL | Credential |
| --- | --- | --- | --- |
| OpenAI | OpenAI API | Managed by StoryOS | OpenAI API key |
| Claude | Anthropic API | Managed by StoryOS | Anthropic API key |
| Ollama, vLLM, LM Studio | OpenAI-Compatible / Local LLM | The service's `/v1` endpoint | Optional or service-specific key |
| Hosted compatible API | OpenAI-Compatible / Local LLM | Provider's `/v1` endpoint | Provider API key |


## StoryOS Without ComfyUI

StoryOS remains fully useful without ComfyUI: creators can write, develop story worlds, run WordPress filmmaking agents, plan production, manage continuity, import/export scripts and EDL data, and register or upload assets from any external generator.

Web-based generation providers such as Veo can participate in the StoryOS framework as external asset sources. StoryOS should store their prompt, provider, model, source URL, usage rights, and generated media as asset provenance. A provider needs an explicit WordPress connector before StoryOS can submit jobs or poll it automatically; direct Veo, Nova, and similar connectors are roadmap work, not current built-in execution paths.
