# Celtx connector implementation

Status: optional outbound connector.

The bundled Celtx extension sends supported World Graph Studio records to Celtx with WordPress-native HTTP requests. It creates or updates remote elements and stores local mappings in `_worldgraph_celtx_mapping`.

## Direction

The implemented sync direction is **World Graph Studio → Celtx**. The API client includes read methods, but there is no Celtx-to-World-Graph-Studio import or conflict-resolution workflow. Do not describe this connector as bidirectional.

## Supported local records

The sync service handles Projects, Characters, Locations, Scenes, Shots, Assets, and Props. Shots are represented through Celtx comments because the remote model has no equivalent World Graph Studio Shot type.

## WordPress REST surface

Authenticated administrators can test the connection, request outbound sync, inspect a local mapping, or remove a mapping under `/wp-json/worldgraph/v1/celtx/`.

## Configuration

Credentials and remote project configuration are stored through the extension settings screen. Treat API keys as secrets and grant settings/sync access only to trusted administrators.

See the canonical [Celtx connector documentation](../../../../../../about/plugins/CELTX.md) for routes, mappings, and limitations.
