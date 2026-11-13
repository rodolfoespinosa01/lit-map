# Synthetic interaction preview — Milestone 3

Local URL: https://rutgers-lit-m1.ddev.site/lit-map/interaction-preview

Run `ddev start` from the project root. The route requires `access content` and
works anonymously on this local test host. After installing these module code
changes into another copy of the host, run `ddev drush cr` once to discover the
new services, route, library, and theme hook. Fixture-only changes need no rebuild.
No new modules, Composer packages, database tables, or environment settings are
required. The original `/lit-map/geometry-preview` is preserved.

## Data and rendering

Read [the version 2 JSON contract](../survey-format.md) for the 33 fabricated
records, references, known totals, and explicit-zero/missing distinction.

`SurveyDataProvider` reads fixed module files and delegates validation and
aggregation to `SurveyNormalizer`. That normalizer takes decoded data and reference
catalogs and has no Drupal, filesystem, database, or Qualtrics dependency. A future
adapter must produce this interchange contract rather than embedding Qualtrics
logic in the normalizer. Only validated public labels and counts reach
`drupalSettings.litMapPreview`; the controller prepares a render array, not data.
Twig is presentation-only and includes the existing verified SVG. Attached scoped
CSS and vanilla JavaScript use Drupal Behaviors, `core/drupalSettings`, and
`core/once`. The behavior cleans up listeners when Drupal detaches the component.

One preview instance is supported on this page. Multiple component instances,
per-instance SVG ID namespacing, and a final Paragraph remain future work.

## Interaction semantics

- Initial state has no language/county selected and a clear prompt.
- Language checkboxes support any combination. Recorded county/language values,
  including explicit zeroes, highlight matching counties. Numbered map-margin
  callouts show separate per-language **county totals**; they never combine
  languages into a unique-person total. On narrow screens the callouts move below
  the geometry within the same figure, retaining readable text sizes.
- Select a county with the native select, a map path, or a large county callout/
  results button. No language selection is required. County-only selection shows
  all languages with a record in that county, including recorded zeroes.
- The county selection determines the information panel. With languages but no
  county selected, its accessible list covers all 21 counties; missing values say
  “No data available.” This does not mean zero speakers.
- “Show town/city breakdown” starts off. Turning it on reveals municipality
  buckets, including the explicit **Municipality unspecified** bucket, after a
  county is selected. There is no municipality geometry.
- The town/city selector becomes available when a county is selected, even before
  languages are chosen. Selecting a municipality automatically enables the
  breakdown so its filter is visible. It filters the breakdown only; county totals
  remain labeled and visible for context. Clearing that selector shows all listed
  municipality buckets plus unspecified.
- Changing county clears a town/city that belongs to a different county. The
  unspecified category is valid across counties. Turning breakdown off always
  clears the municipality filter, including its URL parameter.
- “Clear languages” retains county/breakdown state. “Reset all selections” clears
  all four owned state fields. Selecting the blank county option clears the county
  and municipality. Controls and notices explain empty/no-data states.
- The synthetic notice remains in every state, including file errors, and sticks
  to the viewport while scrolling within the preview.

## URL state

| Parameter | Values |
| --- | --- |
| `lit_lang` | Repeated known language IDs, e.g. `lit_lang=en&lit_lang=es` |
| `lit_county` | Known five-digit NJ Census GEOID, e.g. `34013` |
| `lit_municipality` | Known municipality key belonging to the selected county, or `_unspecified` |
| `lit_breakdown` | `1` for on; absent for off |

Example:
`?lit_lang=en&lit_lang=es&lit_county=34013&lit_breakdown=1&lit_municipality=newark`

Values are validated against the server-validated references. Unknown language IDs
are discarded and duplicates removed; invalid county/town references are ignored.
A municipality without a valid county and enabled breakdown is ignored. The
canonical URL removes invalid owned parameters using `replaceState`. User actions
use `pushState`; `popstate` restores all controls, map and results without reloading.
Unrelated query parameters and URL fragments are preserved. The URL is display
state only: PHP never uses it to select a data path. Filtering sends no requests.

## Cache strategy and failure behavior

This small local preview favors correctness over render-cache reuse:

1. The provider reads and validates file contents on **every new request**. There
   is no service static cache, Drupal cache item, filemtime key, or long-lived data
   preparation result.
2. Route option `no_cache: TRUE` invokes Drupal's no-cache route response policy,
   disabling Internal Page Cache. Render metadata `max-age: 0` disables render and
   Dynamic Page Cache reuse for this response, including its settings attachment.
3. Verified headers: `X-Drupal-Cache: UNCACHEABLE (response policy)`,
   `X-Drupal-Dynamic-Cache: UNCACHEABLE (poor cacheability)`, and
   `Cache-Control: must-revalidate, no-cache, private`.

The mutation test changes a value while preserving file size and mtime, requests
the exact same URL, and observes 20 → 21 without `drush cr`. Restoring the fixture
produces 20 again. Thus freshness does not depend on filesystem timestamp precision
or externally invalidating cache tags. Already-open pages intentionally hold their
loaded public snapshot; refresh to see file edits. There is no background polling,
AJAX, or promise of live updates to an already-loaded tab.

Missing, malformed, invalid-reference or otherwise invalid input results in a
friendly unavailable message with no controls or survey settings attachment.
The `lit_map` logger records only a generic failure and exception category, without
raw records, filesystem paths, or exception traces. Log entries can be inspected
with `ddev drush watchdog:show --type=lit_map`. Expected QA error entries remain in
the local log as evidence; no unrelated logs were cleared.

## Accessibility and verification

Controls use native labels, a language fieldset/legend, keyboard focus styles and
touch targets. The SVG becomes a labeled group of county buttons with a roving
tab stop: arrows/Home/End move focus; Enter/Space selects. The native county select
and large text buttons are equivalent alternatives to small SVG targets. The map
has selected-state outlines, numbered callouts, text labels and a separate results
list; color and hover are not the only ways to get information. A concise live
status describes state changes without reading the entire result list each time.

Run tests:

```sh
ddev exec php tests/lit_map/normalizer-test.php
node --check web/modules/custom/lit_map/js/interactions.js
tools/geometry/.venv/bin/python tools/geometry/convert.py --check
# Same temporary browser driver used in Milestone 2; no frontend build system.
npm install --prefix /private/tmp/lit-geometry-qa playwright-core@1.58.2 --no-audit --no-fund
node tests/lit_map/browser-test.cjs
node tests/lit_map/fixture-refresh-test.cjs
```

The browser tests use installed Google Chrome on this Mac. Override
`LIT_PLAYWRIGHT_PATH` if Playwright Core is elsewhere. The fixture test temporarily
changes and hides **only the synthetic fixture**, then restores its original bytes
and timestamps in `finally`. Do not edit that fixture concurrently with this test.
No cache rebuild occurs inside the fixture test.

See [verification.md](verification.md) for executed checks, screenshots, limitations,
and the complete changed-file inventory. Drupal 11, final Paragraph integration,
CSV/Qualtrics adapters, taxonomy, and media remain unimplemented or untested.
