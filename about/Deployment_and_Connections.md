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

For reliable production scheduling, invoke `wp-cron.php` from the host scheduler. Local Lando users can run due events with `lando wp-cron`.

## ComfyUI MCP

Local ComfyUI or Comfy Cloud both have ahelpful MCP for dicovering and using Templates.

## Local ComfyUI HTTP API

StoryOS can reach a local ComfyUI server through its HTTP API. In the Setup
wizard, choose **Local ComfyUI HTTP API**, set the endpoint that is reachable
from WordPress, and use **Test ComfyUI** to check `/system_stats`. In a Lando
development environment where ComfyUI runs on the host, use
`http://host.docker.internal:8188`; `localhost` refers to the WordPress
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