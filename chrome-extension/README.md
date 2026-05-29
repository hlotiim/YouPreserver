# Instagram Profile All Highlight With Meta Data Downloader As A Zip

Instagram Profile All Highlight With Meta Data Downloader As A Zip is a utility module designed to download and preserve Instagram profile highlight media along with structured metadata in a single organized ZIP file. It helps users archive highlight stories, media files, captions, timestamps, cover details, and related profile information in a clean, portable format.

This module is useful for developers, creators, agencies, researchers, and digital archivists who need a structured way to back up Instagram highlight content for authorized accounts, personal archives, content migration, portfolio preservation, or social media data management workflows.

## What This Module Does

This module collects Instagram profile highlight media and exports everything as a downloadable ZIP package. The ZIP can include media files, metadata files, profile information, highlight group details, and structured JSON data for future reuse.

Instead of saving Instagram highlight content manually one by one, this module automates the preservation process and keeps the downloaded data organized by highlight name, media type, and metadata structure.

## Key Features

- Download Instagram profile highlights
- Export all highlight media as a ZIP file
- Preserve metadata with each highlight item
- Save structured JSON data for developers
- Organize files by highlight group
- Support images and videos
- Include timestamps, media IDs, cover data, and profile metadata when available
- Useful for Instagram backup, content archiving, and migration workflows
- Developer-friendly output structure
- Easy to integrate into larger automation systems, WordPress plugins, dashboards, or archive tools

## Use Cases

- Backup your own Instagram story highlights
- Preserve creator or brand highlight content
- Archive social media content before account changes
- Move Instagram highlight assets into another website or CMS
- Build a personal Instagram archive page
- Create a local backup of highlight media and metadata
- Store social proof, testimonials, event stories, product stories, or campaign highlights
- Use downloaded metadata for analytics, content organization, or profile reconstruction

## Why This Module Is Useful

Instagram highlights are often valuable because they contain long-term profile content such as testimonials, events, product showcases, travel memories, client work, offers, FAQs, and brand stories. Manually saving this content is slow and unorganized.

This module solves that by downloading highlight media and metadata together, then packaging everything into a ZIP file that is easy to store, transfer, inspect, or import into another system.

## Output Structure

The generated ZIP file is structured like this:

```text
ipa-highlights-username-2026-05-28.zip
│
├── highlights.json
│
└── files/
    ├── covers/
    │   ├── {highlight-id}.jpg
    │   ├── {highlight-id}.webp
    │   └── ...
    │
    └── media/
        ├── {story-id}.jpg
        ├── {story-id}.mp4
        ├── {story-id}_thumb.jpg
        └── ...
```

### `highlights.json`

Central manifest with profile and highlight metadata:

| Field | Description |
|-------|-------------|
| `version` | Export format version |
| `format` | Export format identifier (`ipa-highlights-export`) |
| `exported_at` | ISO timestamp of export |
| `username` | Instagram username |
| `user_id` | Instagram user ID |
| `highlights[]` | Array of highlight groups |

Each highlight entry includes:

| Field | Description |
|-------|-------------|
| `id` | Highlight ID |
| `title` | Highlight name |
| `sort_order` | Position on profile |
| `cover` | Relative path to cover image in `files/covers/` |
| `stories[]` | Story items with `id`, `media_type`, `file`, `thumb`, `posted_at` |

Media files live under `files/covers/` and `files/media/`. All paths in JSON are relative to the ZIP root, so highlights are grouped logically in metadata while files stay organized by type.

## Install (Chrome, unpacked)

1. Clone or download this repository
2. Open `chrome://extensions`
3. Enable **Developer mode**
4. Click **Load unpacked**
5. Select this folder (the directory containing `manifest.json`)

## Use

1. Log in to Instagram in Chrome
2. Open the Instagram profile whose highlights you want to export
3. Click the **YouPreserver Highlights** extension icon → **Start**
4. Wait for the ZIP download to complete

## WordPress import (YouPreserver)

Upload the exported ZIP in the [YouPreserver](https://roktimsaha.com) WordPress plugin:

**WordPress admin → YouPreserver → Highlights → Import Highlights ZIP**

The plugin validates the manifest, imports media into the WordPress Media Library, and displays highlights on your public gallery page. Compatible with YouPreserver plugin v1.3.0+.

## Requirements

- Google Chrome (Manifest V3)
- Active Instagram login session
- Authorized access to the profile you are exporting

## License

MIT — see [LICENSE](LICENSE).

## Author

Built by [Roktim Saha](https://roktimsaha.com)
