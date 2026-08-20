# World Graph Studio EDL Import/Export Plugin

## Overview
This plugin enables importing and exporting Edit Decision List (EDL) files from World Graph Studio projects and episodes. Compatible with CMX 3600 (ASCII) and SMPTE 436m (XML) EDL formats.

**Status**: ✅ Implemented — World Graph Studio Plugin (disabled by default, enableable from **World Graph Studio → Plugins**)

## Features
- **Import EDL**: Upload EDL files to automatically create clips and edit points in your project
- **Export EDL**: Export current project or episode timeline as a standard EDL file
- **Format Support**: CMX 3600 (ASCII) and SMPTE 436m (XML) EDL formats
- **Visual Preview**: Preview EDL content before importing
- **Error Handling**: Detailed error messages for malformed EDL files
- **Drop-Frame Timecode**: Correct handling for 29.97/59.94fps NTSC video (semicolon frame separator)
- **Frame Handles**: Pre-roll and post-roll handles for Unreal Engine Sequencer edit flexibility
- **32-Character Clip Names**: Premiere Pro compatible long clip/tape names
- **Multi-Track Support**: Configurable video tracks (V1, V2, V C) and audio tracks (A1, A2, A C)
- **Frame Rate Presets**: 23.976, 24, 25, 29.97, 30, 50, 59.94, 60

## Installation
1. The plugin is included with World Graph Studio at `wordpress/wp-content/plugins/worldgraph/plugins/edl/`
2. It is loaded automatically but **disabled by default**
3. Enable it via **World Graph Studio → Plugins → EDL Import/Export → Enable**
4. Navigate to **World Graph Studio → EDL Manager** to access the plugin

## Usage

### Importing EDL
1. Go to **World Graph Studio → EDL Manager**
2. Click the **Import EDL** tab
3. Select the EDL format (CMX 3600 or XML)
4. Choose the frame rate matching the source footage
5. Upload the `.txt`, `.edl`, or `.xml` file
6. Click **Preview EDL** to see detected clips
7. Review the preview table (clip name, source in/out, record in/out, duration)
8. Click **Confirm Import** to persist clips to World Graph Studio

### Exporting EDL
1. Go to **World Graph Studio → EDL Manager**
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

### CMX 3600 Format (Default)

```
TITLE:  World Graph Studio EDL
FM:     CMX-3600
DATE:   Aug 08 2026
PM:     World Graph Studio

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

### SMPTE 436m XML Format

```xml
<?xml version="1.0" encoding="UTF-8"?>
<smpte:edl xmlns:smpte="urn:smpte:umid:1.0">
  <smpte:header>
    <smpte:title>World Graph Studio EDL</smpte:title>
    <smpte:version>1</smpte:version>
  </smpte:header>
  <smpte:body>
    <smpte:event>
      <smpte:operation>
        <smpte:editmode>
          <smpte:transtype>cut</smpte:transtype>
        </smpte:editmode>
      </smpte:operation>
      <smpte:timecode>
        <smpte:rate>24/1</smpte:rate>
        <smpte:time>00:00:00:00</smpte:time>
      </smpte:timecode>
      <smpte:component>
        <smpte:videoreel>01</smpte:videoreel>
        <smpte:position>1</smpte:position>
        <smpte:authority>World Graph Studio</smpte:authority>
        <smpte:name>SC001_SH001</smpte:name>
        <smpte:duration>00:00:03:00</smpte:duration>
      </smpte:component>
    </smpte:event>
  </smpte:body>
</smpte:edl>
```

## NLE Compatibility

| Feature | Unreal Engine | Premiere Pro | DaVinci Resolve | Avid | FCP |
|---------|--------------|--------------|-----------------|------|-----|
| CMX 3600 | ✅ | ✅ | ✅ | ✅ | ✅ |
| XML | ✅ | ✅ | ✅ | ✅ | ✅ |
| Drop-Frame | ✅ | ✅ | ✅ | ✅ | ✅ |
| Handles | ✅ | ✅ | ✅ | ✅ | ✅ |
| 32-char Names | ✅ | ✅ | ✅ | ✅ | ✅ |
| Multi-Track | ✅ | ✅ | ✅ | ✅ | ✅ |

## Configuration
- **Frame Rate**: Must match source footage (presets: 23.976, 24, 25, 29.97, 30, 50, 59.94, 60)
- **Reel Name**: Source identifier (max 8 chars for CMX 3600)
- **Pre-Roll/Post-Roll**: Frames of handles for edit flexibility (Unreal Engine)
- **32-Character Names**: Enable for Premiere Pro compatibility
- **Drop-Frame**: Required for 29.97/59.94fps NTSC video
- **Video Track**: `V1`, `V2`, or `V C` (all video)
- **Audio Track**: `A1`, `A2`, or `A C` (all audio), or empty to exclude

## Troubleshooting
- **Import Fails**: Ensure EDL file is properly formatted (CMX 3600 recommended)
- **Missing Clips**: Check that source media is available in World Graph Studio
- **Timecode Errors**: Verify frame rate matches between EDL and project settings
- **Drop-Frame Issues**: Use `;` for frames in 29.97/59.94fps, `:` for all others
- **Clip Names Truncated**: Enable "32-character clip names" option for Premiere Pro

## Support
For issues or feature requests, visit the [World Graph Studio GitHub repository](https://github.com/worldgraph).

## External References
- [Unreal Engine EDL Import/Export](https://dev.epicgames.com/documentation/unreal-engine/import-and-export-edl-in-unreal-engine?lang=en-US)
- [Premiere Pro EDL Export](https://helpx.adobe.com/premiere/desktop/render-and-export/export-files/export-a-project-as-an-edl-file.html)
- [DaVinci Resolve EDL Workflow](https://drexelcinetv.tech/workflow/davinci-resolve/davinci-edl-workflow/)
- [SMPTE 436m Specification](https://grokipedia.com/page/Edit_decision_list)


