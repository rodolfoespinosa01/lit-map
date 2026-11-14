> Current questionnaire, selected baseline, dated aggregates and sample downloads:
> [questionnaire v2](questionnaire-v2.md). Older counts/instructions below are
> retained as historical version-1 context; existing records remain supported.

> Authentication update: use [password access](password-access.md). Email-link
> login instructions below are historical and superseded.

> Current passwordless-only walkthrough, editorial resources and updated bubble styling: [resources and access](resources-passwordless.md). The core Drush login command below is an emergency account/reset workflow, not the normal LiT sign-in.

# Combined response counts and owner access

Current contribution implementation, verified locally on November 13, 2026.
This supersedes active-language-only contribution counts and the previous login
landing page. Earlier milestone previews retain their version-2 behavior.
No commit, push, deployment, branch change, dataset reset, or review decision
change was performed. `informational` remains untouched.

## Aggregate contract

The former marginal-only schema could not determine overlapping submissions.
The contribution publisher now writes **publication envelope version 2 / aggregate
schema 3**. Its only envelope fields are `publication_version`, `updated_date`
(UTC date, not an individual timestamp), and `aggregate`.

The aggregate has exactly `schema_version: 3`, `synthetic: true`, `records`, and
`groups`. `records` retains the [version-2 marginal record contract](../survey-format.md)
to preserve explicit zero versus missing. Each group has exactly:

```json
{
  "county_geoid": "34003",
  "municipality": null,
  "languages": ["en", "es"],
  "response_count": 40
}
```

This illustrative group means 40 eligible approved submissions in Bergen with
unspecified municipality reported English and Spanish, and no other positive
language. It is not an additional county total. A municipality ID is from the
existing verified catalogue; `null` means unspecified. Language IDs are unique,
lexically sorted, and limited to known languages. Count is a positive safe integer.
Identical geography/language combinations are merged. Each included submission
belongs to exactly one group. An empty language list is permitted for an eligible
submission reporting no positive language: it reconciles publication membership
but contributes to no selected-language union.

The PHP normalizer rejects unknown fields/references, duplicate or noncanonical
groups, municipality/county mismatch, invalid counts and unsafe sums. It checks
that expanding groups into positive marginal counts exactly matches `records`.
Marginal zero still means an explicit negative answer; missing or declined answers
do not invent zero. A selected set is known where at least one selected language
has a marginal record; otherwise the display says **No data available**.

Only validated labels, geography references, marginal counts and normalized
`combinations` are attached to `drupalSettings`. Normalized group fields are
`county`, `municipality` (`_unspecified` for null), `languages`, and `count`.
No email, source/response ID, raw profile, reviewer or individual timestamp is
included. The public controller reads only the private immutable aggregate file,
never the source adapter. It refuses a marginal-only contribution publication
rather than approximating overlaps. Legacy previews still accept schema 2.

The browser sums disjoint groups whose language set intersects the selection.
Each matching group contributes once, regardless of how many selected languages
it contains. County totals combine all municipality groups plus unspecified;
no separate county aggregate is added. Bubble area, headline, tooltip and panel
use the same union count: **Survey responses reporting ANY selected language**.
Individual selected-language counts remain available with an overlap explanation.
These are distinct submissions, not verified unique people or population estimates.

The active-language selector is removed from this preview. Old `lit_active` values
are ignored and removed on the next state write; valid language/place selections
remain. English opening preset, explicit clear, reset, unrelated query parameters,
Back/Forward, motion/reduced motion, USA navigation and city-mode clearing remain.
Positive selected languages still determine equal categorical color segments,
not shares. More than six positive languages uses the existing neutral disc and
`+` indicator, with the complete text list/legend. Size uses the existing bounded
square-root scale, now fed the combined count; minimum/maximum radii still apply.

## Publication and reconciliation

The new local immutable publication is:
`private/lit-contribution/e29b3b40207ad44904ccf3457accda092d878c5c6fd9396370e85a358bd1a0cb.json`.
Published by owner UID 4 at **2026-11-13 18:10:20 UTC**. It has **6,406 disjoint
groups** and **4,528 marginal records**, representing **10,000 eligible approved
submissions** from `SYN_LIT_10000_V1`. The previous schema-2 file and archived
64-response dataset remain intact. The 100 pending responses remain pending;
duplicate and permission exclusions are not overridden.

The explicit upgrade script `tools/contribution/publish-unions.php` published
without synchronization or decisions. It compared review metadata (excluding
publication membership/time) and review audit rows before/after. Both fingerprints
were `7a499f8c9d0bb14e742d97827333d60dd5d0de821a8420f097035ce4cbf069f6`.
Source reproducibility was checked with the existing generator's `--large --check`.

Independent source iteration, published groups and frontend unions agree:

| Selected languages | All counties | Bergen | Middlesex | New Brunswick |
|---|---:|---:|---:|---:|
| English | 8,937 | 1,435 | 1,252 | 631 |
| Spanish | 5,884 | 952 | 791 | 408 |
| English + Spanish | 9,558 | 1,542 | 1,336 | 670 |
| English + Spanish + Korean | 9,679 | 1,550 | 1,353 | 678 |
| All eight languages | 10,000 | 1,600 | 1,400 | 700 |

For example statewide English/Spanish overlap is 5,263, so the combined count
is 8,937 + 5,884 − 5,263 = 9,558. Every baseline eligible response reports at
least one of the eight languages; the full union therefore reconciles to 10,000.
All county and municipality buckets, including unspecified, were compared.

Final reconciliation reports 10,100 source records, 100 pending, zero rejected,
two duplicate-excluded and ten permission-excluded records. The exclusion counts
overlap the pending category; they are not additional deductions from 10,000.
There are zero unsynchronized records and zero review audit events after (or in
the same second as) this publication. The exact review/audit fingerprint remained
unchanged after authentication and isolated workflow testing.

Publication still validates first, atomically writes an immutable file, then
updates its database pointer transactionally. Failure preserves the last good
publication. Public render/page responses remain uncached and re-read/validate
the pointed-to file, so successful publication is visible on refresh. Private
review changes alone do not change the map. Reconciliation now reports actual
envelope/schema versions, grouped submission total and full-language union.

## Owner identity and access

Exactly one existing account has `ree32@scarletmail.rutgers.edu`: **ree32, UID 4**,
active, designated sole application owner. No account was merged/created or email
changed to establish this identity. **admin, UID 1** remains emergency administration.
Anonymous/general authenticated roles receive no review or owner permissions.

The previous experience sent every successful sign-in to the review list and
lacked an obvious dashboard/account navigation. Owner sign-in now lands at
`/admin/lit`; other authorized administrators land at the **pending-only** queue.
Account navigation shows the username, appropriate dashboard/queue link and
logout, and supplies sign-in on access-denied pages. Owner dashboard links to
administrator management, pending/all reviews, publication, settings,
reconciliation, synchronization and the map.

Owners can add/deactivate/reactivate administrator emails, review, change settings
and publish. Other administrators can review and approve/reject; owner checks
deny administrator management, settings, synchronization, reconciliation and
publication. Sole-owner entity guards, audit events and deactivation revocation
remain. Drupal adds `check_logged_in=1` after login to verify session cookies;
tests accept this core parameter while checking the fixed destination path.

Sign-in remains one email-entry page, with generic responses and no session
created from entering an address. Authorized users receive a 15-minute, single-use
link; confirmation consumes it and establishes a normal Drupal session. Tokens
remain hashed in storage and are removed from URL history before a POST; none
appear in this report. Throttles, safe fixed redirects, CSRF-protected actions,
account-state checks and revocation on deactivation remain enforced.

## Verified local URLs and browser walkthrough

| Purpose | URL |
|---|---|
| Public map | <https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview> |
| Email sign-in | <https://rutgers-lit-m1.ddev.site/lit/sign-in> |
| Owner dashboard | <https://rutgers-lit-m1.ddev.site/admin/lit> |
| Manage administrator emails | <https://rutgers-lit-m1.ddev.site/admin/people/lit-administrators> |
| Pending reviews | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/pending> |
| All reviews | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions> |
| Publication | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/publish> |
| Settings | <https://rutgers-lit-m1.ddev.site/admin/config/lit-contributions> |
| Reconciliation | <https://rutgers-lit-m1.ddev.site/admin/content/lit-contributions/reconciliation> |
| DDEV Mailpit | <https://rutgers-lit-m1.ddev.site:8026> |

Mailpit's URL was obtained from `ddev describe -j`; PHP mail uses the local Mailpit
sendmail transport at `127.0.0.1:1025`. **Messages are captured locally and are NOT
delivered to the actual Rutgers mailbox.** No production mail or live Qualtrics
integration is configured. Invitation remains OFF, URL blank.

1. Run `ddev start`. Open Sign-in and enter `ree32@scarletmail.rutgers.edu`.
2. Open Mailpit, open the latest “LiT local administrator sign-in” message to that
   address, follow its link, and choose **Confirm sign-in**. The dashboard opens.
3. Use **Manage administrator emails** to authorize a fictional `example.org`
   address. In a separate browser context, request its own link, retrieve it from
   Mailpit and confirm. It lands on pending reviews and cannot publish/settings.
4. Review decisions require an internal reason. To preserve this demonstration,
   do not save a decision merely to inspect the form. When deliberately changing
   a decision, the owner subsequently reviews reconciliation and publishes.
5. On the map, select English + Spanish: Middlesex is 1,336. Enable cities and
   choose New Brunswick: 670. Disable cities: the city filter clears and the
   county count immediately returns to 1,336. Refresh/Back/Forward preserve state.
6. Use **Log out** in account navigation and confirm. Owner can deactivate test
   administrators; deactivation revokes existing sessions and unused links.

Local owner recovery/login command, using the verified account identifier:

```sh
ddev drush user:login --uid=4 --no-browser --uri=https://rutgers-lit-m1.ddev.site
```

Open the locally printed one-time link privately; do not paste it into reports.
UID 1 emergency command uses `--uid=1`. These do not transfer ownership; the
existing explicit recovery script remains documented in [ownership setup](large-demo-owner.md).

## Verification and reproducibility

Run from the project root after DDEV startup:

```sh
ddev exec php tests/lit_contribution/union-test.php --export
node tests/lit_contribution/union-model-test.cjs
ddev drush php:script tests/lit_contribution/union-live-check.php
node tests/lit_contribution/union-browser-test.cjs
node tests/lit_contribution/access-browser-test.cjs
```

Browser checks use the existing development-only Playwright/Chrome installation;
there is no browser mapping dependency or frontend build pipeline. Generated DTOs,
source-expected results and test logs live in ignored `private/union-qa/`;
screenshots in ignored `docs/contribution/screenshots/`. Keep test scripts tracked.
Access tests create a uniquely named reserved-domain administrator, record normal
access audit events and leave it inactive. They send Mailpit messages and exercise
normal throttles; do not repeatedly run them within the throttle windows.

- Deterministic fixture: **20 PHP validation/union checks**, **12 JS model checks**.
  English 100, Spanish 60, overlap 40 => union 120, not 160. Three-language union
  130; municipality 105 plus unspecified 15 => county 120. Explicit Arabic zero
  and missing Hindi remain distinct. Two no-positive-language submissions are
  retained in publication membership but excluded from language unions.
- Source-to-public reconciliation covers single/two/three/all-language sets for
  every county and municipality; frontend browser assertions use independent
  source-derived expectations. **37 union browser checks** cover headline/numbers,
  individual counts, tooltip keyboard focus, cities off, URL/history/refresh,
  preset/clear/reset, privacy and mobile overflow.
- **46 access browser checks** cover actual owner email → Mailpit → confirmation
  → dashboard, a newly authorized fictional administrator → pending queue,
  private/owner-only denial, tokens, throttles, CSRF, deactivation/reactivation
  and logout. The actual fictional administrator also approves in an **isolated
  SQLite/file-store workflow**, with owner publication and rejection/removal.
  No live demonstration decision is changed by this approval test.
- Regression checks: **49** legacy map browser, **46** schema-2 normalizer,
  **82** private source validator, **11** prior deterministic aggregation,
  **17** isolated review/publication/failure recovery, **9** isolated invitation,
  **6** owner/privilege guards, **8** categorical colors, **16** bubble model,
  **10** opening model; **25** contribution PHP files linted; `git diff --check`.
- Desktop/mobile map screenshots were inspected for recognizable geography,
  visible count labels, segments, callouts and overflow. Owner dashboard and mobile
  sign-in screenshots were also visually inspected. The dashboard displays ree32,
  logout and all owner-action links; the email form fits the mobile viewport and
  clearly explains Mailpit-only delivery. The sole-owner protection test deliberately
  triggers a caught rejection; its logged error is expected, not a failed check.

Limitations: Chrome desktop/mobile emulation, not a physical phone, Safari/Firefox
or a screen-reader session. Owner transfer and a separate fresh installation were
not exercised. No real mailbox delivery or live Qualtrics integration was tested.
Older active-language-only and English=7 browser scripts describe archived previews
and were not applied to this publication. The existing mock-adapter replacement
requirements remain in [ownership setup](large-demo-owner.md#production-requirements).

## Changed areas

Aggregate builder/publication/reconciliation; schema-3 normalizer; shared map
model/behavior and contribution Twig instructions; owner dashboard controller,
pending queue, routes/menu, sign-in forms, account navigation/styles; explicit
upgrade script; deterministic/source/browser/access tests; root README and survey/
contribution documentation. No Composer dependency, geometry, database schema,
review decision, actual invitation setting or source fixture changed in this work.
