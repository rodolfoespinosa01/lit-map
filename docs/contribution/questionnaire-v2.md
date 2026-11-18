# Transcribed questionnaire v2, local demo and submission dates

This implementation follows the **team printout transcription supplied by the
user**, not a native Qualtrics export and not proof of an approved public-disclosure
policy. No PDF is required or retained. The source repeats a section number;
question keys `q01`–`q30` are stable and independent of section numbering.

- [Question-key mapping](questionnaire-mapping.md)
- [Normalized v2 JSON Schema](questionnaire-v2.schema.json)
- Complete question wording/options: `web/modules/custom/lit_contribution/data/questionnaire.v2.json`
- [Existing import workflow and v1 compatibility](export-upload.md)
- [Owner/admin password access](password-access.md)

## Local URLs

Use `https://rutgers-lit-m1.ddev.site`:

| Purpose | Path |
| --- | --- |
| Local demonstration form | `/lit/local-survey` |
| Public map | `/lit-map/contribution-preview` |
| Owner dashboard | `/admin/lit` |
| Contribution mode / disclosure threshold | `/admin/config/lit-contributions` |
| Normalized export import | `/admin/content/lit-contributions/import` |
| Pending review | `/admin/content/lit-contributions/pending` |
| Publish | `/admin/content/lit-contributions/publish` |
| Reconciliation | `/admin/content/lit-contributions/reconciliation` |
| Baseline/archive composition | `/admin/content/lit-contributions/datasets` |

Owner login is `/lit/superadmin/login`; reviewer login is
`/lit/administrator/login`. Contributors do not create an account/password.

## Content fidelity and explicit assumptions

The welcome uses neutral demonstration text and clearly labeled placeholder
contacts at example.org. Study agreement wording is a placeholder pending the
team's approved text. The 18+ participation checkbox remains required. The form
prominently states **no real raffle entry**. Survey questions, choices, scales,
conditional behavior and requested name/email fields are unchanged.

The printout does not establish all required flags or branching. Development
choices, pending team confirmation:

- Required for acceptance: consent checkbox affirming 18+, reading information
  and voluntary participation, plus a separately labeled **application
  duplicate-check email**. If age is supplied below 18, acceptance fails even
  when the checkbox is checked. Declining participants are told to close the page.
- Original questionnaire answers are otherwise optional. Unanswered single
  choices/matrices are `null`, never No, zero, Beginner or Neutral. Empty
  multi-selects are unknown, not an inferred “none” answer (which the printout
  does not supply). The slider starts visually at 50 but is **unanswered** until
  its explicit answer checkbox is checked. Zero is a valid explicit answer.
- The application adds a separate map-aggregate permission field (`yes`, `no`,
  `unknown`). It is **not an original question**. Consent to participation alone
  does not grant permission to publish. Unknown/no permission stays excluded.
- The duplicate-check email is never copied into Q29 interview contact or Q30
  optional raffle contact. Those remain separate private answers. Submitting them
  never creates an interview/resource card or raffle entry.
- Q2 is shown when Q1 is No. Q11/Q13 appear for an entered second language;
  Q12/Q14 for a third. The explicit Q12 skip instruction is preserved; extending
  this display behavior to the other slot matrices is a documented assumption.
- Q26/Q27 display when Q25 is Yes. The source instead refers to selecting a
  program type, although the preceding visible item is Yes/No: **unresolved
  branching ambiguity**. Other yes/no follow-ups do not invent hidden branching.
  Q29 is shown when Q28 is Yes; it remains optional pending required-flag review.
- Q8 retains the “settings” wording and language rows. Its ambiguity is displayed;
  there is **no invented home/work-use question**. Q10 Language 1 proficiency
  has Beginner, Intermediate, Native/native-like only; Q11/Q12 additionally have
  Advanced. There is no Language 1 support matrix. Speaking/reading/writing stay
  distinct, without adding a signing-proficiency question.
- Age/years fields use optional integer 0–120 inputs; age learning began 0 can
  represent birth. They never infer first-language, native or heritage identity.
  Q22 uses integer steps 0–100 with visible ticks every 10; the actual Qualtrics
  allowed step is unconfirmed. Text limits: language 100, gender/name 200, place
  and email 254, brokering reflection 2,000 characters.

The raw ordered language slots remain private. Exact aliases such as English,
Spanish/Español, Korean, Portuguese, Arabic, Hindi, ASL, Mandarin, Cantonese and
French map to stable canonical IDs. Mandarin (`cmn`) and Cantonese (`yue`) are
separate. Generic “Chinese” remains ambiguous; the old `zh` provisional grouping
is supported for legacy records but cannot be chosen as a new v2 resolution.
Other unknown languages remain unresolved; expanding the canonical catalogue
requires an explicit reviewed code/reference update, not guessing a substitute.

County/municipality text is private. Known NJ names are matched to the existing
Census references; Newark implies Essex, with conflicts flagged. Explicit non-NJ
residence is excluded from this NJ map. Ambiguous/unmatched references remain
pending for review. The private detail page links to **Resolve questionnaire
language/place references**. Reviewers must have evidence for a resolution; an
explicit “leave unresolved” choice prevents accidental municipality assignment.
Non-NJ or contradictory residence may need a source correction/rejection rather
than a manufactured NJ location. No original free text is overwritten by canonical
mapping. Changed resolution requires renewed review; unresolved flags prevent
approval. A map-ineligible response is not treated as zero speakers.

## Local collection, permissions and abuse controls

Modes are Off, Local demo form and External survey URL. External mode retains
syntax validation of a configured HTTPS Qualtrics URL; no live connection or
working URL is invented. Off hides the invitation and disables local intake.
Disabling a link does not close the external Qualtrics survey.

Local mode requires **both** `$settings['lit_local_demo_enabled'] === TRUE` and
DDEV's `IS_DDEV_PROJECT=true`. It fails closed without either, even if Drupal
configuration says Local. The explicit local setup is:

```sh
python3 scripts/install-local-survey-settings.py
ddev drush php:script scripts/configure-local-survey.php
ddev drush cr
```

The first script appends a guarded development include to the existing ignored
`web/sites/default/settings.php` and creates ignored
`private/lit-local-development.php`. Existing settings are preserved. Do not
copy this local flag into production. No DDEV restart is required. The second
command chooses Local and the development panel threshold 5 while preserving
the external URL. Owner settings can subsequently choose Off or External.

Form API supplies field validation and rendering; a session-bound CSRF token,
one-hour non-answer session marker, honeypot and per-IP 20 attempts/hour flood
limit protect anonymous submissions. Drupal discards metadata-only anonymous
sessions, so the marker is necessary to retain the token seed. Raw answers are
not form-cached. Errors and duplicate failures do not expose existing records.
The browser cannot provide a source response ID, timestamp or approval status.

`LocalDemoSourceAdapter` reuses normalized validation and the same transactional
operational importer as exports. IDs are random server-generated `LOCAL_…`
references; submitted/updated timestamps are generated in UTC with `+00:00`.
The survey scope is `LIT_LOCAL_QUESTIONNAIRE_V2`. Accepted responses are always
pending. Identical normalized-email rules, unique hashed reservations and the
shared mutation lock apply to concurrent local submissions. Invalid requests
reserve nothing. Email matching does not verify ownership. Contacts and answers
stay in private files outside `web/`; Drupal stores references/review metadata.

Owner/UID 1 can publish and change settings; administrators can review/resolve
operational responses but cannot publish or manage owners/accounts. No anonymous
or general authenticated role gains administration permissions.

## Versions, imports and privacy

Input schema 2 uses the existing seven transport fields: `schema_version`,
`survey_id`, `response_id`, `email`, `submitted_at`, `updated_at`, `answers`.
The transport email is the application duplicate-check field. V2 `answers` has
`consent`, `map_permission`, `questions` keyed by `q01`–`q30`, and optional private
`resolutions`. The validator derives canonical geography, slot associations,
reported-language membership and eligibility flags; clients cannot override
those derived values. Input JSON Schema documents the input fields, not these
internal derived fields.

JSON uses `export_version: 2`; CSV retains the documented header and JSON-encoded
`answers` cell. `schema_version` on each record explicitly selects the v1 or v2
adapter. Existing v1 exports, records and decisions remain supported; old fixed
answers are not retroactively translated into new questionnaire answers.

Current downloadable examples (identical logical CSV/JSON records):

- `fixtures/lit-export/questionnaire-v2.json`
- `fixtures/lit-export/questionnaire-v2.csv`

These are **Simulated export—not yet verified against Qualtrics**. First preview
has 3 new, 1 duplicate-email, 1 invalid consent, 0 changed/already imported; one
new record has an unresolved language and is publication-ineligible. Two can be
reviewed normally; the unresolved one requires resolution before approval.
Original v1 examples remain in `fixtures/lit-export/example.*` for compatibility.
Reimport preserves unchanged decisions; changed answers or mappings return to
pending. Existing import limits remain 2 MiB/1,000 records and private staged
previews expire after one hour. No native Qualtrics column names are invented.

Public map metric: approved responses reporting **speaking OR understanding ANY
selected language**, once per geographic area. Reporting a language in Q7 is
membership; proficiency/learning age is not used to exclude understanding-only
responses or infer native identity. Categorical bubble segments remain language
indicators, never population shares. Legacy v1 counts retain their explicit
reported-language/zero/missing semantics.

Aggregate schema 4 adds validated `daily_records` and `daily_groups` to the
existing marginals and disjoint language-set groups. Publication manifest version
3 points to those counts and a separate immutable private questionnaire-cohort
aggregate file. That separate file contains only allowlisted question marginals,
not contacts, demographics or reflections; it is **never attached to the page or
served directly**. Raw source answers remain private and separate.

The public panel endpoint filters the last published cohort aggregates and then
applies the owner-configured answered-response threshold (default 5, configurable
2–100). It shows frequency, proficiency by skill/language, explicit heritage
identity, language support, brokering, policy and education aggregates. It never
publishes Q4 age, Q5 gender, Q6 birth order, learning ages, brokering-start age,
Q20 reflections or Q29/Q30 contacts. No quotes or contacts become resources.

Denominators are applicable published responses; missing answers are separate.
Slot-specific panels apply only to a reported canonical language; support applies
only where that language was in slot 2/3. Education follow-ups use the documented
Q25=Yes assumption. Legacy records without the questionnaire are disclosed
separately, not assigned invented answers. Multi-select counts can overlap.
A whole panel is suppressed if answered count, any nonzero answer cell or a
nonzero missing cell is below threshold, preventing simple complementary-cell
reconstruction. Suppressed panels expose no answer counts or denominators.
This conservative development rule is **not research-team approval or a formal
privacy guarantee**; repeated overlapping queries can permit inference and need
policy review before real-data public use. Existing map counts are deliberately
not newly thresholded, as requested. Public language/date aggregates contain no
email, response ID, raw answers or individual exact timestamps.

## Submission-date semantics

Both public and admin filters use **submitted_at**, never import/review/publication
time. Timezone is visibly **America/New_York**. From and Through are inclusive
local calendar dates. PHP derives UTC boundaries as local midnight From through
local midnight *after* Through: 23-hour spring DST days and 25-hour fall days are
handled correctly. Frontend filters compare the already-validated NY day buckets.

Public controls: From, Through, All time, Last 30 days, Last 90 days, This year.
Presets include today in New York. Date controls live inside the existing filters
disclosure to preserve the opening map viewport; its summary shows active dates.
`lit_from`/`lit_through` join existing URL state and preserve unrelated parameters.
Refresh and Back/Forward restore them. All time clears dates; Reset restores the
English/all-time opening preset even after an empty result. Clear languages keeps
the visibly indicated dates; it does not secretly reapply English. Invalid ranges
show no results rather than falling back to all time.

The same dates apply to bubbles, union counts, county/municipality panels and
questionnaire aggregates. Selected municipality is cleared when city mode is
turned off, restoring the dated county total. Bounded dates exclude undated
legacy aggregate records; All time includes them with disclosure. Where original
legacy source timestamps exist, they are used unchanged on publication. No date
is fabricated from import/review time.

Questionnaire requests are pinned to the map's publication hash. If the owner
publishes while a browser is open, panels ask for refresh instead of mixing new
panels with old map counts. Publication remains atomic; separate immutable files
are written before the manifest pointer changes. Failed publication retains the
last good snapshot. Responses and panels use no-store/no rendered survey cache.
Admin lists show source-offset submitted times and UTC import/review/publication
times, with From/Through submission-date filters backed by added timestamp index
metadata. No survey answers were moved into database tables.

## New baseline and activation

The new private source is `private/lit-questionnaire-10000-v2/submissions.json`,
survey `SYN_LIT_QUESTIONNAIRE_V2`, references `R_QV2_00001`–`R_QV2_10000`.
It uses seed 20261114, fictional example-domain contacts, no future timestamps,
and the previously agreed county totals plus exactly **700 New Brunswick**
responses. Learning ages never determine identity. Counts are fabricated; they
are not population estimates or verified rankings.

```sh
python3 tools/contribution/generate-questionnaire-demo.py
ddev exec env LIT_CONFIRM_BASELINE_ACTIVATION=yes vendor/bin/drush php:script scripts/activate-questionnaire-baseline.php
```

Generation refuses to overwrite an existing new dataset. Activation is explicit,
DDEV-only, and preapproves only the new synthetic baseline once with an audit
label—not a research review. It never resets later decisions on reruns. The old
`SYN_LIT_10000_V1` source, 10,000 approvals and 100 seeded pending references remain
preserved. Selection in `lit_contribution.baseline` determines exactly one
baseline; older baseline/fixture rows are excluded, never added together.
Operational uploaded/local contributions and their decisions are retained and
combined only when approved/permitted. If activation publication fails, selection
returns to the previous baseline and its public snapshot remains active.

This local host was explicitly activated on **2026-11-14**. Its new baseline
reconciles to 10,000 responses. Example fabricated all-time totals: English 9,041;
English-or-Spanish 9,247; English/Spanish/Korean 9,413. For English-or-Spanish on
that date: Last 30 days 555; Last 90 days 1,677; This year 5,836. These will change
when contributors are approved/published or when rolling preset dates advance.

## Manual walkthrough

1. Open the local form (or the map invitation). Read the demo/no-raffle notice.
   Check I Agree, enter a **fictional unique** application email and choose map
   permission Yes. For a straightforward NJ test: Newark Yes, Essex county,
   Language 1 English, Language 2 Spanish; leave optional fields unanswered or
   answer using the supplied options. Slider remains unanswered unless checked.
2. Submit. A pending private response is created; the map is unchanged. Another
   submission with the same normalized email in this local survey is refused.
3. Sign in as reviewer. Open pending reviews, inspect the private record and
   distinct timestamps. Unknown references have a resolution link and cannot be
   approved until resolved. Approve the valid test with an internal reason.
4. Sign in as owner and publish. The matching all-time English/Spanish union
   increases by exactly one, not two; today's NY submitted-date filter includes
   it. The source ID, contacts, age and free text never appear in map payloads.
5. Test Last 30 days, a single-day range, county then towns, city mode off,
   refresh, Back/Forward, All time, Clear languages and Reset. Open questionnaire
   aggregates; small cells are suppressed and visible panels show answered,
   applicable and missing counts. A single test cannot force a small answer cell
   to be published below the configured threshold.

Automated tests use isolated source/review storage for accepted submissions and
approvals; they do not leave test contributions in the live operational queue.
Your manual walkthrough intentionally creates a local demonstration record.

## Remaining research/Qualtrics work

Confirm required flags, exact branching (especially Q8/Q27), slider step, age/year
input types, canonical-language expansion/resolution rules, non-NJ handling,
consent and publication permission, and a disclosure policy including temporal
query inference. Obtain a representative de-identified native Qualtrics export
with actual columns, stable IDs, multiselect encoding and timestamp timezone.
Implement a separate column mapper and validate parity with the questionnaire.
This work does not claim native export compatibility, live API connectivity,
production email delivery, research approval, or an actual raffle.

## Verification performed locally (2026-11-14)

- Questionnaire, validation, hand-calculated aggregation, DST and panel checks: 24 passed.
- Isolated actual form submit handler → pending → reviewer approval → owner publication: 17 passed, including failure recovery and unchanged live reviews.
- Two concurrent processes: exactly one private local response, pending review and email reservation. The competing request was safely refused.
- Form browser: 13 passed, including underage rejection, missing-token refusal, keyboard focus and mobile width.
- Date/map browser: 27 passed, including source totals, presets, refresh/Back/Forward, municipality clearing, empty and invalid ranges, public payload privacy and suppression.
- Date model: 14 passed; shared union model: 12 passed. Legacy validator: 82; normalized export import: 50; scoped workflow: 17; resources: 31; invitation modes/rendering: 13.
- Independently reconciled source versus published daily groups: English 9,041; Spanish 1,762; English OR Spanish 9,247; English OR Spanish OR Korean 9,413; all available languages 10,000. All-language New Brunswick is 700. Counts are fabricated survey responses, not population estimates.
- English/Spanish union on the tested NY date windows: Oct 16–Nov 14 = 555; Aug 17–Nov 14 = 1,677; Jan 1–Nov 14 = 5,836; March 8 DST day = 20; November 1 DST day = 15.
- Visually inspected desktop/mobile map, municipality map and mobile form screenshots. Bubbles/counts remain visible, selected municipality is marked, and content fits mobile width. Screenshots remain ignored generated artifacts.
- Read-only admin browser: 10 passed, including anonymous denial, submitted-date controls, malformed public query rejection and stale-publication rejection.
- PHP syntax checks and `git diff --check` passed. Private generated fixtures/configuration and screenshots remain ignored.
- Verified owner configured-owner UID 4/email unchanged, archived baseline 10,000 approved + 100 pending preserved, and live operational source count zero. New baseline explicitly activated and published; local invitation mode enabled.

Accepted workflow testing used isolated storage and the actual Form API submit handler, not a real accepted browser submission to the live queue. Browser tests exercised rendering and invalid requests. Real production concurrency/load, native Qualtrics imports, production delivery, screen-reader testing and Drupal Media playback were not verified. Existing separately approved external resource behavior was regression-tested; no interviews were created. Temporal aggregate queries are not a formal privacy guarantee.
