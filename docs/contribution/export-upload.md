> Current questionnaire, selected baseline, dated aggregates and sample downloads:
> [questionnaire v2](questionnaire-v2.md). Older counts/instructions below are
> retained as historical version-1 context; existing records remain supported.

# Survey export upload workflow

**Simulated export—not yet verified against Qualtrics.** Qualtrics remains the
external collection source. This workflow accepts a documented normalized export,
not an unseen native Qualtrics export. There is no public Drupal survey or live
Qualtrics API connection. The provisional fixed-answer schema is not a confirmed
research questionnaire.

## Local URLs and access

Base: `https://rutgers-lit-m1.ddev.site`.

- Owner dashboard: `/admin/lit` → **Import survey responses**.
- Upload/preview/history: `/admin/content/lit-contributions/import`.
- Pending contributor reviews: `/admin/content/lit-contributions/pending`.
- Owner-only dataset composition: `/admin/content/lit-contributions/datasets`.
- Owner-only reconciliation: `/admin/content/lit-contributions/reconciliation`.
- Owner publication: `/admin/content/lit-contributions/publish`.
- Public map: `/lit-map/contribution-preview`.
- Owner login: `/lit/superadmin/login`; administrator login:
  `/lit/administrator/login`. See [password access](password-access.md).

Only the designated owner and preserved UID 1 emergency administrator can import,
view import history/examples/dataset tools, change settings or publish. Reviewers
can approve/reject uploaded contributor requests; approval does not publish.
Generic authenticated and anonymous roles receive no private access.

## Preserved baseline and clean queue

Publication composition is explicit in `PublishedBaselineAndExports` and the
`lit_contribution.source` service: survey `SYN_LIT_10000_V1`, exact references
`R_LARGE_00001` through `R_LARGE_10000`, plus cumulative uploaded references.
It does not follow the older active-dataset switch. Missing/corrupt baseline data
fails closed. Eligibility still requires approval, permission and no duplicate
exclusion. Existing union groups and explicit-zero behavior are unchanged.

The 100 seeded pending references `SYN_LIT_10000_V1/R_LARGE_10001` through
`R_LARGE_10100` remain intact, including their decisions/audit records. They are
excluded from both the operational queue and publication. Older/unknown fixture
records are preserved and summarized in the owner-only dataset view. No records
were deleted, rejected or reapproved to clear the queue. Administrators cannot
open fixture details by guessing IDs. Uploaded records carry `origin=upload`;
the operational pending page includes only permitted pending upload records.

The live queue is left empty with no uploaded sample records. The original
publication hash/time and 10,000 approved baseline remain unchanged. Public labels
continue to disclose synthetic demonstration data even when uploads are added;
these are survey responses, never verified people or population estimates.

## Normalized format v1

See [JSON Schema](normalized-export-v1.schema.json), which reuses the answers
contract from [the provisional schema](provisional-survey-v1.schema.json).
Runtime PHP validation additionally checks actual county/municipality membership,
calendar dates, language references and duplicate identifiers.

JSON envelope has exactly `export_version: 1` and `submissions: [...]`. Each
submission has exactly these seven fields:

| Field | Meaning |
| --- | --- |
| `schema_version` | Integer `1` |
| `survey_id` | Stable external survey identifier; 1–96 ASCII letters, digits, `_` or `-` |
| `response_id` | Stable response identifier with the same character limits |
| `submitted_at` | ISO timestamp with explicit offset, e.g. `2026-11-01T10:01:00-05:00` |
| `updated_at` | Same timestamp format, at or after submission |
| `email` | Private email; trimmed/lowercased, with dots and plus tags preserved |
| `answers` | Provisional fixed-choice answer object |

`answers` contains `county_geoid`, optional municipality represented as JSON
`null` (the key is still required), `first_languages`, `languages`, and
`aggregate_permission` (`yes`/`no`). Each language profile contains
`uses_language`, speaking/signing proficiency, home use, other settings, frequency
and brokering. Use the existing language IDs and documented fixed choices; no
free-text answers or uploaded approval/reviewer fields are accepted.

CSV is UTF-8, comma-delimited, with exactly this header order:

```text
schema_version,survey_id,response_id,submitted_at,updated_at,email,answers
```

`answers` is a properly CSV-quoted JSON object, not an invented set of Qualtrics
column names. Standard CSV doubled quotes are supported; backslash escapes are
not used. JSON and CSV share the same normalization/validation/review pipeline.
A UTF-8 BOM is accepted. Limits are **2 MiB and 1,000 records per file**; empty,
invalid-envelope, malformed-header, excessive-depth and oversized inputs fail.
Record errors identify the record ordinal (excluding CSV header, not physical
line number when quoted fields span lines). Invalid rows do not reserve emails.

`NormalizedExportMapper` handles interchange columns only. `SubmissionValidator`
handles the shared answer contract, with example-domain restrictions retained for
mock fixtures and normal email validation for imported responses. `ExportImporter`
handles preview/reservations/idempotency; it does not depend on Qualtrics names.

## Import and duplicate rules

The first preview shows exclusive new/already-imported/changed/duplicate-email/
invalid categories. Permission-ineligible is an overlapping count for otherwise
accepted records. Confirmation accepts valid nonconflicting rows only. The owner
can discard the preview without importing. Import history retains timestamp,
actor UID, category counts and row-specific explanations, never raw answers or
plaintext email in Drupal tables.

- Key: case-sensitive survey ID + response ID. Identical reuploads preserve
  decisions and review timestamps. Source bookkeeping `updated_at` alone is not
  material; its displayed metadata may update without renewed review.
- Material changes to answers, identity/email, geography or submission timestamp
  reset the existing reference to pending and audit that change. They cannot
  silently retain approval. Missing rows in later files are retained.
- Accepted pending, approved, rejected and permission-ineligible responses reserve
  their normalized email within that survey. Rejected records are not a way to
  bypass duplicate detection. No provider-specific normalization is performed.
- Existing reservations always win over later files, even if the later file
  claims an earlier submission time. Within a new batch, order is submission
  instant, then stable survey/response ID. Repeated IDs within a file are invalid.
- Conflicting new response IDs are **not accepted** and are reported by row for
  owner reconciliation with the external source. They never overwrite the
  accepted response or reset its decision. Fix the normalized export with the
  legitimate existing response ID only after resolving identity/source facts;
  re-preview any material correction. There is no silent merge/replace action.
- Email comparison does not verify ownership. Imports detect reuse **after**
  collection and cannot block real-time Qualtrics submissions.
- Reserved `SYN_LIT_` survey IDs cannot be imported or changed. An identical
  existing baseline record is classified as already imported and left untouched,
  enabling the sample demonstration without granting upload approval power.
- Confirmation recomputes the preview under the same database-backed lock as
  review/publication. Changed state invalidates the preview. Concurrent stale
  previews must be retried; the existing source-reference unique key remains. A separate unique database
  reservation on survey + email digest also prevents concurrent double acceptance,
  even if a long-running operation outlives its lock. A transactional source-pointer
  comparison also rejects a stale cumulative snapshot rather than losing another
  import.

Only approved, permitted accepted responses contribute at explicit publication.
Public JSON contains geography, language marginals and canonical language-set
counts only; no emails, source IDs, raw answers, internal notes or exact submission
times. Overlapping selected languages count each response once. The public map
continues to read only the last successful aggregate snapshot.

## Private files, transactions and cleanup

All raw source snapshots and previews live outside `web/`:
`private/lit-imports/`. Do not move this directory under the web root. It is
Git-ignored. Directories are created mode 0700; files mode 0600. Uploads are parsed
from PHP's temporary upload file and never moved into Drupal public files.

Previews have random server-generated names in `staging/`, are bound to the
owner UID and expire after one hour. Only token, signature and safe summary enter
Form API state; raw answers never enter form cache or Drupal entities/tables.
Successful confirmation/cancellation removes its staged file. Expired files are
removed on the next preview or Drupal cron. Run cleanup locally with:

```sh
ddev drush cron
```

Committed cumulative source snapshots are immutable, content-addressed JSON.
Their atomic write precedes a transaction containing the current pointer,
review metadata and history. On transaction failure the old pointer remains;
an unreferenced immutable snapshot may remain private. Retain these snapshots
for recovery; no automatic deletion of committed snapshots is implemented. Plan
a retention/backup policy before real research data. PHP removes its original
upload temporary file at request end. No visitor-controlled filesystem path or
remote URL is fetched.

Publication retains its existing immutable-file/transactional-pointer strategy.
Failures preserve the last good public snapshot. Import does not publish or
change the public update date. Invitation settings are unchanged: collection
links remain external and no survey URL is invented. Disabling the invitation
does not close the external survey.

## Manual test: expected results

Sample files:

- `fixtures/lit-export/example.json`
- `fixtures/lit-export/example.csv`

Both are downloadable from the upload page and contain identical logical rows.
They are **Simulated export—not yet verified against Qualtrics**, with fictional
example-domain emails. On a fresh host whose seed timestamps differ, regenerate
these synthetic files with `python3 tools/contribution/make-export-examples.py`.

1. Sign in as owner and open Import survey responses. Upload either example and
   click **Validate and preview**. Expect new **3**, already imported **1**,
   duplicate-email **1**, invalid **2**, changed **0**, permission-ineligible **1**.
2. **Confirm valid records** accepts `SAMPLE_001`, `SAMPLE_002`, `SAMPLE_004` in
   survey `LIT_UPLOAD_SAMPLE_V1`. Only 001 and 002 appear in pending reviews;
   004 withheld publication permission. Row 3 conflicts with 001; rows 6/7 are
   invalid; row 5 is an unchanged baseline response.
3. Reupload the other format. Expect new **0**, already imported **4**, duplicate
   **1**, invalid **2**, changed **0**, permission-ineligible **1**. No new requests
   or reset decisions. Discard or confirm: neither grants approval.
4. Sign in as an administrator. Approve 001 and 002 with internal reasons.
   The public map remains at 10,000 until owner publication.
5. As owner, publish. Expected all-language union: **10,002**; English **8,939**,
   Spanish **5,885**, Korean increases by **1**. English-or-Spanish **9,560**;
   English/Spanish/Korean **9,681**. These increments occur once, even when all
   three languages are selected. All other baseline records remain unchanged.
6. Reimport/re-publish: totals stay unchanged. To test renewed review, change an
   answer on an accepted response while retaining its IDs. Preview shows changed
   **1**; confirmation returns it to pending. The next publication excludes it
   until approved again. Rejecting it also excludes it on the next publication.

These manual steps intentionally alter your local operational sample queue when
you perform them. Automated QA left no sample imports or approvals there.

## Tests and deployment into this local host

The additive updates `10003`/`10004` create source-pointer/history tables
and hashed email-reservation metadata, and add origin/permission review metadata, preserving all existing decisions.
For another checkout of this existing setup:

```sh
ddev drush updatedb -y
ddev drush cr
ddev drush php:script tests/lit_contribution/export-import-test.php
node tests/lit_contribution/export-browser-test.cjs
ddev drush php:script tools/contribution/check-upload-state.php
```

The isolated PHP suite uses SQLite and a temporary private directory; it tests
preview/import/reimport, approval by a reviewer, publication, changed/rejected
records, permission exclusion, duplicates, invalid input, failure rollback and
baseline integrity. Browser QA exercises upload previews/cancellation, downloads,
CSRF denial, anonymous/admin denial and mobile layout. It uses UID 1 emergency
access and leaves a disposable administrator blocked; it does not confirm imports
or publish on the actual site. Old tests expecting seeded pending rows in the
operational queue must instead use the explicit mock adapter. The historical
scoped workflow test has been updated to do so.

Two-process lock check (no source/review writes): start the first command in one
terminal, wait for `LOCK_READY`, then run the second in another within 15 seconds:

```sh
ddev exec env LIT_LOCK_TEST_HOLD=yes vendor/bin/drush php:script tests/lit_contribution/export-lock-probe.php
ddev drush php:script tests/lit_contribution/export-lock-probe.php
```

## Before a real Qualtrics export

Obtain a de-identified representative export and confirmed questionnaire/version,
actual source IDs/headers, multi-select encoding, submission/update timestamps and
timezones, consent semantics, geographic references and language/proficiency
choices. Implement and test a separate source-column mapper. Confirm which source
field represents stable response identity and how corrections/withdrawals are
exported. Agree on email-conflict resolution, retention, access and backup policy.
Native Qualtrics compatibility has not been verified. No API credentials, live
collection URL or production deployment are configured by this work.

### Verification results (2026-11-13)

- 50 isolated export/import/form-confirmation/review/publication checks passed.
  This includes SQL uniqueness, stale source-pointer rejection, consent
  withdrawal, re-review, source privacy and failure recovery. The live review and
  publication fingerprint was compared before/after and remained identical.
- 30 Chrome browser checks passed for both upload previews, cancellation,
  downloads, CSRF, anonymous/reviewer denials, clean queues and mobile sizing.
  Desktop preview and mobile upload screenshots were visually inspected.
- 82 existing fixed-answer validation checks and 17 isolated review/publication
  workflow regressions passed. 37 map browser regressions passed, including
  actual source/count comparisons, municipality toggles, clear/reset, refresh,
  Back/Forward, keyboard tooltips and mobile sizing.
- A separate Drush process held the mutation lock; a second was refused without
  writing records. Stale preview and database conflict defenses were also tested.
- PHP syntax checks and `git diff --check` passed. Private storage and generated
  screenshots/results remain Git-ignored; normalized schemas/examples remain
  eligible for tracking.
- Live final composition: zero operational uploads/pending requests, 100 preserved
  seeded pending references, 10,000 approved baseline responses. Publication
  still matches the original file hash and timestamp. Invitation/account settings
  were not changed; disposable browser administrators were left blocked.

Limitations: full confirmation/approval/publication was exercised against isolated
storage, including the real form submit handler, rather than mutating the live
operational queue in a browser. Local Chrome coverage is not a production or
cross-browser audit. Native Qualtrics mapping and a research-data retention policy
remain to be supplied. Previous preview failures in QA were test-client handling
of the local DDEV TLS certificate for downloads, not failed application imports.
