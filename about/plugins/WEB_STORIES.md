# Google Web Stories connector prototype

Status: extension source; not part of the supported current release.

The repository contains an early connector design for translating between
World Graph Studio Scenes and Google Web Stories. It records the intended
mapping, settings, and REST surface, but it is not loaded by the main World
Graph Studio plugin and should not be presented as delivered synchronization.

## Prototype source

The source under
`wordpress/wp-content/plugins/worldgraph/plugins/web-stories/` includes:

- a proposed WordPress plugin entrypoint;
- a Web Stories REST client;
- Scene-to-Story and Story-to-Scene mapping code;
- a settings class;
- proposed authenticated REST routes under `worldgraph-web-stories/v1`.

The intended local mapping key is `_worldgraph_web_stories_mapping`.

## Why it is not a release feature

- The main plugin and child-plugin manager do not load or expose this
  directory.
- The entrypoint's autoloader does not resolve the filenames currently in the
  directory.
- Enablement is read from a different option than the settings form writes.
- Direction, automatic-sync, storyboard, output-status, and content-source
  settings are not consistently applied by the sync service.
- REST settings updates and loopback authentication need correction.
- There is no supported manual sync dashboard or end-to-end compatibility
  coverage against the Google plugin.

These are implementation boundaries, not installation steps for users.

## Production acceptance criteria

A future supported connector should:

1. Load through an explicit, tested plugin lifecycle.
2. Use one settings contract for enablement and behavior.
3. Apply one-way or two-way direction consistently and prevent save loops.
4. Preserve valid Web Stories document structures in both directions.
5. Authenticate every loopback request and enforce WordPress capabilities.
6. Provide a clear manual sync and conflict-resolution experience.
7. Pass fixture and end-to-end tests against a pinned compatible Web Stories
   release.

Until those criteria are met, creators can still publish media and story data
through custom integrations built on the core World Graph Studio REST API.
