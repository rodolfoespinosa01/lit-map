> Historical Milestone 4 record. The bubble route now uses the informational full-file dataset and distinct-response unions. The old active-language/fixture-refresh browser tests were retired; use [current component verification](../component/verification.md) and [dataset instructions](../dataset.md).

# Milestone 4 verification — 2026-11-13

Preview: <https://rutgers-lit-m1.ddev.site/lit-map/bubble-preview>

## Environment and preservation

- Inspected parent/project instructions, README, earlier milestone documentation, existing module/services/tests and metadata before changes. No applicable AGENTS.md was found.
- `git status` reports that this directory is not a Git repository. No commit, push, deployment, or Git history change was performed; a Git diff is therefore unavailable.
- Existing DDEV host boots successfully: Drupal **10.6.21**, PHP **8.3.36**, database connected. No MariaDB configuration/schema or Composer dependency changes were made. Existing database version remains documented by Milestone 1; M4 does not install a second site or database.
- Original county SVG and metadata reproduce byte-for-byte with the original converter. Original M3 fixture and routes remain intact; its 41 browser regression checks pass.
- Only the local project, temporary browser tooling already available, and generated QA evidence were used. No Paragraph, taxonomy, CSV/Qualtrics adapter, media integration, or runtime mapping library was introduced.

## Exercised checks

| Check | Result |
| --- | --- |
| Existing `normalizer-test.php` | **46 passed**: malformed/invalid counts, duplicates, references, mismatches, aggregation, explicit zero vs missing |
| New `statewide-test.php` | **973 passed**: 564 references, all 21 counties, raw-record aggregation independent of normalization, unspecified counts included exactly once, known generated totals, zero and missing cases |
| `bubble-model-test.cjs` | **16 passed**: URL validation/round trip, unrelated parameters/hash, deterministic active fallback, clearing rules, uncapped area ratios, caps, zero/missing, pan/zoom bounds |
| Existing `browser-test.cjs` | **41 passed** against original M3 route |
| `bubble-browser-test.cjs` | **49 passed**; details in `browser-results.json` |
| `bubble-edge-test.cjs` | **10 passed**; details in `edge-results.json` |
| `bubble-refresh-test.cjs` | **4 passed**; details in `cache-results.json` |
| Original county converter `--check` | 21 names/IDs, retained rings/vertices, safe SVG, exact reproducibility |
| New geography converter `--check` | 564 unique municipalities, 21 county relationships, 51 states/DC, safe SVG output, interior anchors, exact reproducibility |
| Seed generator `--check` | 1,791 records, 27 deliberately unrecorded municipalities, 151 explicit municipality zeroes, exact reproducibility |
| PHP/JavaScript syntax | New service PHP lint and both new JavaScript files checked; route boots and renders anonymously |

The new browser checks exercise:

- No initial selected language/county; initial county geometry only; no USA/municipal asset request until needed.
- Multi-language comparisons stay separate, and changing the active language changes an uncapped county radius. Removing it picks the stable remaining language; clearing languages removes bubbles.
- County-before-language and language-before-county/town yield identical exact results. A pointer click selects the correct bubble, while drag/touch movement does not accidentally change selection.
- USA → NJ → county → towns; Back to New Jersey; zoom-out level transitions; reset; keyboard Enter on NJ/county, arrow pan, Home fit; zoom limited to 8×, pan bounds retained, pan does not write history.
- Municipality-before-toggle enables its visible breakdown, incompatible county changes clear the selection, toggle-off clears it, unspecified records remain available without map locations.
- URL refresh, Back/Forward, unrelated query/hash preservation, invalid URL values, exact explicit-zero and missing municipality cases.
- Selected, hovered and keyboard-focused bubbles pause; selected town gets a leader; motion preference persists across refresh; reduced-motion disables animation; offscreen maps pause.
- Mobile layout at 390×844 has no horizontal overflow. Explicit touch movement pans the map. A real emulated touch swipe with movement mode off scrolls the page, without changing selection.
- Failed USA and municipality asset requests show friendly messages and recover on retry; county counts remain available through municipal geometry failure.
- No browser JavaScript errors during the main 49-check run.

## Data examples checked

All values below are **fabricated survey responses**. They are never added across languages as a unique-person total.

| County | English | Spanish | Korean | Portuguese | Unspecified municipality, same language order |
| --- | ---: | ---: | ---: | ---: | --- |
| Bergen | 8,985 | 8,796 | 8,752 | 7,915 | 14 / 3 / 23 / 13 |
| Essex | 2,649 | 2,678 | 2,659 | 2,313 | 29 / 3 / 33 / 22 |
| Hudson | 1,976 | 2,046 | 1,632 | 1,050 | 22 / 32 / 37 / 23 |
| Morris | 4,449 | 6,737 | 5,252 | 5,703 | 5 / 29 / 33 / 27 |

The test independently sums raw records for each county/language and compares both normalized totals and generated reports. Each county's unspecified values contribute exactly once. Allendale borough's Spanish count is 260; selected screenshots show that exact value. The original M3 totals are deliberately unchanged and belong to its separate fixture.

## Geography and visual inspection

The selected Census vintage is **2025**. Municipality geography is **county subdivision/MCD**, not a CDP. The 564 names/GEOIDs are retained in `municipality-identifiers.csv`, all county relationships derive from source STATEFP + COUNTYFP, and every municipality anchor lies inside its own polygon and its source-named county. There are **611 municipal rings and 32,969 vertices**, all preserved. Polygon holes/multipart features use even-odd fill; no feature is reduced to its first ring. All 21 county and 51 state/DC identifiers are unique. Original county geometry is reused unchanged.

Converters construct SVG elements from an allowlist and check no event/href attributes. There are no scripts, remote images, linked styles or survey counts in the static geometry. Browser parsing adds a second rejection check for unexpected elements and event/href/style/URL attributes. Fills/borders are scoped CSS. USA uses appropriate projected panels; Alaska and Hawaii are labeled as rescaled insets.

**Visually inspected actual Chrome renders**, not only DOM counts:

- NJ overview: recognizable state outline and all county boundaries; county bubbles and separate language counts.
- USA: recognizable contiguous states, highlighted NJ, separate intact Alaska/Hawaii insets.
- Dense Bergen: all 70 municipal boundary paths; overlap is visible, selected bubble is emphasized with a geographic leader and exact callout. Zoom and selector/results alternatives remain necessary at the county-wide extent.
- Hudson and Essex: recognizable shapes, intact visible municipality divisions, hollow zero rings and separate exact comparisons.
- Mobile Bergen and results: readable controls, no horizontal overflow, selected Allendale callout/leader, explicit zeros and no-data messages in the results list.

Evidence is in `screenshots/`: `nj-desktop.png`, `usa-desktop.png`, `bergen-desktop.png`, `bergen-selected-desktop.png`, `essex-desktop.png`, `hudson-desktop.png`, `bergen-mobile.png`, `bergen-selected-mobile.png`, and `results-mobile.png`.

## Freshness, failures and size measurements

The cache test modifies the synthetic statewide fixture while keeping its byte length and mtime unchanged. After DDEV's Mac-to-container synchronization, same-URL requests show Bergen Spanish **8,796 → 8,803** without `drush cr`; both attached settings and rendered counts update. Missing/malformed fixtures yield a friendly message, no metrics attachment, and no filesystem paths. The original file is restored byte-for-byte and a fresh request confirms 8,796 again. The expected errors are logged by `lit_map` as categories only. These deliberately induced entries are retained in local Drupal logs.

Anonymous M4 responses report an uncacheable Drupal page response. Route `no_cache: TRUE` and render max-age zero are explicit; services read fixed files on each request. A page already open remains a snapshot until refreshed. File synchronization latency is distinct from Drupal caching: an immediate request made before the container sees a Mac edit may still read the old file. The retained test waits for synchronization through fresh requests; it does not clear caches or alter DDEV settings.

Measured final first load in local Chrome (fresh browser, anonymous):

| Resource | Decoded bytes | Encoded body bytes | Transfer bytes |
| --- | ---: | ---: | ---: |
| HTML including county SVG and validated settings | 275,434 | 91,787 | 92,087 |
| Drupal/module CSS aggregate | 8,330 | 2,545 | 2,845 |
| Drupal/module JS aggregate | 26,434 | 8,582 | 8,882 |
| Theme logo + favicon | 9,240 | 3,424 | 4,024 |
| **Total initial** | **319,438** | **106,338** | **107,838** |

Resource Timing measurements are retained in `browser-results.json`; local gzip/caching, theme and Drupal aggregate filenames can change these values. No USA or municipal geometry was requested during that initial measurement.

Static files on disk: county SVG **141,897 bytes** (included in initial HTML); all municipality SVGs combined **653,558 bytes**, but only **12,783–60,586 bytes per selected county** load on demand (Bergen 60,586); USA SVG **1,541,724 bytes**, loaded only on USA overview. Raw anchor metadata is 173,779 bytes on disk; the browser receives only validated necessary fields in the compressed HTML, not that whole file. The raw statewide fixture is 236,636 bytes and is never fetched by the interaction code; PHP attaches its normalized display representation.

## Known limitations and unperformed checks

- Chrome desktop and mobile emulation were exercised. Physical iOS/Android devices, Safari, Firefox, VoiceOver/NVDA and a full accessibility audit were **not** performed. Keyboard controls and accessible names/results were checked in Chrome.
- Dense municipality bubbles intentionally overlap at county-wide extent. Selection, leader/callout, zoom, native selector and exact results list resolve access; labels are not claimed to be collision-free. Minimum/maximum visual sizes alter strict area proportionality at the limits, and county/town scales differ as documented.
- Generalized Census boundaries are not legal or high-resolution municipal geography. Magnification adds no detail. Insets use different scales; USA/NJ navigation switches projections discretely. Source metadata's display-scale guidance is retained and documented.
- Touch map dragging is explicitly enabled; default touches retain page scrolling. Map pinch-to-zoom and wheel capture are intentionally absent; zoom buttons/keyboard are the supported alternatives.
- The full-state public metrics/references/anchors load initially. Only geometry is lazy-loaded. USA's 1.54 MB uncompressed SVG is a larger first-use download; no production/network-throttling performance benchmark was performed.
- Drupal 11 remains untested. This milestone does not add final Paragraph packaging, real survey ingestion or production caching.

## Changed-file inventory

Existing source files updated:

- `README.md`: M4 entry point and documentation links.
- `web/modules/custom/lit_map/lit_map.module`: bubble-preview theme hook.
- `web/modules/custom/lit_map/lit_map.routing.yml`: anonymous, uncacheable preview route.
- `web/modules/custom/lit_map/lit_map.services.yml`: reusable bubble data service registration.
- `web/modules/custom/lit_map/lit_map.libraries.yml`: scoped CSS/vanilla JS library.
- `web/modules/custom/lit_map/src/Service/SurveyDataProvider.php`: separate fixed statewide fixture reader; original reader preserved.

New module files:

- `src/Controller/BubblePreviewController.php`, `src/Service/BubblePreviewData.php`.
- `templates/lit-map-bubble-preview.html.twig`, `css/bubbles.css`, `js/bubble-model.js`, `js/bubbles.js`.
- `data/survey.statewide.v2.json`, `data/survey-statewide-references.json`, `data/bubble-geography.json`.
- `assets/municipalities/`: 21 content-hashed SVGs; `assets/us-states-2025.*.svg`: USA overview.

Reproducibility, tests and evidence:

- `tools/geometry/bubble-geography.py`, `tools/geometry/generate-statewide-fixture.py`; downloaded official source ZIPs remain in the already-ignored source directory.
- `tests/lit_map/statewide-test.php`, `bubble-model-test.cjs`, `bubble-browser-test.cjs`, `bubble-edge-test.cjs`, `bubble-refresh-test.cjs`.
- `docs/bubbles/`: README, this verification record, source metadata XMLs, identifier CSVs, geography/fixture reports, browser/cache/edge results, and screenshots.
- Running the existing M3 browser regression regenerated its existing local browser evidence/screenshots under `docs/interactions/`; no M3 source, original fixture or geometry was replaced.

The existing `.gitignore` already covers source ZIPs, virtualenv, dependencies, generated Drupal assets and secrets; no new ignore rules were needed. Composer manifests/lockfile and DDEV/database settings were unchanged.
