# Setup wizard credential model

World Graph Studio's setup wizard configures optional generation and LLM
connections. Core Story Graph authoring, JSON import, and Markdown export do
not require an API key.

The canonical field-by-field guide is the
[Setup Wizard Guide](../wordpress/wp-content/plugins/worldgraph/documentation/SETUP_WIZARD_GUIDE.md).

## Delivered behavior

- Only administrators with `manage_options` can view, test, or save setup.
- Generation choices are sourced from the installed Connection-adapter
  registry.
- The wizard can create or update one managed generation Connection and one
  managed LLM Connection.
- Local ComfyUI can be configured without a provider credential.
- Hosted fal, ElevenLabs, and Comfy Cloud choices accept the credential needed
  by their adapter.
- LLM configuration stores provider, endpoint, model, credential, maximum
  tokens, and temperature.
- Connection and LLM tests use the unsaved form values; testing and saving are
  separate actions.
- Saving marks setup complete and runs or schedules the relevant Template
  bootstrap.

The current form does not expose separate fallback-LLM credential fields.

## Where credentials live

Wizard-entered provider credentials are persisted in the managed Connection's
`credential_reference` field. The primary LLM key is also represented by the
`worldgraph_ai_api_key` option for the AI Editor's current configuration path.

For the primary LLM, deployments can define `WORLDGRAPH_AI_API_KEY` in
`wp-config.php`. fal and ElevenLabs Connections also support explicit
`env://FAL_KEY` and `env://ELEVENLABS_API_KEY` references.

Database backups can contain secrets. Keep backups, logs, screenshots, and
tracked environment files private.

## Useful checks

```bash
lando wp option get worldgraph_gen_connection_mode
lando wp option get worldgraph_ai_backend
lando wp option get worldgraph_ai_model
lando wp option get worldgraph_setup_complete
```

Do not print credential options in shared terminal logs. Use **World Graph
Studio → Connections** to test providers and review non-secret status fields.
