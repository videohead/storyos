# EDL format tools

Status: implemented PHP format library with an incomplete bundled admin
workflow.

World Graph Studio includes a PHP utility for reading, previewing, and writing edit decision list data. Its current role is format handling—not a finished timeline interchange workflow.

## Implemented format layer and scaffold

- CMX-style text parsing and generation.
- XML parsing and generation.
- A handler that can store parsed preview data temporarily for confirmation.
- Timecode-to-frame and frame-to-timecode helpers.
- Generator controls for frame rate, reel name, track labels, handles, drop-frame output, and longer clip names.
- A WordPress admin-page and AJAX-handler scaffold.

The implementation lives in [`edl-import-export.php`](../../wordpress/wp-content/plugins/worldgraph/plugins/edl/edl-import-export.php).

## Current boundary

The confirmation handler validates and clears the preview, but it does not yet write imported edits into World Graph Studio posts or fields. Export currently receives development sample clips instead of resolving a live Project or Episode timeline. The JavaScript and stylesheet referenced by the admin page are also not bundled. Fractional-frame-rate controls also need end-to-end validation before drop-frame output should be considered production-ready.

Accordingly, documentation and marketing should describe this component as an
**EDL parsing, timecode, and generation library with an admin scaffold**. It
should not be presented as a completed admin workflow or round-trip NLE
integration.

## Extension work

A production adapter can build on the delivered format layer by:

1. Defining how EDL clips map to Scenes, Shots, Assets, and ordering fields.
2. Persisting a confirmed preview as an atomic, repeatable import.
3. Resolving live Project or Episode data for export.
4. Adding the admin interaction assets and end-to-end tests.
5. Validating fractional frame rates and output in each target editing application.

This adapter work is distinct from the current screenplay-import and
project-synchronization surfaces. It remains an editorial extension opportunity
under the boundaries recorded in [delivery status](../Delivery_Status.md).

## Enablement

The source is bundled at `wordpress/wp-content/plugins/worldgraph/plugins/edl/`
and is enabled by default. The plugin toggle does not make the incomplete admin
workflow release-ready; it can be disabled through **World Graph Studio →
Plugins** when the format layer is not needed.
