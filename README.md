# Anchor Blocks

Custom Gutenberg blocks for [Anchor Hosting](https://anchor.host) blog posts.

## Blocks

- **Conversation** — Chat-style message thread with user/assistant roles
- **Timeline** — Color-coded vertical timeline with dates
- **Callout** — Styled alert box (blue, red, green, yellow)
- **Stats Dashboard** — Grid of stat cards with colored values
- **Bar Chart** — Horizontal bar chart with labels and percentages
- **Report Card** — Tagged card with header and inner block content
- **Indicators of Compromise** — Scannable list of IOCs (domains, IPs, file hashes, paths) with colored type pills
- **Vector Cards** — Grid of attack-method cards with a label badge, how-it-works body, and a "Detect" footer
- **Term List** — Single card listing terms, each with a large color-keyed label, a title, and a description
- **Data Table** — Clean monospace data table with a title, per-column alignment, and an optional highlighted row

All blocks are server-side rendered to avoid block validation errors across updates. Email-safe inline styles are automatically applied when blocks appear in outgoing `wp_mail` messages.

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

Download the latest release zip from [GitHub Releases](https://github.com/anchorhost/anchor-blocks/releases) and install via **Plugins > Add New > Upload Plugin**.

## Updates

The plugin checks for updates from this repository automatically. When a new version is available it will appear in the WordPress dashboard like any other plugin update.

## License

MIT
