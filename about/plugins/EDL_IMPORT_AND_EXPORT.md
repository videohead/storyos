# StoryOS EDL Import/Export Plugin

## Overview

The EDL (Edit Decision List) Import/Export plugin enables seamless integration between StoryOS and professional video editing software. It allows creators to export shot timelines from StoryOS projects and episodes, edit them in external NLEs (Non-Linear Editors), and re-import the results.

**Status**: ✅ Implemented — StoryOS Plugin (disabled by default, enableable from **StoryOS → Plugins**)

## Supported Formats

| Format | Import | Export | Compatible With |
|--------|--------|--------|-----------------|
| CMX 3600 (ASCII) | ✅ | ✅ | Premiere Pro, Avid, DaVinci Resolve, Unreal Engine, Final Cut Pro |
| SMPTE 436m (XML) | ✅ | ✅ | XML-aware NLEs, automated pipelines |

## Features

### Import
- Upload `.txt`, `.edl`, or `.xml` EDL files
- Preview detected clips before importing
- Automatic parsing of CMX 3600 and XML formats
- Frame rate detection and conversion
- Error handling with detailed messages

### Export
- Export StoryOS project/episode timelines as EDL
- **CMX 3600 ASCII** — universal NLE format
- **SMPTE 436m XML** — structured XML format
- **Drop-frame timecode** for 29.97/59.94fps NTSC
- **Pre-roll / Post-roll handles** for Unreal Engine Sequencer edit flexibility
- **32-character clip names** for Premiere Pro compatibility
- Configurable reel names, video tracks (V1/V2/V C), and audio tracks (A1/A2/A C)
- Frame rate presets: 23.976, 24, 25, 29.97, 30, 50, 59.94, 60

## Installation

1. The plugin is included with StoryOS at `wordpress/wp-content/plugins/storyos/plugins/edl/`
2. It is loaded automatically but **disabled by default**
3. Enable it via **StoryOS → Plugins → EDL Import/Export → Enable**
4. Navigate to **StoryOS → EDL Manager** to access the plugin

## Usage

### Importing EDL

1. Go to **StoryOS → EDL Manager**
2. Click the **Import EDL** tab
3. Select the EDL format (CMX 3600 or XML)
4. Choose the frame rate matching the source footage
5. Upload the `.txt`, `.edl`, or `.xml` file
6. Click **Preview EDL** to see detected clips
7. Review the preview table (clip name, source in/out, record in/out, duration)
8. Click **Confirm Import** to persist clips to StoryOS

### Exporting EDL

1. Go to **StoryOS → EDL Manager**
2. Click the **Export EDL** tab
3. Select target type (Project or Episode)
4. Choose export format:
   - **CMX 3600 (ASCII)** — recommended for all NLEs
   - **XML (SMPTE 436m)** — for XML-aware tools
5. Configure export options:
   - **Frame Rate** — must match source footage
   - **Reel Name** — source identifier (max 8 chars)
   - **Pre-Roll** — frames of pre-roll handles (Unreal Engine)
   - **Post-Roll** — frames of post-roll handles (Unreal Engine)
   - **32-Character Names** — enable for Premiere Pro long names
   - **Drop-Frame Timecode** — required for 29.97/59.94fps
   - **Video Track** — `V1`, `V2`, `V C` (all video)
   - **Audio Track** — `A1`, `A2`, `A C` (all audio), or empty to exclude
6. Click **Export EDL** to download the file

## EDL Format Reference

### CMX 3600 ASCII Format

```
TITLE:  StoryOS EDL
FM:     CMX-3600
DATE:   Aug 08 2026
PM:     StoryOS

0001  REEL 00   V  C  00:00:00:00 00:00:03:00 00:00:00:00 00:00:03:00  * SC001_SH001
0001  REEL 00   A  C  00:00:00:00 00:00:03:00 00:00:00:00 00:00:03:00  * SC001_SH001
0002  REEL 00   V  C  00:00:03:00 00:00:06:00 00:00:03:00 00:00:06:00  * SC001_SH002
0002  REEL 00   A  C  00:00:03:00 00:00:06:00 00:00:03:00 00:00:06:00  * SC001_SH002
```

| Field | Position | Description |
|-------|----------|-------------|
| LINE# | 1–4 | Edit line number (sequential, 4 digits) |
| REEL | 6–13 | Source reel/tape name (8 chars) |
| VIDEO | 15–18 | Video track designator (e.g., `V  C`, `V1  `) |
| TRANS | 19 | Transition type (`C`=Cut, `F`=Fade, `D`=Dip, `M`=Match) |
| FILM-IN | 21–32 | Source in point (`HH:MM:SS:FF` or `HH:MM:SS;FF` for drop-frame) |
| FILM-OUT | 34–45 | Source out point |
| REC-IN | 47–58 | Record in point (timeline position) |
| REC-OUT | 60–71 | Record out point |
| CLIP-NAME | 76–83 (8-char) or 76–107 (32-char) | Clip/tape name |

### Drop-Frame Timecode

For 29.97fps and 59.94fps, frames use a semicolon (`;`) separator:

```
00:00:00;00  — 0 frames elapsed
00:00:01;00  — 1 second elapsed (frames 2 and 3 were dropped)
00:00:02;00  — 2 seconds elapsed
```

Drop-frame skips frames 0 and 1 of every minute (except every 10th minute) to stay within ~3.6 seconds of wall-clock time.

## NLE Compatibility Matrix

| Feature | Unreal Engine | Premiere Pro | DaVinci Resolve | Avid Media Composer | Final Cut Pro |
|---------|--------------|--------------|-----------------|---------------------|---------------|
| CMX 3600 ASCII | ✅ | ✅ | ✅ | ✅ | ✅ |
| SMPTE 436m XML | ✅ | ✅ | ✅ | ✅ | ✅ |
| Drop-Frame TC | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pre/Post Roll | ✅ | ✅ | ✅ | ✅ | ✅ |
| 32-char Names | ✅ | ✅ | ✅ | ✅ | ✅ |
| Multi-track (V/A) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Frame Handles | ✅ | ✅ | ✅ | ✅ | ✅ |

## Unreal Engine Sequencer Workflow

1. **Export from StoryOS**: Export shot timeline as EDL with pre-roll/post-roll handles
2. **Render in Unreal Engine**: Sequencer exports video clips + EDL
3. **Edit in NLE**: Import EDL into Premiere Pro/DaVinci Resolve, link media, make edits
4. **Re-import to UE**: Export edited EDL from NLE, import back into Unreal Engine Sequencer
5. **Sync Changes**: Updated timing/cuts reflected in the Unreal Engine sequence

## Architecture

```
StoryOS (WordPress Plugin)
    ↓
EDL Manager (Admin UI)
    ├── Import Tab
    │   ├── File Upload
    │   ├── Format Detection (CMX 3600 / XML)
    │   ├── Preview Table
    │   └── Confirm Import → Save to StoryOS
    └── Export Tab
        ├── Format Selection (CMX 3600 / XML)
        ├── Frame Rate Presets
        ├── Handle Configuration (Pre/Post Roll)
        ├── Track Configuration (V/A)
        ├── Drop-Frame Toggle
        └── Download EDL File
    ↓
AJAX Handler (wp_ajax_storyos_edl_action)
    ├── handle_import() → parse_edl() → set_transient()
    ├── handle_export() → generate_edl() → file download
    └── handle_confirm_import() → persist to DB
    ↓
Parser/Generator
    ├── parse_edl_ascii() — CMX 3600 regex parser
    ├── parse_edl_xml() — XML DOM parser
    ├── generate_edl_ascii() — CMX 3600 output
    └── generate_edl_xml() — SMPTE 436m XML output
    ↓
Utilities
    ├── timecode_to_frames() — HH:MM:SS:FF → frame number
    ├── frames_to_timecode() — frame number → HH:MM:SS:FF
    └── frames_to_timecode_drop() — frame number → HH:MM:SS;FF (drop-frame)
```

## Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| Default Frame Rate | 24 fps | Used for import when not specified |
| Reel Naming | `REEL 001` | Default reel name for exports |
| Video Track | `V  C` | Default video track designator |
| Audio Track | `A  C` | Default audio track designator |
| Pre-Roll Handles | 0 | Frames of pre-roll (Unreal Engine) |
| Post-Roll Handles | 0 | Frames of post-roll (Unreal Engine) |
| 32-Char Names | Off | Premiere Pro long clip names |
| Drop-Frame TC | On | Required for 29.97/59.94fps |

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Import fails | Malformed EDL file | Verify format matches selector (CMX 3600 vs XML) |
| Missing clips | No valid entries parsed | Check EDL file has proper CMX 3600 structure |
| Timecode errors | Frame rate mismatch | Ensure frame rate matches source footage |
| Missing media | Source files unavailable | EDL references media by reel/clip name — link manually in NLE |
| Drop-frame issues | Wrong separator | Use `;` for frames in 29.97/59.94fps, `:` for all others |
| Clip names truncated | 8-char limit | Enable "32-character clip names" option |

## Support

For issues or feature requests, visit the [StoryOS GitHub repository](https://github.com/storyos).

## External References

- [Unreal Engine EDL Import/Export](https://dev.epicgames.com/documentation/unreal-engine/import-and-export-edl-in-unreal-engine?lang=en-US)
- [Premiere Pro EDL Export](https://helpx.adobe.com/premiere/desktop/render-and-export/export-files/export-a-project-as-an-edl-file.html)
- [DaVinci Resolve EDL Workflow](https://drexelcinetv.tech/workflow/davinci-resolve/davinci-edl-workflow/)
- [SMPTE 436m Specification](https://grokipedia.com/page/Edit_decision_list)
