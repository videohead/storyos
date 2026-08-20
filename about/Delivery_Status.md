# World Graph Studio Delivery Status

**Current release status: complete.** The capabilities described as current in
this repository are implemented. Additional file-based script import/export
formats are on hold.

This document is the status source of truth. Feature specifications explain how
the delivered system works; they should not redefine a shipped feature as
pending merely because an optional provider, account, model, or deployment has
not been configured.

## Delivered

| Area | Current release |
| --- | --- |
| Story Graph | 15 content types, nine taxonomies, Structured Content Fields, canonical relationships, graph traversal, and REST exposure |
| Creative workspace | Project and world management, scenes, shots, sounds, storyboards, assets, editorial records, templates, and provider connections |
| AI assistance | Gutenberg AI Editor, Story Graph context, configured LLM access, WordPress Abilities, and 50+ specialist creative advisors |
| Story intelligence | Search, optional semantic assistance, continuity checks, relationship analytics, summaries, and admin panels |
| Generation | Connection and template records, validation, queued generation jobs, WP-Cron processing, job state, cancellation, result import, and provenance |
| Provider adapters | local ComfyUI HTTP workflows, Comfy Cloud MCP, fal MCP, ElevenLabs, and manually managed external-generator workflows where configured |
| Project interchange | World Graph Studio JSON import and Markdown screenplay/storyboard export |
| Script synchronization | Optional outbound Celtx synchronization for supported entities, with persistent remote-ID mapping |
| Editorial utilities | Optional CMX 3600 and SMPTE 436m XML parsers, preview data, timecode helpers, and format generators |
| Administration | Setup wizard, connection management, plugin toggles, dashboards, and permission-aware REST/admin actions |

“Delivered” describes code in the repository. Optional connections still need
valid credentials, a reachable service, and models or workflows compatible
with that service. Provider-specific availability is an operating condition,
not unfinished World Graph Studio implementation.

## On hold: additional script import/export

The following additions are not part of the current release and are not active
delivery commitments:

- Final Draft FDX import.
- Fade In import.
- Highland import.
- Story Architect import.
- Automated screenplay parsing and Story Graph entity extraction for those
  formats.
- Import preview, deduplication, and merge workflows specific to those formats.
- Professional script exporters beyond the delivered Markdown screenplay and
  storyboard exports.
- Additional script synchronization providers beyond Celtx.

Existing JSON import, Markdown export, and outbound Celtx synchronization are
delivered and remain supported. “Script import/export is on hold” refers only
to the additional formats above.

## Current boundaries

- World Graph Studio does not require an AI or generation connection for core
  Story Graph work.
- Built-in automation depends on the modalities and adapters exposed by a
  configured connection. World Graph Studio can store broader media types even
  when it does not provide a direct connector for the service that created
  them.
- Hosted services can impose their own prices, quotas, licenses, moderation,
  and availability. World Graph Studio itself does not sell usage credits.
- Self-hosting gives the operator control of deployment and data location; site
  visibility and access still depend on WordPress and hosting configuration.
- AAF, OMF, provider-specific NLE panels, and other possible integrations are
  extension points, not current-release commitments.
- The bundled EDL utility can parse, preview, and generate supported formats.
  Import confirmation does not yet persist a project timeline, and live
  project export still needs a production timeline-data adapter; neither is
  presented as delivered interchange.
- The bundled Google Web Stories directory is prototype extension source. It
  is not loaded by the current plugin and is not a supported release workflow.

## Naming contract

- Product name: **World Graph Studio**
- Machine namespace and text domain: `worldgraph`
- PHP namespace: `WorldGraph`
- Constants and environment-variable prefix: `WORLDGRAPH_`
- REST namespace: `worldgraph/v1`

Legacy `storyos` identifiers may remain inside the one-time compatibility
migration and its tests. They are migration inputs, not current public names.
