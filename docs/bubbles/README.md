> Historical Milestone 4 record. The bubble route now uses the informational full-file dataset and distinct-response unions. The old active-language/fixture-refresh browser tests were retired; use [current component verification](../component/verification.md) and [dataset instructions](../dataset.md).

# Milestone 4 — synthetic bubble preview

Local route: <https://rutgers-lit-m1.ddev.site/lit-map/bubble-preview>.
The geometry and interaction previews from Milestones 2–3 remain available and retain their original fixtures. This is an anonymous development preview, not a finished Paragraph or a statement of real language prevalence.

## Try it

1. Open the route. New Jersey appears with no language or county selected.
2. Check Spanish and English. Use **Bubble size: active language** to switch the language encoded by area; the panel keeps both counts separate.
3. Select Bergen from the county selector or map. Enable **Show towns/cities**. The 70 Bergen municipalities load from one static SVG.
4. Choose Allendale borough, or select a bubble. A leader connects the selected town's anchor to the callout; the panel shows its exact per-language counts. Other bubbles remain visible but subdued. Use the selector/list to reach overlapping bubbles.
5. Choose Municipality unspecified. Those responses appear in the panel and county total, without an invented location. Turn off Show towns/cities to clear the town filter.
6. Use **Zoom +**, drag to pan, and **Fit current view**. Zoom is limited to 8× the current extent. Arrow buttons/keys pan; +/− zoom; Home fits the current view when the map region has focus. Enter/Space activate focused map features.
7. Use **Back to New Jersey**, then **USA overview**. Select highlighted NJ or use Back to New Jersey to return. Alaska and Hawaii are explicit insets; no interpolation pretends their inset positions are geographic locations.
8. Refresh and use browser Back/Forward. Selections persist. **Reset selections** returns to NJ with no filters. **Gentle motion** is saved locally.
9. On mobile, normal page scrolling works over the map. Zoom buttons work without gesture mode. To drag the map, enable **Move map with touch**; turn it off to resume scrolling over the map. There is no wheel capture or custom pinch gesture.

## Official geography and licensing

Starting catalog: <https://www.census.gov/geographies/mapping-files/2025/geo/carto-boundary-file.html>.

| Asset | Official ZIP | Scope |
| --- | --- | --- |
| Counties (existing M2 asset, unchanged) | <https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_us_county_500k.zip> | 21 NJ counties, 1:500,000 |
| Municipalities | <https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_34_cousub_500k.zip> | All 564 NJ county subdivisions, 1:500,000 |
| USA overview | <https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_us_state_5m.zip> | 50 states + DC from 56 source records, 1:5,000,000; territories omitted |

NJ municipalities are minor civil divisions (MCDs), represented by Census **county subdivisions**, not Census-designated places. Census explains NJ's functioning MCD governments in its [population estimates FAQ](https://www.census.gov/programs-surveys/popest/about/faq.html). See also the [2025 TIGER/Line technical documentation](https://www2.census.gov/geo/pdfs/maps-data/data/tiger/tgrshp2025/TGRSHP2025_TechDoc.pdf). The 564 count is verified directly against this vintage's shapefile, not assumed from an older municipality list.

U.S. Census Bureau federal boundary data is freely reusable; acknowledge the Bureau as the source. The source ZIPs' original ISO metadata is retained here, including access/use constraints and citation details. These generalized cartographic products are for small-scale mapping, not legal boundaries or precision measurement. The metadata specifies intended display scales of 1:500,000 (NJ) and 1:5,000,000 (USA), and advises against larger-scale/precise geographic uses. Preview zoom magnifies this generalized drawing; it does not provide additional geographic accuracy. They contain no survey counts. See `geography-report.json` for source SHA-256 digests, counts, sizes, and tool versions; the identifier CSVs preserve original Census names/GEOIDs.

NJ source coordinates are NAD83 (EPSG:4269), projected to **NAD83 / New Jersey (EPSG:32111)**, with exactly the same proportional screen transform as the verified M2 county SVG. County geometry is never rewritten by this conversion. All source polygon rings/vertices, including multipart geometry, are retained. No independent polygon simplification is performed: Census's shared generalized geometry is transformed identically and rounded to .001 SVG units. This avoids introducing polygon-specific simplification cracks. Different generalized products may have small edge discrepancies; these are not cadastral boundaries.

USA lower 48 + DC use **NAD83 / Conus Albers (EPSG:5070)**. Alaska uses **EPSG:3338**. Hawaii uses Albers (`+proj=aea +lat_1=8 +lat_2=18 +lat_0=13 +lon_0=-157 +datum=NAD83 +units=m +no_defs`). Alaska/Hawaii are separate, labeled, rescaled insets; all their source parts remain present. USA/NJ switches are discrete view changes, not a geographic morph across incompatible inset transforms.

Interior anchors use Shapely `polylabel` on the largest polygon part (5 metre tolerance), then verify the rounded anchor remains strictly inside its feature. Municipality anchors must also lie inside their named county. Original GEOID is the authority: county key/GEOID mapping stays in `data/nj-counties.json`; municipal keys are `m` + ten-digit Census GEOID; state keys are postal abbreviations with two-digit GEOID. Municipal names use Census NAMELSAD to disambiguate legal names such as township/borough. `municipality-identifiers.csv` records all 564 keys, county GEOIDs, names, rings, and vertices. `state-identifiers.csv` records all 51 states/DC and inset memberships.

## Reproduce assets and synthetic records

From the project root, use the existing development virtual environment. It is not a browser dependency or frontend build pipeline. Python 3.11.9, pyshp 2.3.1, pyproj 3.7.2 (PROJ 9.5.1), Shapely 2.1.2 are recorded by the generator; Python dependencies are pinned in `tools/geometry/requirements.txt`.

```sh
python3.11 -m venv tools/geometry/.venv
tools/geometry/.venv/bin/pip install -r tools/geometry/requirements.txt
curl -fL --retry 2 https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_us_county_500k.zip -o tools/geometry/source/cb_2025_us_county_500k.zip
curl -fL --retry 2 https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_34_cousub_500k.zip -o tools/geometry/source/cb_2025_34_cousub_500k.zip
curl -fL --retry 2 https://www2.census.gov/geo/tiger/GENZ2025/shp/cb_2025_us_state_5m.zip -o tools/geometry/source/cb_2025_us_state_5m.zip
tools/geometry/.venv/bin/python tools/geometry/bubble-geography.py
python3 tools/geometry/generate-statewide-fixture.py
```

The converter verifies pinned source digests before reading. It writes content-hashed municipality SVG filenames (one per county), a hashed USA SVG, geography metadata, references, identifier CSVs, original source metadata, and its verification report. Keep these generated deliverables in version control; ZIPs and virtualenv remain ignored. `--check` reproduces and compares bytes without writing. Tool versions affect report bytes, so use the documented environment for exact reproduction.

The statewide fixture uses the existing **version 2 aggregate contract** in `../survey-format.md`. It is separate from `survey.synthetic.v2.json`, keeping Milestone 3 examples/tests unchanged. Seed: `rutgers-lit-m4-2026-11-13`. Algorithm: SHA-256 of UTF-8 `seed|key`, first eight bytes interpreted as an unsigned big-endian integer. The script documents exact modulus rules: skip some municipalities entirely, omit some language combinations, deliberately record zeros, otherwise fabricate counts 1–350, plus independent county/language unspecified counts 0–40. The result has **1,791 records**, **27 deliberately unrecorded municipalities**, **151 explicit municipality zeros**, four languages, and records throughout all 21 counties. These are fabricated survey response aggregates, never people or population.

`fixture-report.json` records the seed, deliberate no-data and zero examples, every per-language county total, and unspecified counts. County totals are derived exclusively from municipality + unspecified records. There is no separate total record to double count. The existing PHP normalizer continues rejecting duplicates, unknown references, county/town mismatches, invalid counts and unsafe integer totals. `SurveyDataProvider::loadStatewide()` selects trusted, fixed module paths, independent of any eventual Qualtrics adapter.

## State, display, motion and loading

The PHP `BubblePreviewData` service combines validated public metrics and vetted geometry metadata. Each render has a unique ID and a matching `drupalSettings.litMapBubbles[id]` entry. Twig handles layout; the controller attaches the service's prepared data. Vanilla JavaScript runs through Drupal Behaviors/core once; selectors are component-scoped. The legacy module-owned SVG is inlined through Twig with instance-prefixed path IDs. No visitor-controlled file paths/URLs are accepted.

URL parameters are `lit_lang` (repeated language IDs), `lit_active`, `lit_county` (GEOID), `lit_municipality` (`m`+GEOID or `_unspecified`), `lit_breakdown=1`, and `lit_level` (`nj`, `usa`, `county`, `municipality`). Invalid values are ignored and incompatible states normalized. Active language falls back to the first remaining selected language in the stable reference order. Town selection enables the breakdown; switching county clears its town filter; turning the breakdown off clears it. Unrelated query parameters/hash are retained. Semantic changes push history; pan/zoom frames and animation never do. Refresh/Back/Forward fit the appropriate geographic extent; exact transient pan position is intentionally not persisted.

Radius in CSS pixels is `sqrt(count) × .45` for counties and `sqrt(count) × 1.4` for municipalities, clamped to [5,32] for positive counts. Within the uncapped range, area is proportional to count. The legend changes between these two scales; **do not compare apparent area across geographic levels**. Zero uses a hollow radius-4 ring; missing records have no bubble. Transparent interaction circles have at least radius 22. The count labels and comparison panel are exact even where visual caps apply. No languages are summed together. Unspecified counts contribute only to county totals and the panel.

Bubbles are anchored to projected interior points. A screen-space SVG overlay keeps symbol size constant while the geographic SVG viewBox pans/zooms. A separate child group bobs by at most 1.8 pixels or 20% of the anchor's current interior clearance, whichever is smaller. This bounds the moving center inside its own geography; symbol discs can cross borders and are explicitly explained as symbols. Timing varies deterministically. Hover, focus and selection pause animation. `prefers-reduced-motion` disables it, the localStorage key `lit_map.motion` saves the switch, and IntersectionObserver/document visibility pause it offscreen. The map is usable with motion off.

The initial page includes the county SVG, normalized metrics, municipality references and anchor metadata. **USA and municipal boundary SVGs are not in the initial payload.** USA loads on first overview; towns load only for the selected county when enabled (one per county, cached per component). No survey AJAX/filter reload is used. Failed requests are retryable; asynchronous render tokens prevent late responses from inserting the wrong county. Parsed static SVGs reject unexpected elements, event handlers, href/style and URL-based attributes. Exact counts remain available when a geometry request fails.

Preview route `no_cache: TRUE` and render `max-age: 0` intentionally disable Drupal page/dynamic/render caching for this fixture-driven preview. The services reread and revalidate fixed JSON files every request; no mtime-only memoization or stale render attachment exists. After DDEV synchronizes a host edit into its web container, data changes appear on the **next page refresh without drush cr**; an already-open page remains a coherent snapshot. Geometry assets are content-hashed; regenerate the manifest alongside changed geometry so browsers request new filenames. Malformed/missing fixture files yield a friendly message, attach no metrics, and log only an error category to the module logger. Filesystem details and raw invalid JSON are not exposed.

## Development checks

```sh
ddev drush cr
ddev exec php tests/lit_map/normalizer-test.php
ddev exec php tests/lit_map/statewide-test.php
node tests/lit_map/bubble-model-test.cjs
tools/geometry/.venv/bin/python tools/geometry/convert.py --check
tools/geometry/.venv/bin/python tools/geometry/bubble-geography.py --check
python3 tools/geometry/generate-statewide-fixture.py --check
node tests/lit_map/browser-test.cjs
node tests/lit_map/bubble-browser-test.cjs
node tests/lit_map/bubble-edge-test.cjs
node tests/lit_map/bubble-refresh-test.cjs
```

Browser QA uses development-only Playwright Core 1.58.2 and the locally installed Chrome. Set `LIT_PLAYWRIGHT_PATH` to its package directory; the local default is `/private/tmp/lit-geometry-qa/node_modules/playwright-core`. There is no browser testing package in the Drupal runtime. Run the fixture-refresh test serially, with no other page tests: it temporarily edits only the synthetic statewide fixture and restores its bytes in `finally`.

No database schema, MariaDB settings, system PHP, Composer runtime dependency, taxonomy, Paragraph, import adapter, or recording interface is changed. No deployment/push occurs. See `verification.md` for exercised checks and limitations.
