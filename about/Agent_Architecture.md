# World Graph Studio Agent Architecture v1.1

> Your ideas. Your assets. No credits needed.

## Purpose

World Graph Studio uses WordPress Abilities API and plugin-owned filmmaking agents. See
[Deployment and Connections](Deployment_and_Connections.md) for the supported
runtime.

The agent system provides contextual chat and specialist advice throughout
story development, asset generation, production planning, and editorial work
while maintaining awareness of the Story Graph.

The current runtime stays inside WordPress. The editor calls nonce-protected
WordPress REST endpoints, WordPress assembles Story Graph context and the
selected agent prompt, and the configured LLM backend produces the response.
There is no separate agent execution service.

---

## Architectural Vision

World Graph Studio does not use AI as a replacement for creators. It uses AI as a
collaborative team of expert advisors. The creator remains the Executive
Producer.

Agents provide:

- Guidance
- Analysis
- Recommendations
- Context retrieval
- Production-aware conversation

Generation jobs and other state-changing workflows remain explicit World Graph Studio
operations. Agent responses are suggestions and do not modify WordPress
content or execute ComfyUI actions by themselves.

---

## Guiding Principles

### Human Directed

Humans make final decisions and explicitly initiate state-changing operations.

### Story Graph First

Agents receive relevant knowledge from the Story Graph.

### Specialized Expertise

Each agent has a focused production responsibility and role-specific prompt.

### WordPress Owned

Agent definitions, routing, permissions, Story Graph context, and LLM access
are owned by the World Graph Studio WordPress plugin.

### Model Agnostic

The same agent and context layer can use a local OpenAI-compatible model,
OpenAI, or Anthropic through the configured LLM client.

### Extensible

New advisors can be added as `.agent.md` profiles without creating another
runtime or transport.

---

## Current Runtime Architecture

```text
Creator
   |
   v
World Graph Studio Editor / AI Workflow Metabox
   |
   v
POST /worldgraph/v1/ai/chat
   |
   +----------------------+----------------------+
   |                      |                      |
   v                      v                      v
Agent Registry      Story Graph Context    Permission Checks
(.agent.md)         (current post)         (current user/post)
   |                      |                      |
   +----------------------+----------------------+
                          |
                          v
                 Configured LLM Client
              OpenAI-compatible / OpenAI /
                       Anthropic
                          |
                          v
                  Assistant Message
```

The classic-editor AI Workflow metabox is a vanilla JavaScript chat client.
The Gutenberg AI Editor uses the same REST contract. Both surfaces retrieve
the enabled agent list from `GET /worldgraph/v1/ai/agents` and send conversation
turns to `POST /worldgraph/v1/ai/chat`.

---

## Agent Registry and Routing

Agent profiles live in
`wordpress/wp-content/plugins/worldgraph/includes/agents/` as `.agent.md` files.
Each profile contains metadata and a role-specific system prompt.

`AI_MAF_Bridge` loads those files as a local WordPress registry. Its name is
historical and does not imply a separate MAF runtime. The REST API exposes
enabled profiles to the editor, where users may select one for a conversation.

When no agent is selected, `AI_Agent_Router` maps request keywords to a real
installed profile:

| Category | Primary agent | Example concerns |
| --- | --- | --- |
| Story | Screenwriter | Character, dialogue, plot, narrative |
| Prompt / visual | PrevisualizationArtist | Images, shots, lighting, composition |
| Production | Producer | Schedule, budget, crew, locations, logistics |
| Technical | DIT | Formats, imports, exports, technical specifications |
| Editorial | Editor | Continuity, pacing, review, structure |
| ComfyUI | ComfyTechnician | Workflows, checkpoints, samplers, nodes, models |

The Director is the default when no specialist keywords match. If a routed
profile is disabled, the controller falls back to an enabled profile.

---

## Advisor Areas

### Story and Creative Advisors

Assist with narrative development, including:

- Story and scene analysis
- Character and dialogue review
- Plot consistency and worldbuilding
- Story arc and conflict analysis
- Direction, writing, art, and performance considerations

### Prompt and Previsualization Advisors

Translate story context into generation-ready creative recommendations,
including:

- Character and environment prompts
- Storyboard and shot prompts
- Composition, lighting, camera, and style recommendations
- Previsualization planning

These advisors do not queue generations or change ComfyUI workflows.

### Production Advisors

Support planning and execution across department-specific roles, including:

- Shot lists and production breakdowns
- Scheduling, budgeting, and resource planning
- Camera, lighting, grip, sound, wardrobe, makeup, stunts, and transport
- Production coordination and set operations

### Editorial Advisors

Support post-production and story review, including:

- Scene sequencing
- EDL and timeline planning
- Continuity analysis
- Pacing, rhythm, and structural feedback

---

## Comfy Technician

`ComfyTechnician` is the ComfyUI-specific specialist for World Graph Studio operators. Its
profile is defined in
`wordpress/wp-content/plugins/worldgraph/includes/agents/comfy_technician.agent.md`.

It can advise on:

- Workflow, checkpoint, custom-node, and model-file problems
- World Graph Studio Connection and Template readiness
- Container networking and ComfyUI reachability
- Low-cost diagnostic runs before expensive generations
- GPU memory, resolution, frame count, model compatibility, and reproducibility

The router selects this profile automatically for explicit ComfyUI, workflow,
checkpoint, sampler, custom-node, model-file, or generation-server questions.
It is also available in the metabox agent selector.

The Comfy Technician is currently advisory. It does not inspect a live ComfyUI
instance, install custom nodes, download models, provision templates, or queue
generations. It must distinguish supplied facts from likely causes and must not
invent filenames, node classes, model URLs, or observed system state.

---

## REST Chat Contract

The primary conversational endpoint is:

```text
POST /worldgraph/v1/ai/chat
```

The request accepts:

- `prompt`: the current user message
- `post_id`: optional World Graph Studio entity used as Story Graph context
- `agent`: optional enabled agent profile name
- `action`: `chat`, `analyze`, `generate`, or `continuity`
- `messages`: optional prior `user` and `assistant` turns

The endpoint returns stable fields including `success`, `data`, `agent`,
`backend`, `action`, `post_id`, and a sanitized `error` when unsuccessful.

Analysis and continuity controls in the classic metabox use this same chat
endpoint with turn-specific instructions. The older `/ai/analyze`,
`/ai/generate`, and `/ai/continuity` routes remain available for compatible
callers.

---

## Memory and Context

### Session Memory

The classic metabox and Gutenberg client keep prior `user` and `assistant`
messages in browser state. They send bounded history with each request:

- At most 20 prior messages
- At most 10,000 characters per message
- At most 40,000 characters in aggregate

Clearing the metabox chat removes its local history. `/ai/chat` does not
persist transcripts.

### Project and Story Memory

Persistent knowledge remains in World Graph Studio entities and Story Graph relationships.
`AI_Context_Builder` assembles relevant context for the current post on each
request.

### Agent Knowledge

Role instructions come from plugin-owned `.agent.md` files. Relevant durable
skills may be added to the system prompt by `AI_Agent_Skills`.

### Context Flow

```text
User Request
      |
      v
REST Validation and Permission Check
      |
      v
Agent Selection or Keyword Routing
      |
      v
Agent System Prompt + Story Graph Context
      |
      v
Bounded Prior Chat Messages
      |
      v
Configured LLM Backend
      |
      v
Append Assistant Response to Browser Transcript
```

The server owns system prompts and Story Graph context. REST clients may send
only prior `user` and `assistant` messages; client-supplied `system` messages
are rejected. If a post ID is supplied, the current user must be allowed to
edit that post before it can be used as context.

The LLM response cache includes the prompt, model, system prompt, Story Graph
context, and prior messages so replies are not reused across different posts or
conversation states.

---

## Tooling Boundary

World Graph Studio exposes typed capabilities through the WordPress Abilities API and
provides REST controllers for Story Graph and generation operations. The chat
runtime does not currently translate an agent profile's `tools` field into LLM
function calls. Available application operations must not be confused with
operations an LLM is authorized to run.

Current application capabilities include:

- Querying and updating World Graph Studio custom post types
- Searching and traversing the Story Graph
- Importing and exporting scripts
- Creating EDL and timeline metadata
- Submitting generation jobs and importing resulting assets
- Connecting to configured ComfyUI services

These capabilities remain behind their existing permission-checked WordPress
surfaces and explicit user actions. Chat responses do not invoke them
automatically.

Any future agent tool execution must provide:

- Per-agent ability allow-lists
- Current-user permission checks at dispatch time
- Explicit confirmation for destructive or costly operations
- Bounded tool-call loops and response sizes
- An audit trail for calls, arguments, results, user, agent, and timestamp
- Safe handling of untrusted text returned by remote services

---

## Security Model

Agents operate with least-privilege access.

The browser clients authenticate cookie-based requests with a WordPress REST
nonce. The REST permission callback requires an authenticated user who can
manage options or edit posts. Post context also requires `edit_post` access to
the specific entity.

Agent names are checked against the enabled plugin-owned registry. The browser
cannot inject a replacement system prompt, and conversation history is bounded
before it reaches a provider. The vanilla JavaScript metabox renders messages
with DOM `textContent` rather than inserting executable HTML.

API keys and provider authorization remain server-side. They must not be sent
to the browser, stored in conversation history, or written to logs.

---

## Asset Generation Workflow

```text
Story Graph Element
  |
  v
Prompt / Previsualization Advice
  |
  v
Human Review and Explicit Generate Action
  |
  v
World Graph Studio Generation Service / ComfyUI
  |
  v
Generated Asset
  |
  v
Story Graph Update
```

Agent advice and media generation are intentionally separate authorization
steps.

---

## Script to Editorial Workflow

```text
Script
   |
   v
Scene Extraction
   |
   v
Shot Planning
   |
   v
Production Data
   |
   v
EDL Export
```

---

## Extending the Agent System

Add a new advisor only when its responsibility, audience, or safety boundary
is meaningfully distinct. A new profile should:

1. Use a stable `name` that can be selected through the REST API.
2. Describe a focused production role and its World Graph Studio knowledge.
3. State what it can infer, what requires context, and what it must not claim.
4. Remain advisory unless an audited tool broker explicitly grants abilities.
5. Be added to router keywords only when automatic routing is useful.
6. Include focused tests for routing and registry availability.

---

## Strategic Objective

The long-term goal is an intelligent advisor ecosystem built around the Story
Graph. As models evolve, the Story Graph remains the persistent knowledge layer
while agents remain interchangeable expert interfaces.

World Graph Studio therefore preserves production knowledge while remaining model-agnostic
and future-proof.
