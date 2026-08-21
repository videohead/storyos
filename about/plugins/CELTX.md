# Celtx Connector

**Status: bundled connector source; runtime repair required.** The Celtx code
defines outbound mapping, API, and REST surfaces, but the current sync layer
re-parses already normalized API results and passes them to a raw-response
status check. Scene calls also need episode and argument-order correction.
These defects block a verified outbound workflow in the current tree.

## Intended behavior after repair

- Configure a Celtx API key and project ID in WordPress.
- Create or update supported Celtx elements from World Graph Studio records.
- Synchronize projects, characters, locations, scenes, and shots through the
  connector's service and REST actions.
- Store remote mappings in `_worldgraph_celtx_mapping` post metadata.
- Remove a local mapping without deleting the World Graph Studio record.
- Keep Celtx unavailable or disabled without affecting the Story Graph.

The connector uses WordPress HTTP APIs and remains subject to Celtx API access,
account permissions, endpoint behavior, and terms. Those external conditions
are separate from the current implementation defects above.

## Direction boundary

The current sync direction is:

```text
World Graph Studio -> Celtx
```

The API client can make authenticated reads, but the sync service does not
translate remote Celtx records back into WordPress entities. “Bidirectional”
in the upstream Celtx API name does not mean the bundled connector currently
implements two-way project reconciliation.

## Configuration

Open the Celtx settings surface in WordPress and provide:

- Celtx GEM API key;
- Celtx project ID; and
- the enable/disable setting.

Treat the API key as a secret. Production deployments should avoid copying it
into documentation, logs, exports, or Story Graph content.

## REST surface

The connector registers actions beneath WordPress's `worldgraph/v1`
namespace, including:

- `POST /wp-json/worldgraph/v1/celtx/sync`
- `GET /wp-json/worldgraph/v1/celtx/sync`
- `POST /wp-json/worldgraph/v1/celtx/sync/{type}`
- `POST /wp-json/worldgraph/v1/celtx/sync/{type}/{id}`
- `GET /wp-json/worldgraph/v1/celtx/mapping/{type}/{id}`
- `DELETE /wp-json/worldgraph/v1/celtx/unsync/{type}/{id}`

All routes use WordPress permission checks. Exact request and response details
are documented in the connector source under
`wordpress/wp-content/plugins/worldgraph/plugins/celtx/`.

## Relationship to the interchange suite

The Celtx directory is a defined outbound synchronization extension surface,
not a currently delivered path. Final Draft FDX is the delivered screenplay
file importer, while VideoDraft provides optional bidirectional structural
Project synchronization. Fade In, Highland, Story Architect, and additional
professional exporters are possible future adapters, not capabilities of this
connector. See [Delivery Status](../Delivery_Status.md).
