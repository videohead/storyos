# Comfy Template Catalog: Discovery and Provisioning

> Status: specification. Supersedes the exploratory notes in
> `about/interesting-info-from-the-comfyui-mcp.md`.
>
> Companion: `about/plugins/GENERATE_PREFERENCES.md` covers how a provisioned
> Template reaches an author through the Generate metabox. This document ends
> at a runnable Template; that one begins there.
>
> Companion: `about/plugins/COMFY_AND_PROMPT_AGENTS.md` covers the Comfy
> Technician agent that explains catalog and provisioning state in natural
> language.

This document defines the actionable process by which StoryOS:

1. **Discovers** the ComfyUI workflow templates available on a given Connection,
   whether that Connection is Comfy Cloud MCP or a local ComfyUI.
2. **Curates** that catalog — the operator enables the small number of templates
   StoryOS should actually offer.
3. **Provisions** each enabled template — forcing download/install of the models
   (and reporting the custom nodes) it requires on that Connection.
4. **Validates** that provisioning succeeded before the template is usable.

WordPress is the control plane. ComfyUI MCP is the authority on what ComfyUI can
do; StoryOS mirrors it, never invents it.

---

## 1. Vocabulary

| Term | Meaning |
| --- | --- |
| **Connection** | `storyos_connection` post. One endpoint + credential reference. `provider_type = comfyui`. |
| **Catalog entry** | One template *as advertised by the Connection's provider*. Not a StoryOS Template. Ephemeral, refreshable, cached. |
| **Enabled entry** | A catalog entry the operator has switched on for that Connection. |
| **Template** | `storyos_template` post. A StoryOS-owned, curated, bound, runnable configuration. Materialized from an enabled catalog entry. |
| **Manifest** | The nodes + models + download URLs a Template needs, per `Comfy_Manifest`. |

The key modeling decision: **catalog entries are not Templates.** A provider may
advertise 70+ example workflows; StoryOS must not create 70 posts. Discovery
produces a cached catalog; curation is a per-Connection allow-list; only enabled
entries are materialized into `storyos_template` posts.

---

## 2. Connection Capability Tiers

Discovery and download capability differ per Connection. Probe and record the
tier; never assume it.

| Tier | Detection | Discovery | Download |
| --- | --- | --- | --- |
| **A — MCP template system** | `Comfy_Cloud_MCP::available_tools()` includes `list_templates`, `get_template`, `download_models` | `list_templates` / `get_template` | `download_models` |
| **B — MCP, partial** | MCP reachable, subset of the above tools | whatever is advertised | may be unavailable |
| **C — HTTP only** | `mcp_endpoint_url` empty; `GET /system_stats` succeeds | built-in `Generation_Modality` catalog + `/object_info` introspection | not automatic — manual install plan |

Comfy Cloud is normally Tier A. A local ComfyUI with a co-located MCP server
(`mcp_endpoint_url` set) can be Tier A or B. A bare local ComfyUI at
`http://localhost:8188` is Tier C.

### 2.1 Required code changes

`Comfy_Cloud_MCP::supports_tool()` currently calls `available_tools()` with no
`$connection_id`, so it probes the default cloud endpoint regardless of which
Connection is being asked about. Likewise `Comfy_Manifest::request_downloads()`
calls `Comfy_Cloud_MCP::download_models( $urls )` with no `$connection_id`,
which will send a local Template's downloads to the cloud endpoint.

- `supports_tool( string $name, int $connection_id = 0 ): bool` — thread the
  Connection through.
- `Comfy_Manifest::request_downloads( int $template_id )` must read the
  Template's `connection_id` meta and pass it to `download_models()`.
- Add `Comfy_Cloud_MCP::capability_tier( int $connection_id ): string` returning
  `a` | `b` | `c` | `unreachable`, cached alongside `TOOLS_TRANSIENT`.
- Persist the resolved tier and tool list onto the Connection as
  `last_health_report['comfy']` during `Connection_Tester::test()`, so admin UI
  can render the right affordances without a live probe.

---

## 3. Stage 1 — Discovery

**Trigger:** explicit operator action ("Sync template catalog" on the Connection
row / Connection edit screen) and after a successful `Connection_Tester::test()`
on a `comfyui` Connection. Never on page load; never on `init`.

### 3.1 Tier A/B: MCP discovery

```
list_templates( filters, connection_id )
```

Call once per `Generation_Modality::all()` `task_type` (`txt2img`, `img2img`,
`txt2video`, `img2video`, `video2video`, …), plus one unfiltered call, and merge
by template id. Filtering by task type is what lets StoryOS map a provider
template onto a StoryOS modality without guessing.

For each returned entry, normalize to the **catalog entry schema**:

```json
{
  "id": "flux_txt2img_basic",
  "name": "Flux — text to image",
  "source": "mcp",
  "model_type": "flux",
  "task_type": "txt2img",
  "modality": "text-to-image",
  "required_nodes": ["CheckpointLoaderSimple", "KSampler", "..."],
  "models": [{"filename": "flux1-dev.safetensors", "folder": "diffusion_models"}],
  "model_urls": ["https://huggingface.co/.../flux1-dev.safetensors"],
  "parameters": {"width": 1024, "height": 1024, "steps": 20},
  "model_family": "wan",
  "workflow_hash": "sha1:…"
}
```

- `modality` is derived by mapping `task_type` back through
  `Generation_Modality::all()`; entries whose `task_type` maps to nothing are
  kept with `modality: null` and marked `unmappable` (visible but not enablable
  until an operator picks a modality).
- `model_family` is inferred from `required_nodes` via
  `Model_Family::all()` node prefixes, **longest prefix first** (`WanSCAIL`
  before `Wan`).
- `required_nodes` and `models` come from `list_templates` when present, else
  from `Comfy_Manifest::extract_nodes()` / `extract_models()` on the returned
  workflow graph.
- Do **not** store the full workflow JSON in the catalog. Store
  `workflow_hash` and re-fetch the graph via `get_template()` at materialization
  time. Catalogs must stay small.

`Comfy_Manifest::discover_provider_templates()` currently reduces each entry to
`{id, name}` — it must be widened to emit the schema above. `discover()`
already produces most of it and should be refactored to share one normalizer.

### 3.2 Tier C: local ComfyUI without MCP

There is no provider template list. Synthesize the catalog from what StoryOS
already knows plus what the instance reports:

1. Seed one catalog entry per `Generation_Modality::all()` slug, using
   `default_workflow()` / `default_settings()`, with `source: "builtin"`.
2. Fetch `Comfy_Manifest::catalog( $endpoint )` (`/object_info`, 5-minute
   transient) and mark each entry `installable: true|false` by testing whether
   every node in `required_nodes` is a key in the object-info catalog.
3. For each model-loader input, use `Comfy_Manifest::installed_options()` to
   list the checkpoints actually present, and attach them as
   `available_checkpoints` on the entry.

This gives the local operator a real, honest list: "these modalities your
ComfyUI can run today, these need nodes/models."

### 3.3 Persistence

Store the catalog **per Connection**, not globally:

- Snapshot: post meta `comfy_template_catalog` on the `storyos_connection` post
  (JSON: `{ synced_at, tier, source, entries: [...] }`).
- Post meta, not a transient — the operator's enable decisions reference it, so
  it must not silently evaporate. Staleness is communicated by `synced_at`,
  refreshed by re-running Stage 1.
- Re-sync is a full replace, but enable flags (Stage 2) live in a separate meta
  key and survive re-sync. Entries that disappear from the provider are retained
  in the enable list and rendered as `withdrawn` so the operator sees why a
  Template stopped working.

---

## 4. Stage 2 — Curation (the enable/disable step)

**Decision: opt-in allow-list per Connection, defaulting to none enabled.**

Rationale for this over the alternatives:

- *Auto-create a Template for every discovered entry* — rejected. Produces
  dozens of unusable posts, each needing a checkpoint, bindings, and a model
  download; pollutes the Template CPT which is a curated authoring surface.
- *Auto-enable everything whose requirements are already satisfied* — rejected
  as the default because it makes the offered set depend on install order, which
  is not reproducible. It is offered as a **bulk action** ("Enable all
  ready-to-run"), not as a default.
- *Enable-by-modality (turn on a task type, StoryOS picks the best template)* —
  rejected as the primary model because it hides which graph runs. It is
  reintroduced as a **preferred-template** setting per modality in Stage 5.
- **Chosen: explicit per-entry toggle, opt-in, with bulk helpers.** Matches the
  existing `enabled_structures` / `model_access` allow-list pattern already on
  the Connection CPT, and matches the fail-closed posture appropriate to an
  action that downloads multi-gigabyte files.

### 4.1 Storage

New Connection post meta, mirroring `enabled_structures`:

```
enabled_templates = [
  { "id": "flux_txt2img_basic", "modality": "text-to-image", "enabled_at": "…", "template_id": 412 },
  …
]
```

Empty array = nothing enabled. Unlike `enabled_structures`, this is
**fail-closed**: an empty `enabled_templates` means the Connection offers only
its explicitly configured Templates (including the `Comfy_Bootstrap` default),
not "everything".

Expose it through `Connection_Repository::get()` / `resolve()` alongside the
other public fields.

### 4.2 UI

A "Template Catalog" panel on the Connection edit screen and on
StoryOS → Connections:

- Header: tier badge, `synced_at`, **Sync catalog** button.
- Filter bar: modality, model family, and a `ready | needs models | needs nodes |
  unmappable | withdrawn` status filter.
- One row per entry: name, modality, model family, requirement status chip,
  enable checkbox.
- Row expands to show `required_nodes`, `models`, and `model_urls`.
- Bulk actions: *Enable selected*, *Disable selected*, *Enable all ready-to-run*.
- Enabling is cheap and reversible; it does **not** download anything. It queues
  the entry for Stage 3.

Requirement status per row is computed from the cached `/object_info` catalog
(Tier A local / Tier C) or, on Comfy Cloud where the local filesystem is not
introspectable, from the entry's own `models` list versus what previous
`download_models` calls recorded — see §5.4.

---

## 5. Stage 3 — Provisioning (forcing downloads/setup)

**Trigger:** explicit "Provision" on an enabled entry, or "Provision all
enabled" on the Connection. Downloads are never implicit.

Provisioning runs as a **job**, not a request. Model files are large; a
synchronous admin-ajax call will time out.

### 5.1 Job model

Reuse the existing generation job/logging surface rather than inventing a second
one:

- Record: a `storyos_generation`-style provisioning record, or a dedicated
  `comfy_provisioning` post meta queue on the Connection holding
  `{ entry_id, state, requested_urls, started_at, finished_at, report }`.
- States: `queued` → `running` → `satisfied` | `partial` | `failed` |
  `manual_required`.
- Executed by WP-Cron, one entry at a time per Connection, so a provisioning run
  cannot saturate the endpoint.
- Every transition writes to `Generation_Log` with the `comfy_provisioning`
  source and the `connection_id`, so the existing log UI covers it.

### 5.2 Tier A — MCP `download_models`

```
get_template( entry.id, {}, connection_id )      → authoritative workflow + urls
extract_model_urls( template )                    → download URL list
download_models( urls, connection_id )            → provider fetches into workspace
```

Rules:

- Always re-fetch via `get_template()` at provision time. The catalog snapshot
  may be stale, and the workflow graph is the authority on what is needed.
- Deduplicate URLs across entries on the same Connection before dispatch — two
  enabled Flux templates must not pull the same 12 GB file twice.
- Chunk the URL list; dispatch and record per-URL outcomes so a single bad URL
  does not fail the whole entry.
- If `download_models` is not advertised (Tier B), fall through to §5.4.

### 5.3 Tier C — local ComfyUI without MCP

No remote download tool exists. StoryOS must not shell out or write to the
ComfyUI filesystem — that is outside the WordPress boundary and would break the
container contract. Instead, produce a **manual install plan** and, where a
manager API is present, delegate.

Order of preference:

1. **Local MCP** — if the operator sets `mcp_endpoint_url` on the Connection,
   the Connection is re-tiered to A/B and §5.2 applies. The setup wizard should
   actively prompt for this: it is the single highest-value field for local
   users, because it is the only thing that makes local downloads automatic.
2. **ComfyUI-Manager** — if `/object_info` reveals the manager's endpoints,
   offer to POST model/node install requests to it. Probe once, record on the
   Connection health report, degrade silently if absent.
3. **Manual plan** — emit a copy-pasteable plan and mark the entry
   `manual_required`:

   ```
   models/diffusion_models/flux1-dev.safetensors
     https://huggingface.co/…/flux1-dev.safetensors
   Custom nodes not installed: ComfyUI-VideoHelperSuite
   ```

   Folder targets come from `Comfy_Manifest::MODEL_FIELDS`; family-level
   fallback comes from `Model_Family::all()['checkpoint_folder']`.

Custom **nodes** are never auto-installed on any tier. StoryOS reports missing
node classes; installing arbitrary code into ComfyUI is an operator decision.

### 5.4 Downloads with no URL

If a required model has a filename but no source URL, the entry cannot be
auto-provisioned. Do not guess a Hugging Face URL from the filename. Mark
`manual_required` and surface the existing guidance already implemented in
`Comfy_Manifest::request_downloads()`: add
`{"filename":"…","folder":"…","url":"…"}` to the Template's Model Requirements
JSON, or install the file into ComfyUI directly.

---

## 6. Stage 4 — Validation

After provisioning, and on demand:

- **Tier A local / Tier C:** `Comfy_Manifest::flush_catalog()`, then
  `Comfy_Manifest::validate( $template_id, $endpoint )`. `missing_nodes` empty
  and `missing_models` empty ⇒ `satisfied`.
- **Comfy Cloud:** the models directory is not introspectable over
  `/object_info`. Validate by the `download_models` result payload plus a
  cheap `get_template()` round trip; if neither is conclusive, mark
  `assumed_satisfied` and let the first real job be the proof. Record the
  distinction — never report `satisfied` on evidence that does not exist.

An entry only leaves the provisioning queue on `satisfied`,
`assumed_satisfied`, `manual_required`, or `failed`.

---

## 7. Stage 5 — Materialization into Templates

Only after an entry is enabled do we create a `storyos_template` post. Do it at
**enable** time (so the operator can bind inputs while models download), and
record the resulting post ID back onto the `enabled_templates` entry.

Field mapping from catalog entry → Template meta:

| Template meta | Source |
| --- | --- |
| `template_name` | entry `name` |
| `modality` | mapped from `task_type` |
| `connection_id` | the Connection being curated |
| `provider_type` | `comfyui` |
| `provider_template_id` | entry `id` |
| `model_family` | inferred via `Model_Family` |
| `checkpoint` | entry `models` primary loader file |
| `workflow_json` | from `get_template()` at materialization; Tier C uses `Generation_Modality::default_workflow()` |
| `model_requirements` | entry `models` joined with `model_urls` |
| `default_values` | entry `parameters` merged over `Generation_Modality::default_settings()` |
| `input_bindings` | left empty — operator binds via `Template_Bindings` |
| `generation_structure` | intent slugs, per `about/plugins/GENERATE_PREFERENCES.md` §7; left empty means "serves any intent matching this modality" |
| `status` | `draft` until Stage 4 reports satisfied, then `active` |

The `status = draft` gate is what prevents a half-provisioned Template from
being selected by `Generation_Controller::resolve_active_template()`. Promotion
to `active` is automatic on `satisfied`, manual on `assumed_satisfied`.

Disabling an entry sets the Template to `archived`. It never deletes the post —
generation history references it.

Preferred-template-per-modality is a Connection-level map
(`preferred_templates: { "text-to-image": 412 }`) used when a generation request
names a modality but no explicit template.

---

## 8. REST Surface

Under the existing `storyos/v1` namespace, alongside the connection routes:

| Route | Method | Purpose |
| --- | --- | --- |
| `connections/{id}/templates` | `GET` | Return the cached catalog (`?refresh=1` re-syncs). |
| `connections/{id}/templates/sync` | `POST` | Stage 1. Returns entry count and tier. |
| `connections/{id}/templates/{entry_id}/enable` | `POST` | Stage 2 + Stage 5. Returns the created Template ID. |
| `connections/{id}/templates/{entry_id}/disable` | `POST` | Archive the Template, drop the enable flag. |
| `connections/{id}/templates/{entry_id}/provision` | `POST` | Enqueue Stage 3. Returns job state. |
| `connections/{id}/templates/{entry_id}/status` | `GET` | Provisioning + validation state. |
| `generation/templates/{id}/requirements` | `GET` | Already exists. Unchanged. |

All mutating routes require `manage_options`, a nonce, and must validate that
the Connection's `provider_type` is `comfyui`. Follow the instance-based
`register_routes()` pattern documented in the build instructions — do not
declare it static.

---

## 9. Security and Safety Constraints

- Credentials stay as references. Discovery and download calls resolve the
  credential at call time from env/vault; the catalog snapshot must never
  contain a key.
- `download_models` URLs are attacker-influenced data coming from a remote MCP
  server. Validate each with `esc_url_raw` plus an `https` scheme check before
  dispatch, and display the full host to the operator before any bulk
  provisioning action. Treat a provider-supplied URL list as untrusted input.
- Provisioning is destructive of disk and bandwidth. It is always explicit,
  always logged, always cancellable, and never triggered by a read request.
- Rate-limit sync and provision per Connection using the existing
  `rate_limits` meta.
- Fail clearly when ComfyUI is absent. StoryOS remains fully usable for
  story work with no Connection at all.

---

## 10. Implementation Order

1. Connection-aware capability probe: `capability_tier()`, thread
   `$connection_id` through `supports_tool()` and
   `Comfy_Manifest::request_downloads()`. *(Bug fix — do this first.)*
2. Catalog entry normalizer shared by `discover()` and
   `discover_provider_templates()`; widen the latter's output.
3. Tier C synthesized catalog from `Generation_Modality` + `/object_info`.
4. `comfy_template_catalog` and `enabled_templates` Connection meta, plus
   `Connection_Repository` exposure.
5. REST routes (§8).
6. Template Catalog admin panel (§4.2).
7. Provisioning job + WP-Cron worker + `Generation_Log` integration (§5).
8. Materialization and the `draft` → `active` promotion gate (§7).
9. Local MCP prompt in the setup wizard; ComfyUI-Manager probe.

Steps 1–4 are prerequisites for everything else. Steps 6 and 7 can proceed in
parallel once 5 lands.

---

## 11. Open Questions

- Does the local Comfy MCP server actually advertise `list_templates` /
  `download_models`, or only the job tools? Step 1 answers this empirically —
  run the probe against `mcp_endpoint_url` before committing to Tier A for local.
- Does Comfy Cloud expose any endpoint that reports installed model files? If
  so, §6 can report `satisfied` instead of `assumed_satisfied` for cloud.
- Should `enabled_templates` be per-Connection only, or should a template
  enabled on one Connection propagate as a suggestion to sibling Connections of
  the same tier? Per-Connection only for now; revisit after real multi-Connection
  usage.
