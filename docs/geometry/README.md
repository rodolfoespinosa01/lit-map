# New Jersey county geometry — Milestone 2

Preview: https://rutgers-lit-m1.ddev.site/lit-map/geometry-preview

This is a geometry preview, not the final LiT map. Its four highlighted counties
(Bergen, Hudson, Morris, Essex) are a style/geography check, not survey results.
The checked-in SVG is static and is rendered inline by Twig. No browser mapping
library, JavaScript, frontend build pipeline, database schema, or survey integration
was added. MariaDB remains 11.8.9 in the existing DDEV 11.8 configuration.

## Official source and reuse

- Publisher: U.S. Census Bureau.
- Starting page: https://www.census.gov/geographies/mapping-files/2025/geo/carto-boundary-file.html
- Download: https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_us_county_500k.zip
- Product: 2025 Cartographic Boundary File (SHP), County and Equivalent for
  United States, **1:500,000**. Vintage: **2025**; source metadata says county
  boundaries are based on those as of **January 1, 2025**.
- Retrieved November 13, 2026. Archive SHA-256:
  `aa976c00b181939755d0da757f4c7c2dc0103c3b3b4530fb2a91c2bb62fc777c`.
- Original archive metadata is retained verbatim in
  [census-source-metadata.xml](census-source-metadata.xml).

The dataset's own metadata says access is unrestricted and permits reuse in
products/publications with acknowledgment of the U.S. Census Bureau. It specifies
small-scale display at 1:500,000 or smaller, and excludes precise geographic
analysis, area/perimeter calculations, geocoding, and precise boundary relationship
determinations. These are generalized cartographic boundaries, not survey-grade
or legal boundary determinations. Attribution is visible below the preview and
embedded in the SVG description. See also the Census
[citation policy](https://www.census.gov/about/policies/citation.html).

The archive is cached outside `web/`, ignored by Git, and downloadable again from
the recorded URL. Its checksum is enforced before conversion; a changed archive
requires deliberate review rather than silently accepting different coordinates.

## Conversion and projection

`tools/geometry/convert.py` reads the SHP/DBF/PRJ directly from the ZIP with PyShp,
selects `STATEFP == '34'`, and sorts the 21 source records by GEOID. No geographic
coordinates are invented or hand-edited.

The source PRJ identifies NAD83 geographic coordinates (EPSG:4269). pyproj/PROJ
transforms them to **NAD83 / New Jersey (EPSG:32111)**, a metre-based State Plane
Transverse Mercator projection designed for New Jersey. A single uniform scale
fits the projected extent into the SVG, with north up (SVG's y axis is reversed)
and 32 units of padding. This preserves projected geographic proportions; it
does not independently stretch longitude and latitude. As with all planar maps,
projection distortion exists, but this state-specific projection minimizes it locally.

Every source ring and every vertex is retained. No extra simplification, clipping,
repair, dissolve, island removal, or geometry merging is performed. One compound
SVG path represents each county; `fill-rule="evenodd"` supports holes as well as
multipart geometry. Hudson and Salem each retain two polygon parts. SVG coordinate
rounding is to 0.001 display unit (less than 0.26 metre worst-case planar positional
rounding here), far below this source's intended cartographic detail. Shared
coordinates use the same transformation and rounding.

Labels use Shapely's interior representative point of each county's largest
polygon. This chooses a display label location, without changing any boundary
or removing smaller parts. All names also appear in a readable HTML reference
list; SVG labels become small on narrow screens. Geometry part/vertex totals in
the conversion report are QA metrics, not survey counts.

Tool versions used:

| Tool | Version |
| --- | --- |
| Python | 3.11.9 |
| PyShp | 2.3.1 |
| pyproj / PROJ | 3.7.2 / 9.5.1 |
| Shapely / GEOS | 2.1.2 / 3.13.1 |
| NumPy | 2.3.5 |

Python dependencies, including certifi, are pinned in
`tools/geometry/requirements.txt`. These tools are development-only, installed in
an ignored project virtual environment; no system PHP, MAMP, or host database
changes are needed. References: [PyShp](https://github.com/GeospatialPython/pyshp),
[pyproj Transformer](https://pyproj4.github.io/pyproj/stable/api/transformer.html),
[PROJ Transverse Mercator](https://proj.org/en/stable/operations/projections/tmerc.html),
[Shapely representative points](https://shapely.readthedocs.io/en/stable/manual.html#object.representative_point).

From the project root, reproduce with:

```sh
python3 -m venv tools/geometry/.venv
tools/geometry/.venv/bin/pip install -r tools/geometry/requirements.txt
mkdir -p tools/geometry/source
curl -fL --retry 2 https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_us_county_500k.zip -o tools/geometry/source/cb_2025_us_county_500k.zip
tools/geometry/.venv/bin/python tools/geometry/convert.py
tools/geometry/.venv/bin/python tools/geometry/convert.py --check
ddev drush cr
```

`--check` regenerates in memory and compares every output byte-for-byte, without
writing files. The report records actual native-library and Python versions, so
matching that report also requires the recorded environment. A newer environment
must be reviewed and reverified before updating generated outputs.

## Assets and identifiers

- SVG: `web/modules/custom/lit_map/templates/geometry/nj-counties.svg`.
- Source-derived county metadata: `web/modules/custom/lit_map/data/nj-counties.json`.
- Complete identifier table: [county-identifiers.csv](county-identifiers.csv).
- Provenance, tool versions, geometry totals and hashes:
  [conversion-report.json](conversion-report.json).

Each path has `id="county-nj-bergen"`, `data-county-key="nj-bergen"`,
`data-geoid="34003"`, and `data-county-name="Bergen"` (example). Keys use
`nj-` plus a lowercase hyphenated source county name and are fixed for this asset.
GEOIDs are five-character strings copied directly from the Census DBF; they
equal `STATEFP` + `COUNTYFP`, preserving leading zeros. Prefer GEOID for joins.
Retain this mapping if future labels change. No taxonomy entities are created.

The SVG has a responsive `viewBox="0 0 464.231 824"`, no fixed pixel dimensions,
and `preserveAspectRatio="xMidYMid meet"`. Fills and borders are controlled by
`css/geometry.css`; preview-only highlights are in `css/geometry-preview.css`.
The reusable geometry library exposes `--lit-county-fill`, `--lit-county-border`,
`--lit-county-border-width`, and `--lit-label-color`. Labels can be hidden through
the `.lit-map-labels` class for a later rendering context.

Twig includes the fixed repository asset with
`{% include '@lit_map/geometry/nj-counties.svg' only %}`. Do not replace this with
untrusted uploaded SVG or a user-controlled include path. A later page containing
multiple inline copies must namespace DOM IDs per instance; stable county keys
and GEOIDs should remain unchanged. Milestone 2 displays one instance only.

## Preview integration and verification

The module uses a normal controller render array, `hook_theme()`, Twig, Drupal
library attachments and render caching. The route requires `access content` and
is available anonymously on the local test host. There was no prior rendering
implementation to preserve. The preview is development scaffolding to review
before any future production integration; no deployment was performed.

See [verification.md](verification.md) for checks and screenshots. Optional browser
QA uses the Mac's existing Chrome and a temporary Playwright Core installation:

```sh
npm install --prefix /private/tmp/lit-geometry-qa playwright-core@1.58.2 --no-audit --no-fund
node tools/geometry/check-preview.cjs
```

This is a test driver only, with no shipped JavaScript or npm build step. Use
`LIT_PLAYWRIGHT_PATH` to select another installed Playwright Core location.
