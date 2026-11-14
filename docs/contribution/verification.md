> Authentication update: use [password access](password-access.md). Email-link
> login instructions below are historical and superseded.

> Current combined-count semantics and owner dashboard: [schema 3 and access verification](combined-counts-access.md). Earlier active-language-only behavior and publication versions below are historical.

# Contribution prototype verification — 2026-11-13

Implemented and exercised only on the existing **contribution** branch. Initial working tree was clean. No applicable AGENTS.md was found in the project or parent directories. README, milestone reports, services, templates, JavaScript, assets and tests were inspected; Milestone 4 was present. No branch creation, staging, commit, push, deployment, Composer change, PHP/MariaDB configuration change or geographic asset replacement was performed.

## Local URLs and final demonstration state

- Approved aggregate map: <https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview>
- Private queue: <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions>
- Invitation settings: <https://rutgers-lit-m1.ddev.site/admin/config/lit-contributions>
- Existing Milestone 4 preview: <https://rutgers-lit-m1.ddev.site/lit-map/bubble-preview>

Use `ddev drush uli` for local administrator login, then follow the walkthrough in `README.md` in this directory. No credentials or one-time login tokens were saved in tracked files.

The local source contains **64 fictional submissions**. Browser QA approved `R_DEMO_001` through `R_DEMO_008` through Drupal's reviewer form and published them locally as administrator. Those demonstration approvals remain visible: **8 approved, 56 pending**. The deliberate duplicate pair `R_DEMO_061`/`R_DEMO_062` remains unresolved/pending for manual review. Other responses, including denied-consent examples, await review. Every record was initially imported pending; none was silently preapproved by the setup script.

Public update date: **2026-11-13 (UTC date)**. Current fabricated Bergen counts are English 7, Spanish 5, Korean 2, Portuguese 6, Chinese (provisional grouping) 5, Arabic 4, Hindi 7 and American Sign Language 4. These are overlapping per-language survey-response counts, not eight additive categories of unique people. The first response has unspecified municipality; remaining approved responses are Allendale examples. Other source locations become visible only after appropriate approval and publication.

Invitation is restored to **OFF**, configured URL **empty**. The temporary syntactically valid URL used to test the invitation was never followed. **No live Qualtrics API connection exists.**

A local `lit_contribution_reviewer` role has only review/access-content permissions. QA accounts `lit_qa_reviewer` and `lit_qa_viewer` were created with random, unsaved passwords and reserved-domain emails; the former has the reviewer role, the latter only ordinary authenticated access. No credentials were emailed. These are local QA accounts, not real contributors or deployment configuration.

## Checks actually exercised

| Check | Result |
| --- | --- |
| `ddev drush php:script tests/lit_contribution/integration.php` | **55 passed**, test metadata rolled back |
| `ddev exec php tests/lit_contribution/validator-test.php` | **82 passed** |
| `node tests/lit_contribution/browser-test.cjs` | **47 passed**, no public-map JS errors |
| `node tests/lit_contribution/colors-test.cjs` | **8 passed** |
| `ddev drush php:script tests/lit_contribution/privacy-check.php` | **5 passed** across metadata, current form-cache inspection, final invitation settings and public payload |
| Existing `tests/lit_map/bubble-browser-test.cjs` | **49 passed** |
| Existing `tests/lit_map/browser-test.cjs` | **41 passed** |
| Existing `tests/lit_map/normalizer-test.php` | **46 passed** |
| Existing `tests/lit_map/bubble-model-test.cjs` | **16 passed** |
| `python3 tools/contribution/generate-mock.py --check` | Exact fixture reproduction passed |
| PHP and JavaScript syntax | New module PHP files linted; shared bubble JavaScript parsed successfully |
| `git diff --check` | Passed; no diff in existing geography assets/data, Composer manifests/lockfile or DDEV configuration |

Generated integration/browser reports and screenshots are ignored under explicit `docs/contribution/` paths. The schema, instructions, this authored report, tests, generator and synthetic private fixture remain eligible for Git tracking.

### Review, synchronization and private source

- Anonymous service access and HTTP list/detail/sync/publish/settings requests denied. Ordinary authenticated users cannot view the queue. Reviewer role can list/read/decide, but cannot synchronize, publish or configure the invitation.
- Actual private detail HTTP response uses `private, no-store`. Fixed fixture and private storage URL guesses return 403/404; the fixture is outside `web/`.
- Reviewer identity and source offset are displayed; imported/reviewed/published fields are distinct. Source answers appear only on the authorized private detail screen. The raw source display is outside the Form API subarray; current Drupal form-cache entries and metadata tables were inspected for raw answer keys/plaintext source emails and none were found.
- Missing-CSRF-token POSTs to review, synchronize and publish are explicitly rejected by Drupal as outdated forms. A valid reviewer form approves; valid synchronize/publish confirmation forms work. Signed stale review tokens are rejected after a decision and rechecked inside the mutation lock; changed/absent source fingerprints prevent approval of obsolete content.
- Survey + response identity is database-unique and case-sensitive, including two IDs differing only by case. Repeated sync creates no duplicate rows, preserves first-import time and unchanged approvals. Source bookkeeping-only `updated_at` changes preserve approval; material answer changes reset pending and clear current reviewer/time while preserving the audit trail.
- Email matching trims/lowercases per survey; dots and plus tags remain distinct. Same email across different surveys is not a conflict. Conflicting same-survey responses cannot be approved until resolution. Resolution leaves the chosen response pending, rejects alternatives, survives unchanged sync, and is invalidated by a changed group member. This does not verify ownership.
- Invalid source snapshots fail before metadata changes. Unknown references, mismatched municipality/county, unsupported enums, arbitrary extra fields, duplicate first-language choices, contradictory none/other settings, invalid calendar dates, missing/invalid timezone offsets, backwards updated timestamps and non-reserved mock email domains are rejected.

### Publication and public privacy

- Independently computed per-language totals match approved/permitted responses; approved responses without aggregate permission are excluded. Unspecified municipality contributes once. Explicit no answers create zero observations; unobserved places remain missing.
- Re-review/pending, rejection and confirmed source removal disappear from subsequent publications. Only the explicitly chosen, separately approved duplicate contributes. The changed-duplicate case invalidates previous resolution.
- Unsynchronized new/changed/removed source content cannot be published. A deliberately injected file-write failure and a changed-source validation failure preserve the prior active aggregate exactly. Database metadata changes during integration tests are rolled back, leaving the browser-demonstrated snapshot intact.
- Publication consists of an immutable aggregate JSON file and a transactional active pointer. No response IDs, email, source times, reviewer data, internal reasons or raw answers appear in the public HTML/settings. Public update information is date-only. Public rendering reads the aggregate, not the private adapter.
- Invitation defaults off; invalid/non-Qualtrics/unsafe URLs are rejected, a configured valid HTTPS Qualtrics survey URL appears only when enabled, and disabling hides it. Settings restored to off/blank. No survey URL was contacted; hiding a link does not close a Qualtrics survey.

### Map rendering and regressions

- One positive selected language produces a solid color; two and three produce equal segments. Actual SVG segment path lengths were compared to verify equal geometry, not count-proportional wedges.
- More than six positive matches produces the neutral `+` fallback; all matching names remain in the accessible bubble label and results. The legend names all eight selected colors.
- Places with only a subset of selected positive counts get exactly that subset of colors. Missing and zero records are not positive categories.
- Adding comparison languages leaves the active-language radius unchanged. Existing map tests retain active-language switching/fallback, county/town order, navigation, pan/zoom bounds, drag-versus-click, touch scrolling, reset, URL refresh/Back/Forward, motion preference, offscreen pause, keyboard actions and reduced motion.
- The approved map and mobile legend were checked at 390×844 with no horizontal overflow. Reduced motion disables animation there. The original Milestone 3 behavior still passes its existing browser tests.

## Actual visual inspection

Opened actual Chrome screenshots for the private detail, approved desktop map, approved mobile map/legend, and dense Bergen categorical map. The private screen shows separate timestamps, reviewer identity, safely rendered source answers, decisions and audit history using native Drupal controls. On the public map the synthetic notice and update date are clear; the approved sample has small bubbles because its counts are small and the original radius scale is preserved. The dense prior-milestone fixture makes the one/two/three-color categorical divisions clearly visible. County/municipal boundaries remain recognizable and unchanged. Mobile controls and legend wrap without horizontal overflow.

Screenshots: `private-detail-desktop.png`, `three-languages-desktop.png`, `many-languages-desktop.png`, `categorical-dense-desktop.png`, `approved-mobile.png`, `legend-mobile.png`, all under ignored `screenshots/`.

## Limitations and unperformed checks

- This is a **mock-only local prototype**, not a confirmed questionnaire or connected survey workflow. Future account/API authorization, actual field mapping, consent/disclosure rules, complete export/pagination semantics, retries, deletion/withdrawal handling and monitoring remain required. See the real-adapter requirements in the README.
- Source listing is a complete in-memory snapshot and the administrative list is small-scale; large surveys, concurrent multi-worker operation and scheduled synchronization were not load-tested.
- Duplicate matching does not prove identity or ownership. Publication changes are explicit/manual; a new snapshot is required to remove a previously published response after a review change. No live consent-withdrawal automation or small-cell suppression is implemented.
- File-write and source-validation failure recovery were injected and exercised. Disk power-loss, database commit failure, and multi-process crash recovery were not fault-injected. Immutable orphan/old aggregate files may remain in ignored private storage; production retention/cleanup is not implemented.
- Chrome desktop/mobile emulation was exercised. Physical phones, Safari, Firefox, screen-reader sessions, full accessibility conformance, Drupal 11 and deployment environments were not tested.
- Small counts remain at the existing minimum radius, so categorical wedges can be tiny; native selectors, named legend, callout and exact text results provide the readable alternatives. Dense bubbles still overlap at county-wide extent. Equal segments are categorical indicators, never population shares.
- Expected rejected-form tests and earlier fixed development errors remain in local Drupal logs. Final end-to-end tests pass; no log deletion was performed.

## Changed files and local storage

New `web/modules/custom/lit_contribution/` module:

- Module metadata, services, permissions, routes, admin menu links, default invitation config/schema.
- Install schema and update hook for the three metadata tables and case-sensitive source identifiers.
- Source adapter interface and fixed-path mock adapter.
- Fixed-answer validator, review manager, atomic aggregate writer, publication and invitation services.
- Private admin/public map controllers, Drupal decision/sync/publication/settings forms, and private-response header subscriber.

Updated shared `lit_map` files: bubble data service accepts validated approved metrics; theme variables/template provide publication date/invitation and categorical explanation; bubble model/JavaScript/CSS implement stable colors, equal segments and fallback while preserving size/state/navigation.

New source/operational material: `fixtures/lit-contribution/` (synthetic private source + README), `tools/contribution/generate-mock.py`, `scripts/setup-contribution-demo.php`, `tests/lit_contribution/`, and `docs/contribution/` schema/instructions/verification. Root README and narrowly scoped QA ignore rules were updated.

Local database additions are only `lit_contribution_review`, `lit_contribution_audit`, `lit_contribution_publication`, native role/account/config records and routine Drupal caches/logs. No survey-answer or metric entity tables, taxonomy or Paragraph packaging were added. Aggregate publications are under ignored `private/lit-contribution/`, outside the public root. Existing verified geography, public synthetic fixtures, licenses, Composer/scaffold strategy and unrelated work were preserved.

## Count/breakdown follow-up

See [the read-only count investigation, exact fixture reconciliation and new checks](count-reconciliation.md).
The follow-up preserves the demonstration source, decisions and publication.

## Opening experience follow-up

See [preset, bubble visibility and isolated invitation verification](opening-experience.md).
The approved publication and actual invitation settings remain unchanged.

## 10,000-response and owner follow-up

See [active dataset, owner/admin access, isolated workflow and verification](large-demo-owner.md).
Earlier sparse-demo evidence remains historical; the legacy decisions and publication are archived intact.
