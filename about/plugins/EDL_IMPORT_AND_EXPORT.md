# EDL format tools

Status: delivered admin workflow — CMX 3600 and SMPTE 436m XML parsing,
generation, live timeline export, and persistent import.

World Graph Studio includes a PHP utility and bundled admin page for reading, previewing, importing, and writing edit decision list data.

## Delivered format layer and admin workflow

- CMX-style text parsing and generation, including edit type, transition
  duration, multi-track (e.g. `AA`, `AA/V`), and `* FROM CLIP NAME` / `* SOURCE
  FILE` / `* LOC` / `* EFFECT NAME` comment annotations.
- Any non-blank ASCII line that is not a header, comment, or recognized event
  line is reported back to the admin preview with its 1-based line number and
  raw text instead of being silently dropped.
- XML parsing and generation.
- A preview/confirm workflow: a confirmed import is persisted as a
  `worldgraph_editorial` Editorial Artifact post (with the parsed clips and
  frame rate in post meta) and optionally linked to a Project or Episode.
- Export resolves a live Project or Episode's Scene → Shot timeline (ordered
  by Scene number, then Shot number) instead of returning sample clips.
- Timecode-to-frame and frame-to-timecode helpers with correct handling of
  fractional NTSC rates (23.976/29.97/59.94) and drop-frame output.
- Generator controls for frame rate, reel name, track labels, handles,
  drop-frame output, and longer clip names — all forwarded to the generator.
- A WordPress admin page, AJAX handler, and bundled JS/CSS assets.

The implementation lives in [`edl-import-export.php`](../../wordpress/wp-content/plugins/worldgraph/plugins/edl/edl-import-export.php).

## Current boundary

Exported Shot durations are read from the Shot `duration` field when numeric
(seconds); Shots without a numeric duration fall back to a fixed 2-second
placeholder, since Shots do not carry explicit timecode fields. XML export
does not yet forward the same reel/track/handle options as ASCII export.

## Extension work

Remaining editorial extension opportunities:

1. Recording explicit Shot in/out timecodes so export duration does not
   depend on a free-text `duration` field.
2. Forwarding export options (reel, tracks, handles) through XML generation.
3. Validating additional EDL dialects (Avid, Resolve variants) against the
   line-validation reporting.

This adapter work is distinct from the current screenplay-import and
project-synchronization surfaces. It remains an editorial extension opportunity
under the boundaries recorded in [delivery status](../Delivery_Status.md).

## Enablement

The source is bundled at `wordpress/wp-content/plugins/worldgraph/plugins/edl/`
and is enabled by default. It can be disabled through **World Graph Studio →
Plugins** when the format layer is not needed.
