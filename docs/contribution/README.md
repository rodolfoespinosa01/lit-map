> Current source workflow: [CSV/JSON export upload](export-upload.md). Historical
> mock synchronization instructions below do not populate the operational queue.

> Authentication update: use [password access](password-access.md). Email-link
> login instructions below are historical and superseded.

> Current combined-count semantics and owner dashboard: [schema 3 and access verification](combined-counts-access.md). Earlier active-language-only behavior and publication versions below are historical.

> Current active demo and owner workflow: [10,000-response setup and access documentation](large-demo-owner.md). Earlier 64-response examples below are retained as the archived dataset.

# Local contribution review prototype

This work is on the `contribution` branch. Milestone 4 remains at `/lit-map/bubble-preview`; the approved-publication map is a **separate** route:

- Public synthetic aggregate map: <https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview>
- Private review queue: <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions>
- Synchronize mock source: `/admin/content/lit-contributions/synchronize`
- Publish approved local aggregate: `/admin/content/lit-contributions/publish`
- Invitation settings: `/admin/config/lit-contributions`

**No live Qualtrics connection exists.** Qualtrics remains the intended authoritative collection source. This prototype reads fictional private fixtures through a mock adapter, never accepts public survey submissions, and never contacts a Qualtrics API. Publication here means replacing a local aggregate snapshot, not deploying or pushing code.

## Local setup and walkthrough

From this project root, with the existing DDEV host installed:

```sh
ddev start
ddev composer install --no-interaction
ddev drush en lit_contribution -y
ddev drush updatedb -y
ddev drush cr
ddev drush php:script scripts/setup-contribution-demo.php
ddev drush uli
```

The setup script requires DDEV, creates a narrowly scoped `lit_contribution_reviewer` role only if absent, and synchronizes references. **New and changed responses always enter pending.** It never preapproves them or overwrites source files. The review module depends on `lit_map`. Use the existing installation instructions for a fresh empty host; do not reinstall this site's database.

1. Open the one-time administrator link printed by `ddev drush uli`; do not share it.
2. Open the private queue. The list separates submitted (source timezone), imported, reviewed and last-published timestamps. Details also show last synchronization, source update, reviewer identity, current publication inclusion, internal reasons and audit history.
3. Open a pending response. Review its private source answers and aggregate-publication permission. Choose Approve or Reject and enter an internal reason. The source answers are fetched for display, not copied into Drupal tables or public attachments.
4. `R_DEMO_061` and `R_DEMO_062` deliberately share the same normalized email in one survey. Choose one via **Choose this response; reject the other conflicting duplicates**, supply a reason, then separately approve the chosen pending response if appropriate. A rejection alone does not resolve the group. This is a review decision, **not email ownership verification**.
5. To reverse an existing decision, first **Return to pending**, then approve/reject with another reason. Every decision is audited. A signed version guard rejects an obsolete form; synchronization and a fresh review are required if source answers changed.
6. A user with publication permission opens Publish and confirms. Only approved, present, permitted and duplicate-resolved submissions contribute. Review changes do not silently rewrite the last publication; confirm another publication to reflect rejection/removal/withdrawal.
7. Open the public map. Select languages; its update date has day precision only. Select Bergen and Show towns/cities to inspect the Allendale examples. The prior milestone map remains a larger fabricated aggregate fixture for dense-map regression testing; its counts do not come from the review queue.
8. Invitation is **OFF** by default. Enable it only with a configured valid HTTPS Qualtrics survey link. The prototype validates the URL format but does not contact it. **Disabling the invitation does not close the survey in Qualtrics.**

Reviewer access is granted through Drupal People → Roles/Permissions. Permissions are separate: `review lit contributions`, `synchronize lit contributions`, `publish lit contributions`, `administer lit contribution settings`. They are restricted permissions; none is assigned to anonymous or ordinary authenticated users. The reviewer role gets only review and access-content permissions. Drupal Form API provides CSRF protection; all mutations require authenticated permissions in the service layer as well as the route. Settings uses a permission-protected ConfigFormBase form. Private responses are `private, no-store`, not indexed, and use no-referrer headers.

## Provisional fixed-answer contract

`provisional-survey-v1.schema.json` is the versioned illustrative interchange schema. It is **not a confirmed research questionnaire**, translation, validated instrument, language ranking, or estimate of population. Its JSON Schema is accompanied by executable PHP validation for source identity, valid dates, reserved email domains, and municipality/county relationships.

The fixture at `fixtures/lit-contribution/submissions.v1.json` is outside Drupal's `web/` document root. It is tracked only because it is explicitly synthetic: 64 fictional submissions, reserved `example.org` addresses, eight provisional languages, yes/no/missing cases, varied counts, unspecified municipalities, denied publication permissions, and a deliberate duplicate pair. Never put real exports in this tracked fixture directory. Generate only into a missing file with `python3 tools/contribution/generate-mock.py`; verify existing bytes with `--check` (the generator refuses to overwrite existing work).

Each submission supplies:

- `schema_version: 1`, survey ID, case-sensitive response ID, private email, submitted and source-updated ISO timestamps with explicit offsets (not bare local times).
- Geography: known NJ county GEOID and a matching municipality key, or null for unspecified municipality. Existing verified Census mappings are reused.
- First languages: unique provisional language codes; an empty array means no first-language answer recorded, not that the respondent has no first language.
- Per-language fixed answers: uses language (`yes`, `no`, `prefer_not_to_answer`); speaking and signing proficiency (`none`, `basic`, `conversational`, `advanced`, `fluent`, `not_applicable`, `prefer_not_to_answer`); home use; other settings (`work`, `education`, `friends`, `community`, `online`, `religious`, `none`, `prefer_not_to_answer`); frequency; language brokering.
- Aggregate-publication permission: explicit `yes` or `no`. Approval cannot override `no`.

The eight demonstration codes are en, es, ko, pt, zh, ar, hi and ase. The `zh` grouping is explicitly provisional, not a claim about how Chinese languages should be represented in the research instrument. The data represents fictional **survey responses**, never unique people. Many language answers can come from one submission.

For the public metric, approved/permitted `uses_language=yes` contributes **one** response; an explicit `no` creates an observed **zero** when no yes response exists in the same bucket. `prefer_not_to_answer` and absent language records contribute no observation. Entirely unobserved buckets remain missing. County totals sum municipality and unspecified buckets once; there is no additional county-total response record. Other answers remain private and do not implicitly change the metric.

## Source and storage boundaries

`SubmissionSourceInterface` exposes `listSubmissions()` (a complete validated snapshot) and `getSubmission(survey, response)` (current submission or confirmed absence). The injected implementation is `MockQualtricsSource`: a fixed module-independent fixture path with no visitor path, URL, HTTP client, credentials, or network integration. It validates every record and rejects duplicate source IDs before synchronization starts.

Drupal stores only these private operational records:

- `lit_contribution_review`: survey/response references, canonical material fingerprint, keyed email digest, status/presence/duplicate fields, source and workflow timestamps, reviewer UID, internal reason and last-publication inclusion.
- `lit_contribution_audit`: source-reference row ID, action, actor UID, time and internal reason.
- `lit_contribution_publication`: one active immutable-file hash, publication time and publisher UID.

There are no response-answer, proficiency, email-text or survey-count entity tables. The keyed email digest uses Drupal's private key and is sensitive metadata, not a public anonymization guarantee. Normalization trims and lowercases the address; it **does not remove provider-specific dots or plus addressing**. Matching is scoped to survey. A same-email group is conservatively treated as a conflict even if the answers look alike. A person may share an address or provide someone else's address; this cannot establish ownership or unique identity.

Synchronization is transactional and idempotent by exact survey + response ID, with a database unique constraint. New/materially changed/reappearing responses become pending. Canonicalization sorts unordered choices; casing/outer whitespace normalization of email and source bookkeeping-only `updated_at` changes do not invalidate an unchanged response. Changed answers, consent, email, geography or submitted timestamp do. A change in a duplicate group invalidates its resolution and returns affected grouped responses to pending. Confirmed removals become absent/pending, never silently deleted. Full snapshot failure does not mark unseen records removed.

All mutations share a Drupal lock. Publishing compares the current complete source snapshot to synchronized fingerprints and fails rather than publishing unsynchronized changes/new/removal. It reads approved responses from the adapter, aggregates in memory, validates the existing public aggregate v2 contract, and writes **only aggregates** to ignored `private/lit-contribution/` outside `web/`.

Publication files are immutable, SHA-256-addressed JSON. Writes use a same-directory temporary file, complete write/flush/fsync, and atomic rename. Only after writing succeeds does one database transaction change the active file pointer and publication metadata. A file/validation/database failure leaves the previous pointer and last good publication intact; an orphan aggregate file may remain after a failed transaction, but is not active or web-accessible. Old files are retained locally; production retention/garbage collection is future work. Review metadata records the last time each submission was included, distinct from whether it is included now.

The public route reads only the active aggregate file, validates it, and attaches only normalized county/municipality/language counts and vetted geometry through the existing scoped `drupalSettings` structure. It never calls the submission adapter. The publication includes only an update **date**, not exact submission/publication times, response IDs, email, raw answers, reviewer identities or reasons. The route and render data are uncacheable for this prototype; a refresh sees a new successful snapshot, while an open tab remains a coherent snapshot. Private source answers are outside the Form API render subarray, so form caching cannot persist them.

## Categorical bubble appearance

Both bubble-based previews reuse the same map behavior. **Area remains the active language's response count**, with the existing minimum/maximum radii and distinct county/municipality scales. Adding another comparison language never increases the size by adding its count.

Colors show selected languages with **positive** counts in that particular place:

- One match: its stable solid color.
- Two through six matches: equal angular segments, one per matching language, regardless of each language's count.
- More than six matches: a neutral disc with a separate `+` indicator. The accessible callout enumerates every matching language and the results panel keeps every exact count. This avoids unreadable narrow wedges or falsely suggesting shares.
- Active-language zero: hollow ring; positive other-language categories can appear on its outline. Missing active-language count: no bubble, but other-language counts remain in the results.

Colors are stable by language code, with an explicit eight-color palette and deterministic fallback for future codes. The text legend names every selected language. Full bubble accessible names state the active-size language, exact count and all positive selected languages. Color is never the only means of identifying a language. Motion/reduced-motion controls, selected callouts, viewBox navigation, URL state and keyboard/touch alternatives remain in the shared component.

## Verification commands

```sh
ddev drush php:script tests/lit_contribution/integration.php
ddev exec php tests/lit_contribution/validator-test.php
ddev exec php tests/lit_contribution/lint.php
node tests/lit_contribution/colors-test.cjs
# Only on this local test host: creates random-password QA accounts, never emails.
ddev drush php:script tests/lit_contribution/browser-accounts.php
node tests/lit_contribution/browser-test.cjs
ddev drush php:script tests/lit_contribution/privacy-check.php
node tests/lit_map/bubble-browser-test.cjs
node tests/lit_map/browser-test.cjs
ddev exec php tests/lit_map/normalizer-test.php
python3 tools/contribution/generate-mock.py --check
```

Use the existing development Playwright Core driver via `LIT_PLAYWRIGHT_PATH` as documented for M4. Integration tests run inside a database transaction and roll metadata back; immutable synthetic aggregate files may remain in ignored private storage. Do not run synchronization/publication tests concurrently with real review activity. Browser QA exercises real Form API approval/publication of fictional records; its final demonstrated approvals remain visible for the local walkthrough. It temporarily configures an invitation, never follows that link, and restores the invitation settings. QA screenshots/reports are narrowly ignored. See `verification.md` for actual results and local final state.

## Requirements for a real Qualtrics adapter

No API endpoint, account, credential or live tenant has been invented. A real connection needs:

1. An authorized Qualtrics account/tenant/data-center and confirmed survey IDs, supported API access and server-only credentials with the required read scopes. Store secrets outside Git/config exports and the public web root. The invitation URL is a separate setting, not an API credential or connection test.
2. The confirmed research questionnaire, consent language/version, language taxonomy, completion criteria and field-ID mappings approved by the survey owners. Replace the mock-only reserved-domain validation and synthetic-only public contract deliberately; do not relabel fixture values as real observations.
3. Stable survey/response identities, explicit-offset submitted/updated timestamps, current email and aggregate permission, valid geographic references, fixed-answer mapping, and documented treatment of missing/declined/withdrawn responses. Unknown values must be quarantined, not guessed.
4. Complete, consistent pagination/export snapshots, authenticated detail lookup, rate-limit/retry/timeouts, monitoring and explicit deletion/withdrawal semantics. A partial export, API outage or inaccessible survey must never be interpreted as removal. A future paginated implementation must materialize a verified complete snapshot before calling this synchronization boundary.
5. An operational schedule for synchronization, review and republishing; independent reviewer/publisher role assignment; audit/retention/key-rotation decisions; and research-approved aggregation/disclosure rules. This prototype has no small-cell suppression or live withdrawal automation.
6. Staging verification against authorized test surveys, privacy/access tests, error recovery and production hosting/caching verification before enabling a real collection invitation. Closing a survey itself must be performed in Qualtrics.

Implementation follows Drupal's [Form API](https://www.drupal.org/docs/drupal-apis/form-api/introduction-to-form-api), [CSRF form protection](https://www.drupal.org/docs/8/api/routing-system/access-checking-on-routes/csrf-access-checking), and native permission-based routing. No additional Composer or browser runtime dependency was installed. Drupal 11 remains untested.
