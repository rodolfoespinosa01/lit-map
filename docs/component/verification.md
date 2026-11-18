# Component verification — November 18, 2026

This record describes checks actually run during the component refactor. It is
not a claim of full accessibility conformance, every Drupal 11 minor version, or
production deployment readiness.

## Environments and installation paths

| Host | Core / PHP | Exercised path |
|---|---|---|
| Working `rutgers-lit-m1` | Drupal 10.6.21 / PHP 8.3 | Additive component updates; normal page `/node/3`; original application state preserved |
| Disposable `lit-component-d10` | Drupal 10.6.21 / PHP 8.3 | Fresh minimal installation without contribution; then original pre-component database backup imported and additive updates exercised |
| Disposable `lit-component-d11` | Drupal 11.4.13 / PHP 8.4 | Fresh minimal installation without contribution; then original database backup upgraded only in this disposable host and additive component updates exercised |

The disposable directories are `/private/tmp/lit-component-d10` and
`/private/tmp/lit-component-d11`, with separate DDEV databases. The working
Composer manifests/lock and core were not upgraded. Both fresh installations
created the normal demo page at `/node/1`; both backup-copy migrations created it
at `/node/3`. They are local test hosts, not deployments.

Drupal 11 was selected using Composer in its own project. Official requirements
were checked against Drupal's [PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements)
and [Composer requirements](https://www.drupal.org/docs/getting-started/system-requirements/composer-requirements).
The selected version installed with Paragraphs 1.23.0 / Entity Reference Revisions
1.15.0 and Drush 13.8.1. Composer's platform check was run in that host.

The additive updates are `lit_map_update_10001`, `lit_map_update_10002` and
`lit_map_post_update_component_architecture`. Repeated updates reported no pending
updates; repeated term seeding/resource migration did not duplicate content or
replace existing editorial values. The reference seed includes a one-time
historical alias upgrade followed by preservation on subsequent runs.

## Executed checks

- `component-integration.php`: transactional tests on both core versions, fresh
  standalone and migrated sites. Checks canonical terms, stable/duplicate key
  validation, ambiguous Chinese/Mandarin/Cantonese mapping, taxonomy label
  propagation, historical aliases, restrictions on counts and combination keys,
  entity/list cache tags, scoped existing-owner resource access, published Media, unpublished Media, disabled canonical
  Media access, HTTPS/transcript link rendering, Paragraph/publication invalidation,
  and unchanged publication groups/pointer. The migrated test executes over a
  thousand assertions because every restricted combination is checked; the fresh
  informational test has no contribution combinations. All temporary entity/config
  edits roll back.
- `component-browser-test.cjs`: passed on both core versions and both installation
  paths. Two normal-page maps, county/language restrictions, disabled municipality
  controls, independent namespaced URL state and refresh, repeated Behaviors
  attachment, unload/reinsert, keyboard control, unique DOM IDs, 390px layout,
  no-JavaScript tables and no JavaScript errors. Final published-page checks also
  compare date-filtered response counts to the published daily groups and open the
  real local test Media canonical page.
- `component-editor-test.cjs`: passed on Drupal 10.6 and 11. Creates a page with a
  Paragraph, selects terms/source/resource through native fields, saves, views
  anonymously, edits its heading and verifies the updated output. Successful and
  failed intermediate test pages are retained as unpublished QA drafts; no account
  credentials were changed. The primary two-map demonstration remains published.
  The configured owner’s native resource create/status access is separately checked
  through Drupal entity/field APIs, including denial of general page creation.
  Its core one-time login remains intentionally blocked by the existing managed
  password policy; that policy was not bypassed and owner credentials were not
  requested or reset. Browser editing used Drupal administration.
- `component-cache-test.cjs`: passed on both disposable migrated hosts. Warms an
  anonymous normal page, changes a taxonomy label, reloads without a cache rebuild
  and sees the new label. Restores the original label in `finally`.
- `component-workflows.php`: 17 isolated approval/publication checks and 50 isolated
  JSON/CSV import/review/publication checks passed on both versions. SQLite and
  temporary file stores isolate service writes. A persisted reviewer exists only
  inside a rollback transaction, matching current authorization rules.
- `component-reset-preservation.php`: empty-reset and restore passed on both
  disposable hosts. Canonical terms, Paragraphs, resource content and demo nodes
  retained identical fingerprints. The working site's reset tool was not run.
- Existing metric tests: 46 normalizer checks, 973 statewide aggregation checks,
  20 deterministic union checks, 82 fixed-answer validation checks and 24
  questionnaire/date/panel/baseline checks passed. JavaScript model checks passed:
  16 bubble-model, 12 union/state and 14 date/union/state checks.
- PHP syntax checks covered the custom module files; JavaScript syntax and
  `git diff --check` passed.

Reproduce principal checks:

```sh
ddev drush php:script tests/lit_map/component-integration.php
node tests/lit_map/component-browser-test.cjs
node tests/lit_map/component-editor-test.cjs
ddev drush php:script tests/lit_map/component-union-reconciliation.php
ddev exec php tests/lit_map/component-lint.php
ddev exec php tests/lit_map/normalizer-test.php
ddev exec php tests/lit_map/statewide-test.php
ddev drush php:script tests/lit_contribution/validator-test.php
ddev drush php:script tests/lit_contribution/union-test.php
```

Browser scripts use local Chrome and Playwright Core 1.58.2 outside the repository.
`LIT_COMPONENT_URL` selects another normal page. Editor tests use `LIT_TEST_BASE`
and `LIT_TEST_CWD`. Cache/workflow/reset tests explicitly require the disposable
project names; do not remove their guards. Reset checks run only on those copies
and restore their application state afterward. See each script for details.

## Preservation and current published numbers

Before migration, the complete database/configuration and private state were
backed up under ignored `private/component-backup-20261118/`. A binary diff of the
pre-existing uncommitted work was also saved. The branch remains `contribution`;
no commit, push, history rewrite or deployment occurred.

The working site's nine contribution tables and account credential fields were
fingerprinted and compared with the restored pre-component backup. All ten
fingerprints matched. The same fingerprints matched after the disposable Drupal
11 upgrade/component migration. Account login/session activity from browser QA is
not a credential change. All original substantive private files matched their
backup hashes; Finder's `.DS_Store` metadata changed separately.

Publication pointer remained:

`3fcd7b7dd3cf44ca130c1d8c108df8c7a06cac2069b1ef12353343f23c8da1f5`

Independent reconciliation of **published** source rows found 10,000 responses
in 5,215 disjoint groups. Statewide unions: English 9,041; Spanish 1,762;
English-or-Spanish 9,247; English-or-Spanish-or-Korean 9,413; all supported
languages 10,000. New Brunswick: 634 / 113 / 647 / 658 / 700 respectively.
No republishing was performed on the working site.

The legacy `union-live-check.php` assumes all currently eligible reviews equal the
historical 10,000-response publication. It failed that old assumption because an
additional approved upload is not in the saved publication. The new read-only
`component-union-reconciliation.php` compares the actual published source rows;
all county/town unions match. The historical union unit test also expected schema
3 even though the existing builder emits daily schema 4; its assertion now checks
schema 4 and the undated bucket without changing the aggregation algorithm.

## Issues found and resolved during verification

- Drupal refuses changing populated string-field storage in place. The update
  adds a long-description field, copies values and retains the short field.
- Current authorization loads a real reviewer account. Older isolated tests used
  an unsaved fake UID; a transactional persisted test actor now exercises the
  existing checks without weakening authorization or changing real accounts.
- Drupal 11 rejects `Merge::key(array)`. Publication/reset and corresponding setup
  helpers now use `Merge::keys(array)`, which is supported on both core versions.
  Actual Drupal 11 workflow tests found this; an info.yml declaration would not.
- A Drupal 11 cache rebuild immediately after importing the Drupal 10 backup
  initially failed because the router `alias` column did not exist yet. Running
  database updates before the cache rebuild completed the disposable upgrade.
- Language seed aliases retain historical spellings when labels are renamed.
  County normalization likewise recognizes stable geometry keys. Unknown and
  ambiguous questionnaire answers remain explicitly unresolved.

## Limits

No real interview, production mail/Qualtrics connection, receiving-site theme,
physical phone, Safari/Firefox matrix, or screen-reader conformance audit was
performed. The demonstration Media is a labeled local text document; external
HTTPS/Vimeo URLs are rendered/validated without contacting an invented interview.
The component links to canonical Media pages and does not provide a player.

The public fixture provider intentionally remains uncached for development file
freshness. Published Paragraphs use entity/list/publication cache invalidation.
Identifier uniqueness is enforced through Drupal validation and guarded saves,
not a bespoke SQL uniqueness table; bulk integrations should serialize writes.
Editorial permission assignment on a receiving site remains that site's choice.
