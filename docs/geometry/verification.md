# Milestone 2 verification — November 13, 2026

Local preview: https://rutgers-lit-m1.ddev.site/lit-map/geometry-preview

## Source and asset checks

- Official Census 2025 county ZIP downloaded successfully from the URL recorded
  in [README.md](README.md); SHA-256 verified before parsing.
- Exactly **21** New Jersey source records selected using `STATEFP=34`.
- Exactly **21 unique path IDs**, **21 unique county keys**, and **21 unique GEOIDs**.
  Names and GEOIDs are copied from and verified against the source DBF records.
  [county-identifiers.csv](county-identifiers.csv) contains the complete mapping.
- All **23 rings** and **7,987 source vertices** retained in the SVG. Hudson and
  Salem each retain two polygon parts. All projected county geometries are valid.
- EPSG:4269 to EPSG:32111 projection, followed by a single uniform display scale;
  no independent axis stretching or additional boundary simplification.
- XML element/attribute allowlist passed: no scripts, event-handler attributes,
  external references, remote assets, foreignObject, image, use, style blocks,
  external entities, or embedded data-loading code. The SVG namespace URL is
  an XML identifier, not a remote asset request.
- SVG contains geographic paths, labels, identifiers and attribution only;
  no survey observations or counts. Geometry styling lives in CSS.
- `convert.py --check` passed: SVG, identifier JSON/CSV, provenance report and
  source metadata reproduce byte-for-byte from the pinned input.
- Static SVG size: **141,897 bytes** (uncompressed).

## Drupal and browser checks

- PHP syntax checks passed for the module hook and preview controller.
- Drupal cache rebuild succeeded, discovering the route, theme hook and libraries.
- Preview HTTP response **200** over HTTPS, with normal certificate validation.
- Existing Drupal **10.6.21** bootstraps successfully; `lit_map` remains enabled.
- MariaDB remains **11.8.9-MariaDB-ubu2404-log**. No database engine configuration,
  schema updates, survey tables, Composer dependencies or DDEV settings changed.
  Normal Drupal cache writes occurred during route/theme registration.
- Paragraph type count remains **0**; taxonomy module remains disabled.
- Browser QA used Playwright Core **1.58.2** with the Mac's installed Google Chrome.
  Tested viewport widths: **1280, 390, 320 pixels**. Each passed:
  - 21 counties, labels and unique identifiers in the actual rendered DOM.
  - Exactly Bergen, Essex, Hudson and Morris have the preview highlight fill.
  - SVG aspect ratio matches its viewBox; no horizontal page overflow.
  - No overlapping or clipped SVG label bounds.
  - No JavaScript page errors or remote requests.

Full numeric results: [browser-checks.json](browser-checks.json).

## Visual inspection

Rendered Chrome screenshots were actually opened and visually inspected at
desktop and mobile sizes. New Jersey's northwestern corner, northeastern counties,
Atlantic coast and Cape May peninsula are recognizable. County boundaries remain
intact, and white borders separate the highlighted counties. The desktop layout
places the reference list beside the map; mobile stacks it below without distorting
the state or clipping the map. Labels are small at mobile scale, so the separate
HTML list provides readable names and identifiers. Small polygon parts are retained
mathematically; their visibility at a particular screen scale is naturally limited
by their cartographic size.

- [Desktop screenshot, 1280px](screenshots/preview-1280.png)
- [Mobile screenshot, 390px](screenshots/preview-390.png)
- [Narrow mobile screenshot, 320px](screenshots/preview-320.png)

No Milestone 2 blockers remain. Browser verification is limited to Chrome;
cross-browser and Drupal 11 testing remain separate future work. Existing
Milestone 1 support-lifecycle/local-settings warnings still apply.

## File inventory

Updated: root `README.md`, `.gitignore`, module `lit_map.info.yml`.

Added under `web/modules/custom/lit_map/`:

- `lit_map.module`, `lit_map.routing.yml`, `lit_map.libraries.yml`
- `src/Controller/GeometryPreviewController.php`
- `templates/lit-map-geometry-preview.html.twig`
- `templates/geometry/nj-counties.svg`
- `data/nj-counties.json`
- `css/geometry.css`, `css/geometry-preview.css`

Added conversion/QA tools: `tools/geometry/convert.py`, `requirements.txt`,
`check-preview.cjs`. The downloaded ZIP and Python virtual environment are ignored
development artifacts. The temporary Playwright installation is outside the project.

Added documentation/evidence in `docs/geometry/`: README, this verification report,
source XML metadata, identifier CSV, conversion JSON report, browser JSON results,
and three screenshots.

No Git repository was present at inspection; no repository was initialized,
committed or pushed. Existing Milestone 1 work was preserved. No filtering,
imports, taxonomies, media integration, final Paragraph, deployment or publication
was performed.
