# World Graph Studio Setup Wizard

## Purpose

The setup wizard creates the managed Connections used by media generation and
the AI Editor. It is a first-run convenience, not a requirement for core Story
Graph authoring.

Current release status is defined in
[Delivery Status](../../../../../about/Delivery_Status.md). An optional service
being unconfigured does not make its World Graph Studio integration unfinished.

## Access and permissions

Activation sets a one-time redirect for administrators when
`worldgraph_setup_complete` is false. The page is always available at:

`/wp-admin/admin.php?page=worldgraph-setup`

Only users with `manage_options` may view, test, or save setup. Until the form
is submitted, other World Graph Studio admin pages redirect administrators back
to setup; unrelated WordPress admin screens remain accessible.

The form may be submitted with all optional service fields blank.

## Wizard sections

### 1. WordPress Runtime

This section confirms that WordPress is the application runtime and reminds the
operator to configure a reliable WP-Cron trigger. It is informational; the
wizard does not install a host scheduler.

### 2. Generation Connection (optional)

The preferred-Connection list is built from installed adapter metadata:

| Choice | Provider record | Fields and behavior |
| --- | --- | --- |
| Comfy Cloud MCP | `provider_type = comfyui`, production | Hosted provider credential; fixed MCP endpoint |
| Local ComfyUI HTTP API + MCP | `provider_type = comfyui`, local | Local HTTP URL plus optional separate MCP URL |
| fal MCP | `provider_type = fal`, production | fal API key; fixed MCP endpoint |
| ElevenLabs Generative Audio | `provider_type = elevenlabs`, production | ElevenLabs API key; fixed REST endpoint |
| Suno API + MCP | `provider_type = suno`, production | SunoAPI.org key plus a separate AceData Cloud MCP token; fixed REST and MCP endpoints |
| No generation connection yet | none | Does not create, update, or delete a managed generation Connection |

The entered hosted-provider credential is written to the managed Connection's
`credential_reference` field. The field is hidden for local ComfyUI and when
no provider is selected. Suno also displays a separate MCP-token field and
writes it to `mcp_credential_reference`; the two Suno providers do not share
bearer tokens.

#### Local ComfyUI fields

**Local ComfyUI API URL** is the normal ComfyUI HTTP endpoint. In Lando, use:

`http://host.lando.internal:8188`

**Local ComfyUI MCP URL** is optional and must point to a separate MCP server.
ComfyUI's HTTP API does not become MCP by adding `/mcp`.

Saving local setup ensures a managed text-to-image Template exists. After the
form is saved, the readiness panel reports missing nodes or checkpoints and
offers a recheck action.

#### Provider tests

**Test Generation Connection** uses the unsaved form values:

- local ComfyUI requests `/system_stats`;
- fal initializes MCP and checks the required generation tools;
- ElevenLabs reads the voice/model catalog;
- Suno checks the SunoAPI.org credit endpoint and the required AceData Cloud
  MCP tools with their separate credentials; and
- Comfy Cloud is saved first and managed from the Connections screen.

Tests do not store the unsaved values. Saving is a separate action.

#### Catalog side effects

Saving:

- creates the managed local text-to-image Template for local ComfyUI;
- schedules fal Template provisioning;
- schedules ElevenLabs voice/model Template provisioning; or
- schedules six transport-specific Suno music, custom-music, and lyrics
  Templates.

ComfyUI provider-catalog sync and manual materialization remain available on
the saved Connection.

### 3. External Generator Workflow

This section explains how to bring media from a provider's web application into
WordPress. It stores no provider credentials and creates no Connection.

### 4. LLM Connection

An LLM is required for the AI Editor and filmmaking advisors, not for core
World Graph Studio content.

The form stores:

- provider: `openai_compatible`, `openai`, `anthropic`, or `dual`;
- base URL;
- model identifier;
- API key/token;
- maximum response tokens; and
- temperature.

For a local service running on the Lando host, use a container-reachable URL,
for example `http://host.lando.internal:11434/v1`.

**Test LLM Connection** evaluates the current unsaved values. For a compatible
endpoint it can populate the model datalist from the provider response.

If the PHP constant `WORLDGRAPH_AI_API_KEY` is defined, the primary key field
is disabled and the constant is used for wizard testing. The wizard does not
expose separate cloud-fallback fields in the current form.

## Save flow

Selecting **Save All Configurations** performs a nonce and capability check,
then:

1. validates the generation choice against the adapter registry;
2. saves generation mode and local ComfyUI URL options;
3. creates or updates the managed `generation` Connection when selected;
4. schedules or performs the provider-specific Template bootstrap;
5. saves primary LLM and advanced response settings;
6. creates or updates the managed `llm` Connection;
7. sets `worldgraph_setup_complete` to true; and
8. redirects back with a success notice.

Managed records are identified by `worldgraph_wizard_slot`, so rerunning setup
updates them rather than creating another wizard-owned Connection.

## Stored state

### WordPress options

| Option | Purpose | Default/fallback |
| --- | --- | --- |
| `worldgraph_gen_connection_mode` | Current generation choice | `none` |
| `worldgraph_comfy_connection_mode` | Compatibility mirror of the choice | `none` |
| `worldgraph_comfy_local_url` | Local ComfyUI HTTP URL | form suggests `http://host.lando.internal:8188` |
| `worldgraph_comfy_local_mcp_url` | Optional separate local MCP URL | empty |
| `worldgraph_ai_backend` | Primary LLM backend | `openai_compatible` |
| `worldgraph_ai_url` | Primary LLM base URL | empty from a newly submitted blank form |
| `worldgraph_ai_model` | Primary model | empty |
| `worldgraph_ai_api_key` | Primary LLM key when no constant is defined | empty |
| `worldgraph_ai_max_tokens` | Response limit | `2048` in wizard |
| `worldgraph_ai_temperature` | Sampling temperature | `0.7` |
| `worldgraph_setup_complete` | First-run gate | false until saved |

The compatibility `worldgraph_comfy_connection_mode` option is written
alongside the current generation option. New code should use
`worldgraph_gen_connection_mode`.

### Managed Connection fields

The generation record stores the selected provider type, environment, endpoint,
MCP endpoint where applicable, credential, and `unverified` status. A Suno
record also stores its distinct AceData Cloud token in
`mcp_credential_reference`.

The LLM record stores the backend as provider type, endpoint, credential, model,
max tokens, and temperature. It uses the `llm` wizard slot.

## Credentials

### Primary LLM environment override

Map the deployment environment into a PHP constant in `wp-config.php`:

```php
define( 'WORLDGRAPH_AI_API_KEY', getenv( 'WORLDGRAPH_AI_API_KEY' ) ?: '' );
```

The environment variable by itself does not define a PHP constant.

### Generation credentials

The wizard accepts a provider credential and stores it on the managed
Connection. The fal and ElevenLabs adapters also resolve manually configured
`env://FAL_KEY` and `env://ELEVENLABS_API_KEY` references.

Suno requires two credentials. `credential_reference` accepts the SunoAPI.org
key or `env://SUNO_API_KEY`; `mcp_credential_reference` accepts the AceData
Cloud token or `env://ACEDATACLOUD_API_TOKEN`. A Suno website subscription,
browser session, or key from the other service is not a substitute.

Do not place secrets in tracked `.env` files, screenshots, logs, Template JSON,
or REST examples. Protect database backups because wizard-entered credentials
are persisted.

## Reopen or reset

You can revisit the page without resetting anything:

`/wp-admin/admin.php?page=worldgraph-setup`

To restore first-run redirect behavior:

```bash
lando wp option update worldgraph_setup_complete 0
```

Submitting the wizard sets it to true again.

## Verify saved configuration

```bash
lando wp option get worldgraph_gen_connection_mode
lando wp option get worldgraph_ai_backend
lando wp option get worldgraph_ai_model
lando wp option get worldgraph_setup_complete
```

Do not print credential options in a shared terminal log. Use the Connections
screen to test providers and inspect non-secret status fields.

## Troubleshooting

### The activation redirect did not appear

Open the setup URL directly. Redirects are intentionally skipped for AJAX,
cron, bulk activation, and users without `manage_options`.

### World Graph Studio pages keep returning to setup

Submit the form once, or verify:

```bash
lando wp option get worldgraph_setup_complete
```

### Local ComfyUI test cannot connect

- Use `host.lando.internal`, not `localhost`, for a service on the Lando
  host.
- Confirm the URL is the ComfyUI HTTP base, not an MCP URL.
- Confirm the host firewall and bind address allow the appserver container.

### fal, ElevenLabs, or Suno test succeeds but Templates are not visible

Saving schedules a single WP-Cron catalog event. Run due events and inspect the
Connection's provider configuration:

```bash
lando wp cron event run --due-now
```

Then review the Connection's last catalog sync/error fields.

For Suno, verify that six REST/MCP Templates were provisioned. They do not
appear in the story-post Assets metabox because that surface currently lists
image-output Templates only.

### LLM test cannot find models

- Confirm the URL is reachable from WordPress.
- Use the correct OpenAI-compatible `/v1` base.
- Check the model endpoint's authentication requirement.
- Select or enter a model returned by the endpoint.

## Related documentation

- [Full Setup Guide](SETUP_GUIDE.md)
- [Plugin Architecture](ARCHITECTURE.md)
- [Generation Engine](../../../../../about/plugins/GENERATION_ENGINE.md)
- [Deployment and Connections](../../../../../about/Deployment_and_Connections.md)
- [Suno Integration](../../../../../about/plugins/SUNO.md)
