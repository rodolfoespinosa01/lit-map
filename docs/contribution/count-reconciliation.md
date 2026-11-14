> Current combined-count semantics and owner dashboard: [schema 3 and access verification](combined-counts-access.md). Earlier active-language-only behavior and publication versions below are historical.

# Count accuracy and breakdown investigation — 2026-11-13

Work stayed on `contribution`. Existing Milestone 4 zoom, municipality geometry,
motion controls and URL state were preserved. No synchronization, review decisions,
source edits, publication, demonstration reset, deployment or push was performed.

## Actual source and publication

The public `/lit-map/contribution-preview` uses `Publication::publicData()` and the
singleton `lit_contribution_publication.file_hash`, not the older milestone fixture.
Its current file is outside the public web root:

`private/lit-contribution/f92aadedb5f730a3adc5fa1cd8e9b6fb8a226816bbeebad9262c6f035a390891.json`

Publication envelope version **1**, aggregate schema **2**, synthetic **true**.
Published **2026-11-13 16:53:56 UTC**; public date **2026-11-13**.
The file hash is verified on every read. Its validated aggregate is normalized
before being attached to public `drupalSettings`. No raw source adapter is called
by the public controller. Historical preview routes still use their own fixtures.

The mock source is `fixtures/lit-contribution/submissions.v1.json`, survey
`SYN_LIT_PROVISIONAL_V1`. Read-only reconciliation found:

| Category | Records |
|---|---:|
| Source | 64 |
| Approved eligible and included in publication | 8 |
| Pending | 56 |
| Rejected | 0 |
| Duplicate-excluded | 2 |
| Permission-excluded | 6 |
| Unsynchronized / missing source | 0 |
| Audit events after publication / in publication second | 0 / 0 |

Categories overlap: duplicate records 061/062 and nonpermitted records
010/020/030/040/050/060 are already among the 56 pending records. Do not add
these exclusions to 56. Both unresolved duplicates are excluded; normalized-email
matching does not verify ownership. Publication rejects unsynchronized source
changes, then includes only approved, permitted, nonconflicting/chosen records.
The deterministic test also exercises rejected records, since this live snapshot
has none. Current eligibility, membership, fingerprints and aggregate contents
match the published snapshot; it does not predate any current decisions.

## Exactly why English is 7

All eight eligible records are in Bergen County (34003), have permission, and
are approved without unresolved duplicates. IDs below are synthetic private-source
references for developer reconciliation; they are **not** placed in public settings.

| Response suffix (`R_DEMO_`) | Municipality | English use | Spanish use |
|---|---|---|---|
| 001 | Unspecified | yes | yes |
| 002 | Allendale | yes | yes |
| 003 | Allendale | yes | no |
| 004 | Allendale | yes | no |
| 005 | Allendale | no | yes |
| 006 | Allendale | yes | yes |
| 007 | Allendale | yes | yes |
| 008 | Allendale | yes | no |

Allendale is `m3400300700`. English affirmative records are 001, 002, 003,
004, 006, 007 and 008. Record 005 explicitly answers no; it is not an extra
English response. A no contributes explicit zero; an absent/prefer-not-to-answer
language does not invent a zero.

| Language | Allendale | Unspecified | Expected county | Actual published/UI county |
|---|---:|---:|---:|---:|
| English | 6 | 1 | 7 | 7 |
| Spanish | 4 | 1 | 5 | 5 |
| Korean | 2 | 0 | 2 | 2 |
| Portuguese | 5 | 1 | 6 | 6 |
| Chinese (provisional grouping) | 5 | 0 | 5 | 5 |
| Arabic | 3 | 1 | 4 | 4 |
| Hindi | 6 | 1 | 7 | 7 |
| American Sign Language | 3 | 1 | 4 | 4 |

UI browser assertions directly cover English/Spanish; read-only service checks
cover all eight published/public DTO totals. Other counties have no published
records and show “No data available.” These are overlapping language responses,
not population estimates or a unique-person total.

## Reproduction and changes

Before changes, Spanish → Bergen showed 5; city mode → Allendale showed 4;
cities off immediately cleared the municipality URL parameter and restored 5.
Adding English while Spanish remained checked kept Spanish active, correctly
showing 5. Removing Spanish made English the sole active language and showed 7.
Thus the reported stale count was **not reproduced as an aggregation/state bug**.
The multiple-language checkbox interaction was ambiguous, and hover overwrote
persistent selection text. Small counts also share the existing minimum bubble
radius; changing 5 to 7 need not visibly change radius.

Changes:

- Active-language control and panel explicitly identify the language controlling
  numbers and size, including instructions for multiple-language comparisons.
- Hover/focus uses a dedicated accessible tooltip dock outside the map and its
  controls. It persists while read/hovered, with Escape and a dismiss button;
  selection/state changes clear it. It does not overwrite selected-place text.
- Enter, click and tap keep selected details in the panel. All selected languages
  have text counts. County breakdown remains complete even when a town is selected;
  the selected town and unspecified counts appear first, followed by other records
  and municipalities without data. A separate button clears the town selection.
- Unspecified selection has an exact active-language count in its summary.
- The active-language selector occupies a full mobile row to avoid truncation.
- Publication and reconciliation share the pure `AggregateBuilder` eligibility
  and aggregation code. Atomic writer/pointer behavior is preserved.

## Private reconciliation

`/admin/content/lit-contributions/reconciliation` requires the restricted
`administer lit contribution settings` permission. Anonymous visitors and the
review-only role receive 403. The report is uncached/private/no-store, with no
public attachments or endpoint. It displays source references and exclusions,
current/published eligibility, published county/language totals, immutable file
hash, publication time, and audit events after publication. It compares aggregate
contents and membership independently of timestamp ordering. Audit events within
the publication second are separately reported because timestamps have only
second precision. Source unavailability gives a friendly message and logs only
the exception class. It never synchronizes or publishes.

## Verification exercised

- `ddev exec php tests/lit_contribution/reconciliation-test.php`: 11 deterministic
  checks, hand-calculated English 2 / Spanish 1 from three eligible responses,
  explicit zero versus missing, pending/rejected/duplicate/permission exclusion,
  subsequent approval/rejection/permission withdrawal, isolated immutable file
  publication and failed-write preservation. Isolated files are in container tmp;
  the demonstration publication pointer is untouched.
- `ddev drush php:script tests/lit_contribution/reconciliation-live.php`: current
  source eligibility equals the existing publication; all eight public DTO totals
  equal published aggregates.
- `node tests/lit_contribution/count-browser-test.cjs`: 40 checks including the
  reported sequence, single/multiple languages, city filter clearing, refresh,
  Back/Forward, unrelated URL parameters, keyboard/pointer tooltips, persistent
  selection, no-data county, mobile width, admin/reviewer/anonymous access and
  public payload privacy. This script never submits review/publication forms.
- Existing regressions: 49 larger-fixture bubble browser checks, 16 bubble model,
  8 color model, 82 source validator, 46 survey normalizer checks; 17 PHP files
  passed syntax checks. `git diff --check` passed.
- Desktop (1440px) and mobile (390px) screenshots visually inspected. Generated
  screenshots remain ignored under `docs/contribution/screenshots/`.
- Read-only before/after metadata fingerprint (all review, audit and publication
  rows): `76c4264162be8a4ceb0f76ed112693895711a9458881a5c674bc686154277cbf`.
  Normalized-source fingerprint remained
  `5b8c69a2b9cf6f447863e4c4a69235555f2d1919408435b0cc1d042f64407933`.

No live re-publication/approval/rejection test was run against demonstration data.
Those transitions were tested with isolated deterministic inputs/storage. Existing
mutating contribution browser/integration scripts were deliberately not rerun.
No physical touch device or screen-reader session was exercised; keyboard and
responsive Chrome behavior were tested. No live Qualtrics connection exists.

## Manual walkthrough

1. Open `/lit-map/contribution-preview` with no query parameters. The newer opening
   preset selects English; use Clear languages and expand Change languages and places. Select Spanish,
   then Bergen: 5. Enable towns/cities and choose Allendale: 4; unspecified is 1.
2. Disable towns/cities: municipality selection and URL filter disappear, and the
   county summary restores 5. Select English and uncheck Spanish: 7.
3. Check Spanish again: English remains active at 7. Set active language to
   Spanish: 5. Refresh, then use Back/Forward to verify state restoration.
4. Hover or Tab to a bubble/boundary; read the separate tooltip. Escape dismisses
   it. Enter/click selects the place and exposes equivalent panel information.
5. Run `ddev drush user:login --name=admin --no-browser` for a local login link.
   Open the private reconciliation URL from the review queue. Expect 64 source,
   56 pending, 8 eligible/published, and matching publication. Do not publish merely
   to inspect the report.
