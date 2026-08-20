# Celtx Connector

**Status: delivered optional outbound connector.** The bundled Celtx code sends
supported World Graph Studio records to the Celtx GEM API and retains the
remote element IDs required for later updates. It does not currently import
Celtx changes into WordPress.

## Delivered behavior

- Configure a Celtx API key and project ID in WordPress.
- Create or update supported Celtx elements from World Graph Studio records.
- Synchronize projects, characters, locations, scenes, and shots through the
  connector's service and REST actions.
- Store remote mappings in `_worldgraph_celtx_mapping` post metadata.
- Remove a local mapping without deleting the World Graph Studio record.
- Keep Celtx unavailable or disabled without affecting the Story Graph.

The connector uses WordPress HTTP APIs and remains subject to Celtx API access,
account permissions, endpoint behavior, and terms.

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

## Relationship to on-hold script work

The Celtx connector is delivered and maintained. Additional FDX, Fade In,
Highland, Story Architect, and professional script import/export formats are
separate work and are on hold. See [Delivery Status](../Delivery_Status.md).
