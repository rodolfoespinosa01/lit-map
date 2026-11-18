# Local demonstration maintenance

These commands affect application data, never drop a database or delete Drupal
accounts/content. Do not use on a receiving Rutgers site. Module installation
never changes its front page or authentication settings.

## Explicit local setup

From the project root, opt in locally (ignored private settings):

```sh
python3 scripts/install-local-survey-settings.py --enable-reset
```

Both `lit_local_demo_enabled` and `lit_local_reset_enabled` must be boolean TRUE,
CLI must be used, and `IS_DDEV_PROJECT` must equal `true`. Missing flags or an unavailable configured superadmin fail closed.
Do not deploy these development settings. `.env` holds the configured owner identity;
`.env.example` contains only placeholders. Reset never changes the owner's password.

## Preview each preset

```sh
ddev exec env LIT_RESET_PRESET=contributions drush php:script scripts/lit-local-reset.php
ddev exec env LIT_RESET_PRESET=fresh drush php:script scripts/lit-local-reset.php
ddev exec env LIT_RESET_PRESET=empty drush php:script scripts/lit-local-reset.php
```

Output includes current table counts, number of administrators affected, and fixed
storage paths/file counts. It excludes response contents, email addresses and passwords.

| Preset | Removes | Preserves/restores |
| --- | --- | --- |
| `contributions` | Operational uploads/local responses, their review/audit rows, reservations, import history and all private upload snapshots/staging; stale publications | Accounts, settings, synthetic sources and review decisions; republishes the selected baseline (or empty baseline) |
| `fresh` | All operational and historical survey/review/import data, obsolete private datasets/publications, added administrators' LiT roles and sessions/tokens | Exactly 10,000 reproducible questionnaire-v2 responses, all approved; invitation OFF, external URL blank |
| `empty` | Same administrator/contribution cleanup as fresh, plus all private synthetic datasets | Valid empty public publication; map displays “No data available” |

All presets preserve owner and UID 1, application code, geography, resource
configuration, front-page configuration, unrelated Drupal accounts/roles/content,
and private credentials. Packaged, tracked synthetic example fixtures are code
assets and are not deleted; empty mode disables their use as a baseline. LiT routes also require explicit LiT membership, so unrelated Drupal role permissions
cannot restore a revoked administrator’s LiT access. No account
is deleted, including deactivated or historically missing administrators.

## Execute and confirm

Run from an interactive terminal; replace the preset explicitly:

```sh
ddev exec env LIT_RESET_PRESET=fresh LIT_RESET_EXECUTE=yes drush php:script scripts/lit-local-reset.php
```

Type `RESET fresh` exactly (or `RESET contributions` / `RESET empty`). A pipe,
noninteractive stdin, missing preset, or mismatched confirmation is rejected.
Preview is the default; `LIT_RESET_EXECUTE=yes` alone cannot select a preset.

Before mutation the tool writes and fsyncs a private recovery archive, verifies its
checksum and round-trip contents, and prints its identifier on completion. The
archive includes scoped database rows, affected LiT role assignments/sessions,
settings/baseline pointers, and exact bytes of all managed source/publication files.
User password hashes are not modified or exported by this tool.

OS locks fence local web requests and CLI review/import/publication operations
throughout backup, mutation and cleanup. Local requests receive HTTP 503 while
reset runs. A private `lit-reset-incomplete` marker keeps requests fenced after a
crash. Ordinary failures restore the pre-reset snapshot automatically; if recovery
fails, explicitly restore the backup named in that marker. Do not remove the marker
to bypass unfinished recovery. Database publication pointers change transactionally;
files are written before pointers, and obsolete snapshots are removed before the
fence is released.

## Recovery and retention

Backups live only in Git-ignored `private/lit-reset-backups/`, with directory mode
0700 and files mode 0600. Each `ID.backup` has a matching `ID.backup.sha256`.
These archives contain private data. Keep them outside the web root and do not
commit, email, or publish them. They are the **only intentional retained copies**
of removed application data. There is no hidden snapshot retention.

Create a backup without resetting data (the explicit preset selects the scope):

```sh
ddev exec env LIT_RESET_PRESET=fresh LIT_RESET_BACKUP=yes drush php:script scripts/lit-local-reset.php
```

Preview a restore, then execute it interactively:

```sh
ddev exec env LIT_RESET_RESTORE=YYYYMMDD-HHMMSS-0123456789abcdef drush php:script scripts/lit-local-reset.php
ddev exec env LIT_RESET_RESTORE=YYYYMMDD-HHMMSS-0123456789abcdef LIT_RESET_EXECUTE=yes drush php:script scripts/lit-local-reset.php
```

Type `RESTORE YYYYMMDD-HHMMSS-0123456789abcdef`. Use the actual generated identifier.
Restore verifies scope, checksum and site identity and creates a backup of current
scoped data before replacing it. Restore does not roll back unrelated site data or
owner credentials. Restored session records can make previous admin sessions valid
again: treat restore as restoring their former access as well as data.

Retention is explicit and manual: retain recovery archives until restoration has
been checked, then delete both files for an individual archive within seven days
(or sooner when local private-data policy requires). List archive names without
opening their contents:

```sh
ls private/lit-reset-backups/
rm -- private/lit-reset-backups/ID.backup private/lit-reset-backups/ID.backup.sha256
```

Never delete the archive referenced by an outstanding recovery marker. There is no
automatic expiry that could destroy the only recovery copy after a failed reset.

## Local landing page and restoration

The original local `system.site:page.front` was `/user/login`. No login redirect or
access restriction caused that behavior. The explicit setup reuses `/lit`:

```sh
ddev drush php:script scripts/setup-local-landing.php
ddev exec env LIT_RESTORE_FRONT=yes drush php:script scripts/setup-local-landing.php
```

The first command records the original value once in Drupal state and sets `/lit`;
the second restores it. Reset presets leave both settings untouched. Public routes:

- `/` and `/lit`: existing landing page
- `/lit-map/contribution-preview`: public map
- `/lit/superadmin/login`: owner login
- `/lit/administrator/login`: administrator login
- `/user/login`: standard Drupal and UID 1 emergency login

Role-specific logins retain their fixed authorized dashboard redirects and discard
request destinations. Standard account-menu blocks remain hidden only in LiT flows;
receiving-site navigation and authentication are not overwritten.

## Disposable verification

The test harness creates a separate Drupal installation and SQLite database under
`/tmp/lit-reset-qa.*` inside DDEV, with fictional accounts and private data. It copies
code, dependencies and example fixtures, never the current database, `.env`, or
private submissions. No reset is run against the current site:

```sh
ddev exec bash tests/lit_contribution/prepare-reset-site.sh
ddev exec bash tests/lit_contribution/run-reset-site.sh
ddev exec bash tests/lit_contribution/run-reset-edge.sh
ddev exec bash tests/lit_contribution/run-reset-final.sh
```

Tests cover production guard, preview, 10,000 baseline, reservation clearing,
admin roles/sessions, owner credentials, unrelated state/roles, landing preservation,
empty publication, recovery, symlink rejection, concurrency and questionnaire rules.
Test copies and their recovery archives remain under the printed disposable path;
remove those disposable directories after QA. They contain fictional test data only.

The final CLI test leaves only the disposable site empty. To verify that state in
Chrome on the local Mac, run `ddev exec bash tests/lit_contribution/serve-reset-site.sh`
in one terminal and `node tests/lit_contribution/reset-empty-browser.cjs` in another;
stop the temporary server afterward with `ddev exec --raw -- bash -c 'kill "$(cat /tmp/lit-reset-http.pid)"'`. The browser harness uses the existing local
Playwright installation at `/private/tmp/lit-geometry-qa/node_modules/playwright-core`
and Google Chrome. It proxies requests solely to the container's loopback test server.
It also checks the real local root anonymously, without logging in or resetting it.

## Changed implementation files

- `src/Form/DemoSurveyForm.php`: neutral placeholder introduction/agreement description.
- `.env.example`, `scripts/lit-owner.php`, owner-related contribution docs and access tests,
  and `tools/contribution/check-questionnaire-state.php`: configured identity/placeholders.
- `scripts/lit-local-reset.php`, `src/Service/LocalReset.php`,
  `src/Service/LocalOperationGate.php`, `src/EventSubscriber/LocalResetSubscriber.php`,
  service registration and mutation entry points: scoped backup/reset/recovery and locking.
- `tools/contribution/generate-questionnaire-demo.py`: fixed seed/date and standalone
  generation without depending on a previously generated legacy manifest.
- `src/Source/MockQualtricsSource.php` and `PublishedBaselineAndExports.php`: explicit
  empty baseline support.
- `src/Controller/PublicMapController.php`, map theme variables/template and count
  labels: an explicit empty-publication presentation.
- `scripts/setup-local-landing.php` and `scripts/install-local-survey-settings.py`:
  explicit local-only front-page setup/restoration and separate reset opt-in.
- `tests/lit_contribution/*reset*` and navigation harnesses: disposable verification.

Paths under `src/` refer to `web/modules/custom/lit_contribution/`. No questionnaire
catalog, answer choices, schema, conditional JavaScript, geographic attribution or
license notice was changed by this cleanup. Existing unrelated working-tree edits
were retained.
