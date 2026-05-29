# YouPreserver

Open-source social media profile preservation codebase for backing up profile media, posts, stories, highlights, metadata, and archive pages for authorized accounts.

YouPreserver is an open-source **social media backup tool** and **profile archive codebase** that helps you **preserve Instagram profile** content on your own website or storage system. It combines a WordPress plugin for syncing and displaying an **Instagram-style profile archive**, plus a Chrome extension for **highlight archive workflow** and **Instagram ZIP export** of highlight media and metadata. It is built for **authorized Instagram backup**, **personal profile backup**, and **digital content preservation** — not for accessing accounts without permission.

![Status](https://img.shields.io/badge/status-active-brightgreen)
![Project](https://img.shields.io/badge/project-open--source-blue)

<!-- Add a license badge after choosing a license -->

## What is YouPreserver?

YouPreserver is an open-source **social media preservation tool** and **profile preservation system** for backing up and displaying profile content from accounts you own or manage. The project includes a WordPress plugin that connects to the Instagram API through authorized OAuth, syncs posts and reels, downloads media locally, and publishes an **Instagram-style profile archive** on your own domain. It also includes tools for importing highlight stories from structured ZIP exports. YouPreserver is designed for **authorized accounts only** and helps developers and creators build a **custom domain profile archive**, **metadata export tool**, and **media backup system** they control.

## Why I Built YouPreserver

A lot of my life is on Instagram — milestones, memories, stories, highlights, photos, and profile history. If a platform closes an account, restricts access, changes policies, or I simply lose access one day, years of content could disappear.

I built YouPreserver because I did not want years of milestones and memories to live only inside one platform. I wanted a practical way to **preserve Instagram profile** content on my own domain, with my own media, my own archive, and my own backup system.

The first version was built quickly as a practical preservation experiment. The goal was not to create a SaaS. The goal was to solve my own problem and open-source the system for other people who may need the same thing.

Built by **Roktim Saha** — [https://roktimsaha.com](https://roktimsaha.com)

## Engineer Behind YouPreserver

YouPreserver was built by **Roktim Saha**, an entrepreneur and developer who builds web products, automation tools, AI systems, and open-source experiments.

Website: [https://roktimsaha.com](https://roktimsaha.com)

## Key Features

- Preserve social media profile content from authorized accounts
- Backup profile photos, posts, photos, videos, and reels via Instagram API sync
- Build an **Instagram-style profile archive** page on your WordPress site
- Save media files locally in WordPress uploads
- Export and import structured metadata
- Support JSON-based archive data for posts, reels, and highlights
- Support **Instagram ZIP export** and highlight import workflows
- Chrome extension for highlight media and metadata export
- Public gallery page with grid, reels tab, and highlights row
- Full-screen viewer for posts, reels, and highlight stories
- Manual pinned post management
- Dynamic gallery title and SEO meta description
- Scheduled sync support via WordPress cron
- Developer-friendly PHP and JavaScript codebase
- Can be adapted into WordPress plugins or custom websites
- Designed for **authorized accounts only**
- Useful for **personal profile backup** and **creator media backup**
- Can be extended for additional storage adapters and archive workflows

## Use Cases

- Personal **Instagram profile backup**
- **Preserve Instagram profile on your own domain**
- **Instagram-style archive page** for a creator or brand
- **Social media content preservation** and digital memory backup
- Creator portfolio backup
- Brand media archive
- Social proof and testimonial preservation
- Event story backup
- **Highlight archive workflow**
- **Social media migration** to your own site
- Offline ZIP backup of highlights
- Developer archive tools and automation
- WordPress plugin backend for profile mirrors
- **Custom profile mirror page**
- **Digital content preservation** for long-term access

## How It Works

1. Install the YouPreserver WordPress plugin on your site.
2. Connect your Instagram account through authorized Meta / Instagram OAuth.
3. Sync profile data, posts, and reels through the configured API connection.
4. Download media files locally to your WordPress uploads directory.
5. Store captions, timestamps, permalinks, and media metadata in the WordPress database.
6. Import highlight stories from a structured ZIP export using the Chrome extension or compatible export file.
7. Display preserved content on a public archive page — for example `/gallery/` — in an Instagram-style layout on your own domain.

## Example Archive Output

```text
youpreserver-archive/
├── profile.json
├── posts.json
├── highlights.json
├── stories.json
├── media/
│   ├── photos/
│   ├── videos/
│   └── thumbnails/
├── highlights/
│   ├── travel/
│   │   ├── metadata.json
│   │   ├── cover.jpg
│   │   └── media/
│   └── work/
│       ├── metadata.json
│       ├── cover.jpg
│       └── media/
└── export.zip
```

Highlight ZIP exports from the related Chrome extension follow a compatible manifest structure for import into YouPreserver.

## Metadata You Can Preserve

- Profile username
- Display name
- Bio
- Profile image
- Media ID
- Caption
- Media type
- Timestamp
- Permalink
- Thumbnail URL
- Local file path
- Highlight name
- Story order
- Cover image
- Raw JSON data when available

Metadata availability depends on the source, API, export file, or import method used.

## Related Module

**Instagram Profile All Highlight with Metadata Downloader as a ZIP**

GitHub: [https://github.com/hlotiim/instagram-profile-all-highlight-with-meta-data-downloader-as-a-zip](https://github.com/hlotiim/instagram-profile-all-highlight-with-meta-data-downloader-as-a-zip)

This related module focuses on downloading Instagram profile highlights with media and metadata as an organized ZIP archive. It can be used alongside YouPreserver or independently for authorized highlight backup, structured metadata export, and profile archive workflows.

The `chrome-extension/` folder in this repository contains the Chrome extension used for that highlight export workflow and WordPress ZIP import.

## Installation

Clone the repository:

```bash
git clone https://github.com/hlotiim/YouPreserver.git
cd YouPreserver
```

### WordPress plugin

1. Copy the `instagram-profile-archive/` folder into your WordPress `wp-content/plugins/` directory.
2. Activate **YouPreserver** in the WordPress admin.
3. Open **YouPreserver** in the admin menu and connect your Instagram account.

Requirements:

- WordPress 6.0+
- PHP 7.4+
- HTTPS recommended for OAuth callback

### Chrome extension (highlights ZIP export)

1. Open `chrome://extensions`
2. Enable **Developer mode**
3. Click **Load unpacked**
4. Select the `chrome-extension/` folder

Optional (only if your setup uses Composer or Node tooling elsewhere):

```bash
composer install
```

```bash
npm install
```

## Configuration

Configure the plugin in **WordPress admin → YouPreserver**.

You will need Meta / Instagram app credentials for authorized API access:

```env
INSTAGRAM_APP_ID=
INSTAGRAM_APP_SECRET=
```

These are configured through the WordPress admin connection screen rather than a root `.env` file in most setups.

Example storage-related settings inside WordPress:

```env
APP_URL=https://example.com
STORAGE_PATH=/wp-content/uploads/instagram-archive
SAVE_METADATA=true
DOWNLOAD_MEDIA=true
```

Never commit access tokens, API keys, cookies, session data, or private credentials to GitHub.

## Usage

### WordPress plugin

1. Connect your Instagram account in the admin panel.
2. Run a sync to import posts and reels.
3. Open the public gallery page (default slug: `/gallery/`).
4. Import highlight ZIP files from **YouPreserver → Highlights**.

### Chrome extension

1. Log in to Instagram in Chrome.
2. Open the profile whose highlights you want to export.
3. Click the YouPreserver Highlights extension icon and start export.
4. Upload the downloaded ZIP in WordPress admin.

Replace or extend these steps based on your deployment and workflow.

## Website and WordPress Integration

YouPreserver can be used as a base for:

- WordPress plugins
- **Instagram-style archive pages**
- Private backup dashboards
- Creator portfolio websites
- **Custom profile mirror pages**
- **Media backup systems**
- **ZIP export tools**
- Content migration systems

A common use case is creating a page like:

`/gallery/`

on your own domain, where your preserved profile content is displayed in an Instagram-style archive layout.

## Authorized Use Only

YouPreserver is intended only for preserving content from accounts you own, manage, or have permission to archive.

Do not use this project to access private accounts, bypass platform restrictions, collect data without consent, violate copyright, or break platform terms.

Always respect:

- Platform rules
- Privacy
- Copyright
- Local laws
- Content ownership

## Disclaimer

YouPreserver is not affiliated with, endorsed by, sponsored by, or officially connected to Instagram, Meta, Facebook, or any other social media platform.

All trademarks, names, logos, and platform references belong to their respective owners.

## Roadmap

- Instagram-style archive page improvements
- ZIP export improvements
- Metadata viewer
- WordPress plugin refinements
- Scheduled sync enhancements
- Local media library improvements
- Highlight manager
- Story archive manager
- Multi-profile support
- Cloud storage support
- Import from official platform data exports
- Search and filter archive content
- Better archive UI components

## FAQ

### What is YouPreserver?

YouPreserver is an open-source social media backup and profile archive codebase for preserving authorized profile media, metadata, and Instagram-style archive pages on your own website.

### Why was YouPreserver built?

It was built because years of milestones, memories, stories, highlights, and photos should not depend on a single platform. The creator wanted a personal backup and archive system on his own domain.

### Who built YouPreserver?

YouPreserver was built by Roktim Saha — [https://roktimsaha.com](https://roktimsaha.com)

### Can I use YouPreserver to back up my Instagram profile?

Yes, for accounts you own or are authorized to manage, using the configured Instagram API connection and import tools.

### Can I preserve Instagram photos and videos?

Yes. The WordPress plugin is designed to sync and store photos and videos locally for authorized accounts.

### Does YouPreserver support stories and highlights?

It is designed for story and highlight archive workflows. Posts and reels sync through the Instagram API. Highlights can be imported from structured ZIP exports produced by the related Chrome extension. Availability depends on your data source, export method, and API access.

### Can this create an Instagram-style profile archive page?

Yes. YouPreserver includes a public gallery page with profile header, posts grid, reels tab, highlights row, and a full-screen viewer.

### Is YouPreserver an official Instagram tool?

No. YouPreserver is an independent open-source project and is not an official Instagram or Meta product.

### Can developers use this with WordPress?

Yes. The main implementation is a WordPress plugin that can be installed, configured, and extended on your own site.

### What is the related highlight ZIP downloader module?

It is a companion Chrome extension and open-source module for exporting highlight media and metadata as ZIP:

[https://github.com/hlotiim/instagram-profile-all-highlight-with-meta-data-downloader-as-a-zip](https://github.com/hlotiim/instagram-profile-all-highlight-with-meta-data-downloader-as-a-zip)

### Is this for authorized accounts only?

Yes. Only use YouPreserver for accounts you own, manage, or have explicit permission to archive.

### Why should someone preserve their social media profile?

Social profiles often contain years of personal history, creator work, brand content, and public memories. Preserving that content on your own domain gives you a backup you control if platform access changes.

## Keywords

social media backup tool, Instagram archive tool, Instagram profile backup, Instagram highlights downloader, Instagram stories backup, Instagram media downloader, Instagram ZIP export, Instagram profile archive, social media archive system, social media preservation tool, profile preservation system, profile archive codebase, backup Instagram photos and videos, preserve Instagram profile, archive Instagram profile on website, Instagram-style profile archive, social media data export, metadata export tool, digital content preservation, creator media backup, authorized Instagram backup, personal profile backup, open-source social media archive, media backup system, story archive workflow, highlight archive workflow, profile mirror page, custom domain profile archive.

## Contributing

Contributions are welcome.

People can help improve:

- Documentation
- Storage adapters
- ZIP export logic
- WordPress integration
- UI components
- Metadata handling
- Archive workflows
- Import/export systems
- Security and privacy improvements

## License

The WordPress plugin in `instagram-profile-archive/` is licensed under **GPL-2.0-or-later**.

The Chrome extension in `chrome-extension/` is licensed under **MIT** (see `chrome-extension/README.md`).

## Summary

YouPreserver is an open-source social media backup and profile archive project created by Roktim Saha for authorized content preservation, metadata export, and Instagram-style archive pages on your own domain. It helps you backup Instagram photos and videos, import highlight archives, and build a profile mirror page you control. Use it responsibly, only for accounts you own or are permitted to archive.
