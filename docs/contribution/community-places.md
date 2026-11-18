# Community names and municipal geography

Hanover Township exists as Census GEOID **3402729550**, map key
`m3402729550`, in Morris County **34027**. East Hanover is a distinct municipality,
GEOID **3402719210**. Neither name is a fuzzy match for the other.

Whippany and Cedar Knolls were absent because Census municipal boundaries and
our municipality references do not enumerate these communities. The contribution
map's selector uses all statewide municipality references, filtered only by county;
it does not remove municipalities without survey records. Bubbles do depend on
selected languages, date range and published data. Absence of a bubble is not
absence of geography. The older Milestone 3 interaction preview uses its smaller
fixture reference set; use the contribution preview for statewide community search.

## Source and maintenance

[Hanover Township's official Township Profile](https://www.hanovertownship.com/231/Township-Profile),
accessed 2026-11-14, identifies Whippany and Cedar Knolls as communities within
Hanover Township, Morris County, and East Hanover as a neighboring township.
The lookup records these factual relationships and links attribution; no page
text, geographic coordinates or new boundaries are copied.

Edit `web/modules/custom/lit_map/data/community-places.json` to add sourced
community rows: name, NJ state, county GEOID, existing parent municipality key,
and authoritative HTTPS source. `CommunityPlaces` validates each parent/county
reference. Keep conflicting same-name entries rather than choosing arbitrarily:
normalization requires exactly one distinct candidate within county/state.
Add tests for new aliases and possible municipal-name collisions. Do not add
community rows to metric or geometry references.

## Search and normalization

The bubble preview's Search NJ municipality or community field searches all
municipality names and community names, scoped to the selected county (or all NJ
when County is clear). Keyboard-operable result buttons explain “within Hanover
township” and select the existing parent. Selection, URL, labels and counts use
Hanover Township. The search is not filtered by survey coverage.

Local questionnaire Q2 and schema-v2 normalized JSON/CSV imports share the same
validator. Q2 retains its original text privately. Morris + Whippany/Cedar Knolls
+ NJ maps to Hanover; full “Hanover Township” and shortened “Hanover” also work.
An explicit non-NJ state never maps automatically. With no state suffix, a resolved
NJ county supplies NJ context; missing/unmatched county or ambiguous names require
review. State text follows the existing comma-separated `community, state` format;
unsupported formats remain unresolved rather than guessed. Explicit reviewer
resolutions remain available and audited. Schema-v1 imports continue requiring
canonical municipality identifiers, not community text. No native Qualtrics column
mapping is claimed.

This change does not retroactively normalize stored submissions, synchronize
reviews, or publish new counts. Future import/review uses the existing changed-
response protections. No counts are allocated to communities. Community-specific
statistics require separately collected and reviewed community data; all Hanover
responses must never be described as Whippany responses.

## Verification

- 15 PHP checks: aliases, full/short municipal name, private original text,
  county/state scope, non-NJ review, ambiguous candidates, East Hanover separation,
  and unchanged complete publication/review fingerprints and aggregate payload.
- Browser checks: both community searches, keyboard activation, parent selection,
  county scoping, unchanged data, and selector availability without matching data.
- Current published English counts: Hanover 18; East Hanover 30. These are synthetic
  municipality-wide counts, not community counts. No source records were changed.

Run `ddev drush php:script tests/lit_contribution/community-places-test.php` and
`node tests/lit_contribution/community-browser.cjs` (existing local Playwright/Chrome
setup). Local URL: https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview.
