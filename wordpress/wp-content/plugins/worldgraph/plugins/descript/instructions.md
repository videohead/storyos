# Descript Sync maintenance notes

- Keep credentials on `worldgraph_conn`; this plugin stores only its enabled
  state, selected Connection ID, and per-Project sync mappings.
- Descript's API has no editable project schema like VideoDraft's. Treat this
  integration as two independent one-way operations, not a bidirectional
  mirror:
  - **Pull**: export a composition transcript (sync endpoint, raw text body)
    and import it as a Project/World/Scene through `WorldGraph_Importer`.
  - **Push**: submit bound video/audio attachments from a Project's Scenes and
    Shots as an async `import/project_media` job, then poll `jobs/{id}`.
- `export/transcript` returns raw file content, not JSON — `Descript_API`
  handles that response separately from its JSON request helper.
- Descript job history is only available for 30 days; do not rely on job
  records for long-term mapping state beyond what is stored in Project post
  meta.
- Mock every Descript request in automated tests. Never require a real API
  token.
