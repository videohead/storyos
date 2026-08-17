# StoryOS Deployment and Connections

StoryOS keeps stories, Story Graph data, and helpful filmmaking agents in WordPress. Generative media workflows run through ComfyUI and MCP. Neither a local GPU nor ComfyUI is required to use StoryOS for writing, planning, continuity, collaboration, or asset tracking.

## Before You Start

Every StoryOS user needs:

1. A WordPress.org-capable host, or a local Docker/Lando deployment.
2. A local ComfyUI installation operated through an MCP client, Comfy Cloud account with an API key, or no ComfyUI connection while using StoryOS for story-only work (no visual assets can be generated in this mode).
3. An API-connected LLM: a local OpenAI-compatible server such as llama.cpp, Ollama, vLLM, or LM Studio; or a hosted provider API such as OpenAI or Anthropic.

Browser-only subscriptions, including ChatGPT, Claude, and Claude Code subscriptions without an API credential, are not supported by the StoryOS server integration at this time. Hosted LLM providers require an API key; a local LLM must expose an OpenAI-compatible API endpoint and any credential it requires.

## Core Runtime

The standard deployment contains WordPress, MariaDB, and the StoryOS plugin. WordPress stores generation jobs and uses WP-Cron to process bounded batches.
The SCF plugin is also required in order to extend StoryOS capabilities

For reliable production scheduling, invoke `wp-cron.php` from the host scheduler. Local Lando users can run due events with `lando wp-cron`.

## Comfy Cloud MCP

Comfy Cloud is the supported WordPress generation connection. Create a Comfy Cloud API key, set `STORYOS_COMFY_API_KEY` in the deployment environment, and restart the appserver. StoryOS calls `https://cloud.comfy.org/mcp` with the key and submits or polls work from WP-Cron.

The key can also be entered in the StoryOS settings, but an environment variable is preferred for deployed sites. Do not commit credentials to `.env` or source control.

## Local ComfyUI and MCP

The first-party local `comfy-mcp` server is a Python stdio MCP server launched by an MCP-compatible client. It is not an HTTP service that PHP can call directly.

To use a local ComfyUI installation, connect an MCP-capable desktop or coding agent to both the local `comfy-mcp` server and the StoryOS WordPress MCP/Abilities surface. The agent can then read StoryOS context, operate local ComfyUI workflows, and return generated assets to StoryOS through its normal media workflow. This is an optional development and creator workflow; it is not required by the WordPress deployment.

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