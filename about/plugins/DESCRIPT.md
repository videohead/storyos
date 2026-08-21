# Descript Connection and Exchange

World Graph Studio currently includes experimental Descript integration source
for two explicit, independent directions: importing a Descript composition
transcript into the Story Graph and sending a local Project's bound audio/video
media to Descript. It is not yet a release-ready workflow or a bidirectional
project mirror.

## Setup

| Setting | Value |
| --- | --- |
| Connection provider type | `descript` |
| Default endpoint | `https://descriptapi.com/v1` |
| Credential | Descript API token or supported credential reference |
| Plugin state | Descript Sync must be enabled |

The intended setup is to create and test a Descript Connection, then select it
under **World Graph Studio > Descript Sync**. The admin screen and REST routes
load only while the bundled integration is enabled.

## Direction and Mapping

| Operation | Direction | Intended mapping |
| --- | --- | --- |
| Pull transcript | Descript → Story Graph | Export the selected composition transcript and normalize it into a Project, Story World, Sequence, and one transcript Scene through the canonical importer |
| Push Project media | Story Graph → Descript | Collect public video/audio attachments bound to a Project's Scenes and related Shots and submit a Descript project-media import job |

The intended transcript import preserves the returned transcript body inside
`script_content`; it does not infer Characters, Locations, Shots, or editable
Descript composition structure. Stable, Connection-scoped external IDs and
per-Project mapping metadata allow an import to resolve the same local records
on a later pull.

The intended media export transfers references to eligible attachment URLs.
It does not export the Story Graph as an editable Descript schema. The receiving Descript
job must be polled, and Descript's access, quota, retention, and media-fetching
requirements remain external operating conditions.

## REST Surface

The routes require an enabled integration and an administrator with the
configured World Graph Studio capability:

```http
GET    /wp-json/worldgraph/v1/descript/projects
POST   /wp-json/worldgraph/v1/descript/pull
POST   /wp-json/worldgraph/v1/descript/push
GET    /wp-json/worldgraph/v1/descript/jobs/{job_id}
GET    /wp-json/worldgraph/v1/descript/mapping/{project_id}
DELETE /wp-json/worldgraph/v1/descript/mapping/{project_id}
```

Removing a mapping does not delete either the local Project or the Descript
project.

## Current Boundary

- Treat this integration as an experimental scaffold until its canonical media
  relationship lookup, callback route, binary transcript handling, and runtime
  contract have been completed and tested.
- Its current `setup_options` entry also makes the generic wizard present it as
  a generation provider even though it has no Template/generation executor;
  that classification must be corrected before release.
- Pull and push are manual, asymmetric operations.
- A pull imports one transcript Scene rather than a lossless Descript project.
- A push sends bound audio/video attachments rather than project structure.
- World Graph Studio remains the canonical source for its Story Graph records.

See the [Integration Catalog](../Integration_Catalog.md) and
[Script and Editorial Interchange](../Script_EDL_Integration.md) for the wider
toolchain.
