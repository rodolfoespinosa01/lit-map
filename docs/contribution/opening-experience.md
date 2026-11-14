> Current combined-count semantics and owner dashboard: [schema 3 and access verification](combined-counts-access.md). Earlier active-language-only behavior and publication versions below are historical.

# Contribution opening experience and bubble visibility

Verified locally on `contribution`, 2026-11-13. No source fixtures, review decisions,
publication pointer/files or active invitation settings were changed.

## Findings and behavior

The inherited opening state selected no languages. `bubbles()` intentionally
returns without creating bubbles when no active language exists. This explains
the blank no-query opening; it was not an aggregation failure. Published coverage
is also sparse (Bergen only), and the previous 5 px minimum radius made its small
counts hard to notice. Mobile introductory content placed the map below the fold.

The contribution controller now attaches an explicit `openingPreset` flag. Only
this preview defaults to New Jersey county view, breakdown off, English selected
and active. English must have a record in the published totals (including an
explicit zero); otherwise the lexicographically first recorded language ID wins.
The reference catalogue alone is not evidence of published data. An empty
publication does not invent counts or select an unavailable language.

Valid URL language/county/overview selections take precedence. Entirely invalid
map parameters fall back to the preset. Explicit clear is encoded as `lit_lang=`;
it survives refresh and Back/Forward. Existing valid `lit_level=nj` links without
languages continue to represent an intentionally empty selection. Reset restores
the opening preset; clearing languages does not trigger defaults again. Other
query parameters and fragments are preserved. Historical milestone previews
retain their original opening state and radius scale.

Filters are available in “Change languages and places.” Navigation is below the
map in the contribution preview, so the initial mobile viewport shows Bergen's
bubble. The synthetic notice remains visible, with the public update date and
active-language explanation. The provenance and sparse-coverage explanation
remain beside/below the map. English currently has records in 1 of 21 counties;
other counties say “No data available,” never inferred zero.

Positive contribution bubbles have a documented **12 px minimum radius**, retaining
the existing active-language scale and 32 px maximum; zero remains a hollow 4 px
ring. The floor is a legibility aid, not a data change or an assertion of exact
area proportionality below the floor. Comparison colors remain categorical;
languages are never summed as unique people.

## SVG checks

Reviewed the source SVG, geometry and overlay containers, CSS display/opacity,
viewBox and screen transformation, clipping, motion and behavior initialization.
The existing anchored screen-space overlay follows the geometry's `getScreenCTM()`;
floating motion is applied to a child, preserving the anchor. Added explicit
layer ordering: geography z-index 0, bubbles z-index 1. Tooltip content remains
outside the stage and does not cover it. No data/geometry conversion was changed.

Fresh Chrome sessions verified the actual disc and label bounds, opacity, stage
containment and center-point hit target above geography, not just DOM presence.
The nominal 24 px disc renders about 23.9 px due to the bordered overlay viewport.
Screenshots visually confirm the colored disc and white “7” on desktop (1440×1100)
and mobile (390×844), in the initial viewport without scrolling. Municipality
loading, county return, USA return and refresh preserve visible/count-correct
bubbles. USA intentionally has no response bubbles and identifies itself as context.

The actual current publication remains:
`private/lit-contribution/f92aadedb5f730a3adc5fa1cd8e9b6fb8a226816bbeebad9262c6f035a390891.json`
(version 1 envelope, schema 2 aggregate, published 2026-11-13 16:53:56 UTC).
English Bergen **7 = Allendale 6 + unspecified 1**; Spanish **5 = 4 + 1**;
Korean Bergen **2**. All eight language totals still match reconciliation.
There is no fallback to the statewide milestone fixture.

## Invitation

Actual settings remain `invitation_enabled: false`, `survey_url: ''`.
No confirmed working URL or live Qualtrics connection exists. When enabled with
a syntactically valid configured URL, Twig renders a prominent **Share your
language experience** link immediately above the filter control. Disabled,
blank or invalid configuration renders no link and does not change map behavior.

The admin settings explain that the research team must supply a confirmed,
published survey URL; an administrator enters it, enables the invitation and
saves. Syntax validation does not verify availability or connect an API. Disabling
the invitation does not close the Qualtrics survey.

The invitation test uses a separate `MemoryStorage` and `ConfigFactory`, tests
service decisions and the real Twig render for enabled/disabled/blank/invalid
cases, and checks that active configuration is unchanged. Its synthetic test URL
is never persisted, published, followed or presented as a working survey.

## Checks run

- `node tests/lit_contribution/opening-model-test.cjs`: **10** checks for preset,
  deterministic fallback, explicit zero, empty data, valid/invalid URL precedence,
  clear serialization, unrelated parameters and legacy preview behavior.
- `node tests/lit_contribution/opening-browser-test.cjs`: **39** checks for actual
  visible disc/label/hit testing, initial desktop/mobile viewport, sparse data,
  preset/clear/reset/refresh/Back/Forward, one/two/three languages, municipality
  on/off, USA/NJ returns, touch emulation, reduced motion and no JavaScript errors.
- `node tests/lit_contribution/count-browser-test.cjs`: **40** reconciliation,
  count, tooltip, keyboard, history and private-access regressions. Adapted its
  starting controls to explicitly clear the preset and open the filter disclosure.
- `node tests/lit_map/bubble-browser-test.cjs`: **49** existing larger-fixture
  map checks including anchors, motion and navigation.
- `node tests/lit_map/bubble-model-test.cjs`: **16** model regressions.
- `ddev drush php:script tests/lit_contribution/invitation-isolated.php`: **9**
  isolated invitation/render/settings-preservation checks.
- Read-only `reconciliation-live.php` confirms all published totals and eligibility.
  `trace.php` confirms unchanged review/audit/publication hash
  `76c4264162be8a4ceb0f76ed112693895711a9458881a5c674bc686154277cbf`
  and normalized source hash
  `5b8c69a2b9cf6f447863e4c4a69235555f2d1919408435b0cc1d042f64407933`.
- PHP syntax checks and `git diff --check`. Generated screenshots remain ignored.

Limitations: Chrome desktop/mobile emulation only; no physical phone, Safari,
Firefox or screen-reader session. No live survey availability/API test and no
live synchronization/republication were performed. On smaller viewports or hosts
with larger headers, scrolling may still be needed; no automatic page scroll is
forced. Future dense published coverage may require further overlap tuning.

## Local walkthrough

Open https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview with no query.
Expect a visible Bergen bubble labeled 7 and English active. Open the filter
control to compare Spanish/Korean; change the active-language selector to change
the number. Enable municipality mode for Allendale counts, then turn it off to
restore county totals. Clear languages, refresh, then reset; try Back/Forward.

Admin settings: https://rutgers-lit-m1.ddev.site/admin/config/lit-contributions

Reconciliation: https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/reconciliation

Changed implementation files: `lit_map/js/bubble-model.js`, `lit_map/js/bubbles.js`,
`lit_map/css/bubbles.css`, `lit_map/templates/lit-map-bubble-preview.html.twig`,
`lit_contribution/src/Controller/PublicMapController.php`,
`lit_contribution/src/Form/SettingsForm.php`, and `lit_contribution.routing.yml`.
Added opening/model/invitation tests and this report; updated the existing count
browser test, README and prior investigation walkthrough. Existing uncommitted
work remains intact; no staging, commit, push or deployment was performed.
