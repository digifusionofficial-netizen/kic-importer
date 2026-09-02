# KIC Importer

Strict WordPress importer for the frozen `KIC-1.0` contract and target `wp-kadence-importer`.

Version 1.4.0 provides a guarded end-to-end workflow. Release ZIPs always use the canonical `kic-importer/kic-importer.php` plugin path so WordPress replaces an existing installation instead of creating a second plugin entry. Media references are normalized relative to their source HTML/CSS file, including safe `../` and Windows paths, before block JSON, HTML, srcset, CSS and SEO values are rewritten.

1. secure ZIP extraction with traversal, size, file-count, and prohibited-file checks;
2. strict KIC-1.0 manifest, document, component, media, CSS/JS, SEO, link, form, and placeholder preflight;
3. a Kadence-independent `SiteSchema`;
4. a version-gated adapter that emits editable WordPress blocks without guessing undocumented Kadence attributes;
5. Media Library import, draft page creation/update, SEO metadata, menus, global patterns, logs, and rollback;
6. Tools > KIC Importer UI, REST status/report endpoints, and WP-CLI validation/import commands.

## Styling pipeline

The importer parses `global.css`, `components.css`, and `responsive.css` into a neutral stylesheet model. Manifest tokens are validated against `global.css`, global colors and typography are mapped to WordPress Global Styles when the active theme exposes a writable record, and supported element rules are resolved by selector/cascade for desktop, tablet, and mobile.

The adapter emits editable Kadence Row Layout, Section/Column, Advanced Text, Advanced Button/Single Button, Advanced Image, Accordion and Pane blocks, plus native Gutenberg paragraphs, lists and navigation and the editable KIC form block. Media URLs are rewritten once from source paths to permanent Media Library URLs across page blocks, synced patterns, srcset, CSS backgrounds and Open Graph metadata. Imported source classes are prefixed and the three required source stylesheets are sanitized, combined and permanently scoped to `kic-site-{import_id}`. That stylesheet loads only for pages belonging to its import and preserves media queries and properties that do not yet have native mappings. The per-import `style_report` separates native mappings, fallback rules and sanitizer actions; prohibited executable CSS is rejected rather than discarded.

The automated suite includes complete Pine & Pipe and ClearPath KIC-1.0 packages as styling regression fixtures.

SVG files currently fail preflight with an actionable error before WordPress content or media is modified. This remains fail-closed until an audited SVG sanitizer is included.

Unknown Kadence versions fail closed. The plugin never guesses block attributes.

Requirements: WordPress 6.5+, PHP 8.0+, DOM and ZIP extensions, and Kadence Blocks 3.x or 4.x. Unknown Kadence major versions fail closed.

## Development

Install development dependencies and run tests:

```sh
composer install
composer test
```

Build a distributable ZIP with the repository's `build.ps1` script on Windows.
