# Changelog

## 1.9.0 — 2026-10-23

- New Canvas block (`anchor/canvas`) for custom coded areas in posts. HTML/SVG, CSS and optional JS live in attributes and render into a declarative shadow root, so the code's styles cannot leak into the page or the page's into it, while inherited tokens (`--ab-*` and the theme's variables) keep it in step with light and dark mode. `:host` is a size container, so layouts can switch with `@container` queries
- Canvas fallback image: shown in email (the `wp_mail` filter strips the live code and adds a "View the interactive version on the web" link) and RSS, and anywhere the shadow root does not attach, such as Minn Admin's island preview
- Canvas code renders only when the post author has `unfiltered_html`; otherwise the block falls back to its image and caption
- Canvas in the editor: live shadow-root preview with a Code/Preview toolbar toggle, frame and caption settings, and a fallback image picker. Minn Admin gets an insert template and textarea controls for the code attributes

## 1.8.1 — 2026-08-22

- Timeline: pin `box-sizing: border-box` on the item dot pseudo-element. Themes whose reset targets only `*` (which never matches pseudo-elements) rendered the 12px dot as 16px including its ring border, centering it 2px right of the rail

## 1.8.0 — 2026-08-22

- Dark mode across all 14 blocks. Every surface, border and text colour is now a token instead of a hardcoded hex, and each token reads the host theme's variable first (`--ab-surface: var(--surface, #fff)`), so blocks adopt the theme's palette and follow its light/dark switching. On a theme that publishes no such variables the fallbacks apply and rendering is unchanged
- Blocks are self-sufficient in dark mode: a `prefers-color-scheme: dark` block plus an `html[data-theme="dark"]` override supply dark fallbacks, tinted callout/badge tones and lightened text-accents, so dark mode works even without a cooperating theme
- The editor canvas is pinned to the light palette in `editor.css` (loaded after `style.css`), so an author on a dark-mode OS still edits against the light admin chrome
- Contrast fix: the user-message label in Conversation now uses `--ab-anchor-text` (#2b8fc7) rather than the brand cyan #55c1e7, which failed contrast on a light surface

## 1.7.0 — 2026-07-04

- Minn Admin integration (`app/MinnAdmin.php`): block-inspector form descriptors for all 13 blocks via the `minn_admin_block_forms` filter — friendly labels, a User/Assistant role select, textarea controls, real color palettes (callout: blue/green/red/yellow), and an editable conversation header (`wrapperText`). Slash-menu insert templates for Conversation, Timeline, Callout, Stats Dashboard and Bar Chart. A no-op when Minn Admin isn't installed

## 1.6.0 — 2026-06-23

- Data Table block (`data-table`): a single server-rendered monospace data table. Title, columns, per-column alignment, rows, and an optional highlighted row all live in block attributes (authored via the post generator). Includes an email-safe inline-style rebuild

## 1.5.0 — 2026-06-22

- Term List block (`term-list` / `term`): a single card listing terms, each with a large color-keyed display label, a bold title, and a rich-text body. Includes an email-safe single-card rebuild

## 1.4.0 — 2026-06-17

- Vector Cards block (`vector-cards` / `vector-card`): a responsive grid of attack-method cards with a colored label badge, color-keyed top accent, how-it-works body, and a "Detect" footer. Includes an email-safe stacked-card rebuild
- Report Card tag now supports a `gray` color

## 1.3.0 — 2026-06-14

- Indicators of Compromise block (`ioc-list` / `ioc-row`) with colored type pills, monospace values, notes, and an email-safe table rebuild
- Fix Stats Dashboard grid layout inside the block editor iframe

## 1.2.0 — 2026-04-09

- Self-hosted updater for wordpress.org takeover protection

## 1.1.0

- Report Card block with tag, title, count header and inner block content
- Bar Chart block with colored horizontal bars and percentage widths

## 1.0.0

Initial release.

- Conversation block with user/assistant message roles
- Timeline block with color-coded dots and dates
- Callout block in blue, red, green, yellow styles
- Stats Dashboard with stat card grid
- Email-safe inline styles for all blocks via `wp_mail` filter
