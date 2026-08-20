# World Graph Studio Script and EDL Integration

> Build worlds. Connect ideas. Generate anything. No credits needed.

## Current Release Boundary

World Graph Studio treats the Story Graph as the canonical source of truth and
provides several delivered interchange paths around it:

| Capability | Current status |
| --- | --- |
| World Graph Studio JSON import | Delivered |
| Markdown screenplay export | Delivered |
| Markdown storyboard export | Delivered |
| Celtx synchronization | Delivered optional plugin |
| CMX 3600 EDL formatter/download | Delivered optional plugin; current admin export uses sample clip data |
| SMPTE 436m XML EDL formatter/download | Delivered optional plugin; current admin export uses sample clip data |
| CMX/XML EDL parsing and preview | Delivered optional plugin |
| Additional professional script-file formats | On hold |

See [Delivery Status](Delivery_Status.md) for the repository-wide status source
of truth. “Script import/export is on hold” refers only to the additional
formats listed below; it does not withdraw JSON import, Markdown export, or
Celtx synchronization.

## Canonical Workflow

```text
World Graph Studio JSON or Celtx
                ↓
          Story Graph
      ↙         ↓          ↘
Markdown      Storyboards      Shot planning
exports       and assets       and editorial data
                                  ↓
                         CMX/XML EDL formatting
```

Scripts, storyboards, shot lists, and editorial files are projections or
exchange artifacts. Project, World, Character, Location, Scene, Shot, Sound,
Asset, and relationship records remain canonical in WordPress.

## Delivered Project Interchange

### World Graph Studio JSON Import

The core importer accepts a versioned World Graph Studio JSON document through
the WordPress admin or these administrator-only REST routes:

```http
POST /wp-json/worldgraph/v1/import/validate
POST /wp-json/worldgraph/v1/import
```

Validation can run without writes. A committed import creates or updates the
supported Project, Story World, Character, Location, Prop, Scene, Shot, Sound,
Storyboard Frame, and Sequence records; resolves external IDs; assigns terms;
builds relationships; and verifies the resulting counts and references.

JSON import is the delivered structured project importer. It is not an FDX,
PDF, or generic screenplay parser.

### Markdown Screenplay Export

The WordPress Export screen derives a screenplay-style Markdown document from
the selected live Project, including its ordered Scenes, locations, dialogue,
characters, and Shots where present. The download uses a `-screenplay.md`
suffix.

### Markdown Storyboard Export

The same screen can derive a storyboard Markdown document from live Scenes,
Shots, Storyboard Frames, descriptions, prompts, image references, camera
notes, and ordering. The download uses a `-storyboard.md` suffix.

Markdown output is intentionally readable, diffable, and suitable for version
control. The current release does not claim native Final Draft or other
professional screenplay-file output.

## Delivered Celtx Synchronization

The bundled `worldgraph-celtx` plugin provides optional outbound
synchronization to the Celtx GEM API for supported entities:

| World Graph Studio entity | Celtx representation |
| --- | --- |
| Project | Project |
| Character | Character element |
| Location | Location element |
| Scene | Scene/element |
| Shot | Shot element |

Persistent `_worldgraph_celtx_mapping` post meta stores the remote identity and
sync timestamp by entity category.

The integration includes connection testing, full and type-specific outbound
sync, individual-item sync, mapping inspection, and unsync actions under the
`worldgraph/v1/celtx/*` REST surface. The API client can read Celtx resources,
but the current sync service does not import remote changes into WordPress.
It requires Celtx credentials and a reachable Celtx service; those operating
requirements do not make the delivered outbound workflow pending.

## On Hold: Additional Script Formats

The following capabilities are not part of the current release and are not
active delivery commitments:

- Final Draft FDX import.
- Fade In import.
- Highland import.
- Story Architect project import.
- PDF screenplay extraction.
- Automated screenplay parsing and Story Graph entity extraction for those
  formats.
- Format-specific import preview, deduplication, and merge workflows.
- Professional screenplay exporters beyond the delivered Markdown views.
- Additional script synchronization providers beyond Celtx.

No `/scripts/import`, `/scripts/export`, preview, or commit REST routes are
registered in v1. Extensions should use their own namespaces until they satisfy
the core Story Graph mapping and validation contract.

## Story Graph Mapping Rules

An interchange adapter should preserve these meanings:

### Character

- Dialogue speaker and action references map to Character records.
- Scene participation is a graph relationship, not duplicated free text.
- Storyboard and Asset links remain associated with the Character.

### Location

- Scene headings and structured location references resolve to Location
  records.
- Location visual references and generated Assets retain their provenance.

### Scene

- Scene order, title/heading, summary, script content, structured dialogue,
  location, time of day, and production notes remain distinct fields.
- Shots, Sounds, Storyboard Frames, Characters, and Assets remain linked rather
  than flattened into one document.

### Shot and Storyboard

- Shot order, camera/lens information, duration, slate/take data, and editorial
  notes remain attached to the Shot.
- Storyboard Frames link to a Scene or Shot and can link to an image Asset.

## EDL Integration

The optional EDL plugin adds a capability- and nonce-protected WordPress admin
screen. It does not add a REST namespace.

### Delivered Formats

| Format | Parse/preview | Export |
| --- | --- | --- |
| CMX 3600 ASCII (`.txt`, `.edl`) | Yes | Yes |
| SMPTE 436m XML (`.xml`) | Yes | Yes |
| AAF | No | No |
| OMF | No | No |

The import side parses an uploaded CMX/XML document into normalized clip data,
converts timecodes to frame positions, stores a short-lived preview, and lets
an authorized user confirm the preview. Confirmation does not currently create
or update persistent Story Graph timeline entities. “EDL import” in the current
plugin therefore means parsing and preview, not a persisted NLE round trip.

The export side formats clip arrays as downloadable CMX 3600 or SMPTE 436m XML.
The current Project/Episode timeline resolver is a development placeholder that
returns two fixed sample clips; it does not yet derive a live Story Graph cut.

### Export Surface and Controls

The admin screen exposes:

- Frame-rate choices for 23.976, 24, 25, 29.97, 30, 50, 59.94, and 60 fps.
- CMX reel names and configurable video/audio track designators.
- Optional pre-roll and post-roll handles.
- Eight- or 32-character clip-name formatting.
- A drop-frame option for fractional NTSC rates.
- Sequential record-in/record-out positions derived from source clip lengths.

The current admin handler does not forward its reel, handle, track, clip-name,
or drop-frame choices to the formatter, and its fractional-rate values are
integer sentinels rather than usable fractional rates. Treat those controls as
prototype UI. The delivered path is format generation and download from the
formatter's current clip input, not a validated live-timeline export.

The CMX output is intended for NLEs that accept CMX 3600, including common
Premiere Pro, DaVinci Resolve, Avid Media Composer, and Unreal Sequencer
workflows. Actual compatibility still depends on the receiving application's
version, import settings, supported CMX subset, frame rate, and media relinking.
The XML path uses the plugin's SMPTE 436m event/component/timecode structure;
consumers should validate it against their target application.

### Current EDL Boundaries

- World Graph Studio does not ship AAF or OMF codecs.
- It does not ship Premiere Pro, Resolve, Avid, Final Cut, or Unreal panels.
- It does not embed or transfer source media in an EDL.
- EDL clip names and timecodes do not replace Story Graph identity and
  relationship metadata.
- Imported EDL preview data is transient in the current plugin.
- Project/Episode timeline extraction and fully wired export controls are not
  delivered.

AAF, OMF, persistent timeline import, direct media relinking, and NLE-specific
panels are valid extension points, not promised current-release work.

## Production, Continuity, and Advisors

World Graph Studio uses connected Scenes, Shots, Sounds, Storyboards, Assets,
and Editorial Artifacts to preserve context across planning and export. The
delivered continuity checker, graph analytics, AI Editor, and specialist
advisors can analyze that data without treating an imported script or EDL as a
second source of truth.

Advisor output may include scene analysis, shot suggestions, storyboarding
ideas, production preparation, and editorial guidance. It becomes canonical
only when a user saves approved information to the Story Graph.

## Design Contract

A script is not the source of truth.

A storyboard is not the source of truth.

An EDL is not the source of truth.

The Story Graph is the source of truth, and delivered exchange formats remain
traceable views of that structured data.
