# Web GenAI Platform Support

This page describes what a web-based generative AI platform can do with StoryOS today. StoryOS is the editorial and asset-management layer; it is not a compatibility layer for every provider API.

## Current Support

| Platform or route | StoryOS status | What users can do now |
| --- | --- | --- |
| Comfy Cloud MCP | Supported | Configure `STORYOS_COMFY_API_KEY`, submit image/video/audio/3D workflows, and let WordPress queue and poll jobs with WP-Cron. |
| Local ComfyUI through an MCP-capable client | Supported workflow | Connect the client to both StoryOS and the local `comfy-mcp` server. Run local workflows and register or upload the resulting assets in StoryOS. |
| fal MCP | Supported | Configure a fal API key, discover model schemas, provision StoryOS Templates, submit image/video jobs, poll them through WP-Cron, and import the returned media into WordPress. |
| ElevenLabs Generative Audio API | Supported | Configure an ElevenLabs API key, provision Templates for speech, dialogue, sound effects, music, or voice design, and import the generated audio into WordPress. |
| OpenAI, Anthropic, or OpenAI-compatible LLM API | Supported for AI Editor | Configure an API credential and compatible base URL in **StoryOS > AI Settings**. A browser subscription alone is not sufficient. |
| Other web image/video platforms | External asset source | Generate in the provider's own web app, then upload or register the result in StoryOS with its prompt, model, source URL, and usage-rights information. |

## Platforms That Are Not Automatic Connectors Yet

The following platforms are not currently supported as direct StoryOS generation connections:

- OpenAI Sora
- Runway
- Google Veo
- Kling
- Seedance
- Adobe Firefly
- Midjourney
- Amazon Bedrock or SageMaker video endpoints

Some of these services may be reachable through their own APIs, a third-party gateway, or a ComfyUI custom node. That does not make the route a StoryOS-supported connector. A supported connector must submit work, handle its lifecycle, retrieve downloadable artifacts, and preserve the asset metadata in WordPress.

The presence of `veo` or `nova_reel` in the connection form is an extension point and planning surface, not evidence that those providers execute successfully today.

## Recommended User Paths

### Path A: Managed generation in StoryOS

1. Create a Comfy Cloud account and API key.
2. Set `STORYOS_COMFY_API_KEY` in the deployment environment, or enter the key in StoryOS settings for local evaluation.
3. Configure a reliable host scheduler for `wp-cron.php`; local Lando users can run `lando wp-cron`.
4. Enable the Generation Engine and submit a workflow from StoryOS.
5. WordPress stores the generation record and polls Comfy Cloud through WP-Cron.

### Path B: Hosted generation through a supported provider

1. Create a fal or ElevenLabs account and API key.
2. In the Setup Wizard or **StoryOS > Connections**, choose the matching provider.
3. Use `env://FAL_KEY` or `env://ELEVENLABS_API_KEY` in production when the key is supplied by the runtime.
4. Test the Connection so StoryOS can discover provider capabilities and create or update Templates.
5. Select a provider Template and submit the generation from StoryOS.

fal jobs are queued and polled through WP-Cron. ElevenLabs audio responses are imported synchronously, including every preview returned by Voice Design.

These are first-party StoryOS execution paths. Provider model availability, quotas, pricing, regions, and terms remain controlled by fal and ElevenLabs.

### Path C: Local or third-party web generation

1. Create the image or video in the provider's web application or local ComfyUI setup.
2. Download the final artifact and retain the provider job URL or project reference.
3. Add the artifact to the WordPress media library or the relevant StoryOS asset.
4. Record the provider, model, prompt, source URL, generation date, and usage rights.

StoryOS can manage the story context, asset relationship, provenance, and downstream editorial work even when it did not submit the generation request.

## What Requires Future Work

To make a web platform a direct connector, StoryOS needs a provider-specific implementation for authentication, capability validation, submission, polling or webhooks, cancellation where available, artifact download, and asset ingestion. The current WordPress generation batch is intentionally limited to Comfy Cloud MCP, so adding a provider name or endpoint to a connection record alone is not enough.

## To Do: API Discovery

- [ ] Confirm official API availability, authentication, regional access, and commercial terms for Sora, Runway, Veo, Kling, Seedance, Firefly, Midjourney, and Amazon video services.
- [ ] Record provider-specific generation, polling/webhook, cancellation, and artifact-download requirements.
- [ ] Verify whether each provider's API permits asset provenance, source URLs, prompts, model identifiers, and usage-rights metadata to be retained in StoryOS.
- [ ] Prototype one connector end to end, including capability discovery, credential references, retries, downloadable artifacts, and WordPress media ingestion, before labeling the provider supported.

See [Deployment and Connections](Deployment_and_Connections.md) for credentials and runtime setup. Keep provider availability, pricing, regions, model limits, and terms of use with the provider's official documentation.