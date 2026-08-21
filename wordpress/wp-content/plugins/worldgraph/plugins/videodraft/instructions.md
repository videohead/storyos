# VideoDraft Sync maintenance notes

- Keep credentials on `worldgraph_conn`; this plugin stores only its enabled
  state, selected Connection ID, and per-Project sync mappings.
- Treat `tools/list` and `get_project_schema` as runtime contracts. The CLI
  repository does not vendor the hosted project's complete JSON schema.
- Fetch and checkpoint an existing remote project before `update_project`.
  Send complete changed arrays because VideoDraft replaces arrays wholesale.
- Keep pull preview read-only. Commit through `WorldGraph_Importer`, then add
  the imported Scene-to-Project scoping edges.
- Do not add automatic sync until VideoDraft exposes a durable webhook, delta,
  or revision contract and the plugin has a tested loop-prevention policy.
- Mock every VideoDraft request in automated tests. Never require a real PAT.

