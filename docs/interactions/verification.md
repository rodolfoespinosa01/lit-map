# Milestone 3 verification — November 13, 2026

Preview: https://rutgers-lit-m1.ddev.site/lit-map/interaction-preview

## Completed checks

The initial inspection found no applicable AGENTS.md files and no Git repository.
README, version 1 synthetic documentation, module code, Census county metadata,
and Milestone 2 verification were read before changes. The original geometry
preview and all geometry assets/metadata were preserved.

### Data service

`ddev exec php tests/lit_map/normalizer-test.php` passes **46 checks**:

- All 13 county/language totals match independent fixture expectations; each is
  the sum of only its municipality and unspecified buckets.
- Spanish county totals: Bergen **20**, Hudson **28**, Morris **10**, Essex **26**.
  Their unspecified portions are **5, 6, 2, 4**, respectively.
- Explicit Korean zeroes in Hudson and Morris survive normalization; absent
  Portuguese in Morris and unrecorded counties remain missing.
- Duplicate municipality combinations, duplicate null/omitted unspecified
  combinations, bad count types/ranges, unknown references, county mismatches,
  unexpected personal fields, separate county totals, unsupported versions,
  non-synthetic data and unsafe aggregate overflow are rejected.
- Optional municipality omission is equivalent to null; empty data stays empty.
- Only normalized public output is returned and successive calls use new input.

PHP syntax checks passed for both service classes and the controller; JavaScript
syntax check passed. Drupal successfully rebuilt services/routes/theme/library
caches. No runtime or Composer dependency was added.

### Browser interaction

Chrome with Playwright Core 1.58.2 passes **41 checks**. Full results are recorded
in [browser-checks.json](browser-checks.json), including:

- Empty initial selection and prompt; breakdown off and municipality disabled.
- County-only lists every available language and count.
- County/municipality-before-language and language-before-county produce the
  same panel results.
- Municipality selection enables visible breakdown; changing county clears an
  incompatible municipality; disabling breakdown clears its filter and URL value.
- Known municipality counts and unspecified counts are correct.
- English/Spanish remain separate: Essex shows 16 and 26, never a combined 42.
- Four matching counties and correct per-language callouts; explicit zeroes and
  missing county/language records display distinctly.
- Clear-languages, reset, invalid/mismatched/duplicate URL handling, unrelated
  parameters and fragments, refresh, and Back/Forward restoration work.
- Back after reset restores all four state dimensions; Forward restores empty state.
- Keyboard language and breakdown controls, native county-select typeahead,
  SVG arrow navigation and Space selection work with a visible focus outline.
- Drupal repeated attach calls do not duplicate controls; interaction requests
  **no AJAX or page navigation**.
- At **1440, 390, and 320 pixels**, no horizontal page overflow occurs, the SVG
  keeps its verified aspect ratio, and count-callout text remains at least 14px.
- Touch selection through a large county callout works. The synthetic notice stays
  visible when mobile results are scrolled into view. No JavaScript errors occurred.

Headless macOS Chrome did not expose its native dropdown popup's arrow selection
to automation. Native keyboard typeahead was exercised successfully instead;
the independent SVG arrow-key route was also tested. The original native select
was retained without custom key interception.

### File freshness and failure handling

`node tests/lit_map/fixture-refresh-test.cjs` passed. Results are retained in
[fixture-refresh-checks.json](fixture-refresh-checks.json).

The test changed Bergen/Hackensack Spanish from 12 to 13 while preserving file
byte length and mtime. A fresh request to the **same URL** showed county total
**21 instead of 20**, without rebuilding Drupal caches. It then tested malformed
JSON and an absent fixture; both returned the friendly unavailable message with
no data controls, survey settings, or exposed filesystem path. Finally, the
original bytes and timestamps were restored and the next request showed **20**.

Drupal headers confirmed both page-cache layers were uncacheable. `lit_map` log
entries were inspected: expected `JsonException` and `RuntimeException` categories
were logged without paths or raw records. The contract tests were rerun successfully
after restoration. Existing loaded tabs retain their request snapshot until refresh;
there is intentionally no background polling.

### Preserved environment

- Drupal **10.6.21** boots successfully; PHP **8.3.36**.
- MariaDB **11.8.9-MariaDB-ubu2404-log**, with no engine/configuration change.
- No new survey tables, Drupal entities, taxonomies or database schema changes.
  Normal Drupal cache and QA error-log writes occurred.
- Paragraph type count remains **0**; taxonomy module remains disabled.
- `tools/geometry/convert.py --check` passed. The verified SVG and associated
  source-derived assets still reproduce byte-for-byte.

## Visual inspection and limits

Desktop and mobile Chrome screenshots were opened and actually inspected. The
map retains recognizable NJ geometry; numbered markers connect the small
northeastern counties to readable count callouts. County selection is outlined,
counts are separated by language, the municipality panel labels its scope, and
missing values remain textual. On mobile the map, callouts, and result panel stack
without clipping, while the sticky notice keeps the fabricated nature visible.

Evidence:

- [Initial desktop](screenshots/initial-desktop.png)
- [English/Spanish, Essex/Newark desktop](screenshots/selected-desktop.png)
- [All languages, desktop](screenshots/all-languages-1440.png)
- [All languages, 390px](screenshots/all-languages-390.png)
- [All languages, 320px](screenshots/all-languages-320.png)
- [Mobile results with persistent notice](screenshots/mobile-results-viewport.png)

No milestone blockers remain. Not performed: Safari/Firefox checks, physical-device
touch testing, a real screen-reader session, Drupal 11 tests, production performance
tests, or final Paragraph integration. Touch was emulated in Chrome. Native SVG
county names get small on mobile; full-size text callouts, the county selector, and
the results list provide readable alternatives. This is not a full accessibility
conformance audit. The Milestone 1 Drupal support-lifecycle warning still applies.

## Changed files

Updated:

- Root `README.md` and `docs/survey-format.md` (active contract is now version 2).
- Module `lit_map.info.yml`, `lit_map.module`, `lit_map.routing.yml`,
  `lit_map.libraries.yml`.

Added under `web/modules/custom/lit_map/`:

- `lit_map.services.yml`
- `src/Service/SurveyNormalizer.php`, `src/Service/SurveyDataProvider.php`
- `src/Controller/InteractionPreviewController.php`
- `data/survey.synthetic.v2.json`, `data/survey-references.json`
- `templates/lit-map-interaction-preview.html.twig`
- `js/interactions.js`, `css/interactions.css`

Added tests:

- `tests/lit_map/normalizer-test.php`
- `tests/lit_map/browser-test.cjs`
- `tests/lit_map/fixture-refresh-test.cjs`

Added documentation/evidence:

- `docs/survey-format-v1.md` (preserved historical contract)
- `docs/interactions/README.md`, this verification report, browser and fixture
  refresh JSON reports, and six screenshots.

The old version 1 fixture is preserved but is not used. There were no changes to
Composer manifests/lockfile, DDEV settings, verified SVG, or geographic source
metadata. No deployment, publication, push, unrelated environment modifications,
CSV adapter, Qualtrics API, media integration, taxonomy, or final Paragraph.
