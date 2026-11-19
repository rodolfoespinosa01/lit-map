# Informational scope migration — November 19, 2026

Work is on `refactor/informational-map`, branched from the clean `contribution`
implementation. No commit, push, deployment, reset or history rewrite was made.

## Retained and migrated

The normal `/node/3` page, all existing content and references, map architecture,
taxonomies, geographic distribution, resource nodes/Media, date/union counts and
questionnaire aggregate definitions remain. File normalization, matching,
aggregation, private aggregate writing, public reading and panel filtering now
live in `lit_map`. The extracted 10,000-response baseline exactly matched the
former active aggregate before uninstall. No source records are needed to serve it.

## Database/configuration changes

- Added Paragraph `field_lit_survey_show` and `field_lit_survey_url` plus widgets.
  Retained old source values for revision compatibility; updated their labels.
- Activated `lit_map.dataset` state pointing to a validated private immutable
  aggregate. Set `lit_map.local_page` to 3 and explicitly changed this local
  site's front page from `/lit` to `/node/3`.
- Uninstalled `lit_contribution` through Drupal. Removed its nine schema tables:
  `lit_contribution_review`, `audit`, `publication`, `dataset`, `access_audit`,
  `login_token`, `email_reservation`, `import_state`, `import_history` (each name
  after the first has the same `lit_contribution_` prefix).
- Removed its settings/resources configuration and `lit_contribution.*` state.
  Migrated catalogue resources were already present and were not overwritten.
- Removed only LiT owner/administrator/reviewer role assignments and obsolete
  module permissions, then their three role definitions. All 16 existing users
  were preserved; no account was assumed disposable from its name. Standard
  accounts, passwords, UID 1, shared services and mail infrastructure remain.
- Preserved all recorded IDs: 7 nodes, 5 Paragraphs, 32 terms, 1 Media item.

Removed active application-owned private paths after backup/extraction:
`private/lit-contribution`, `private/lit-imports`,
`private/lit-contribution-demo-10000-v1`, `private/lit-questionnaire-10000-v2`,
`private/lit-local-development.php`, `private/lit-operation.lock`.
Other private archives and unrelated files were retained. Removed obsolete
`LIT_OWNER*`/`LIT_SUPERADMIN*` assignments from `.env` without displaying values.
The existing guarded settings include becomes inactive because its target is gone.

The retired module, public local survey/authentication/review routes, fixture and
account/reset tools, workflow tests and contribution documentation were removed.
`/lit` and `/lit-map/contribution-preview` redirect to the retained page; normal
filter parameters are preserved. Standard `/user/login` remains available.

## Private recovery backup

Git-ignored directory: `private/informational-backup-20261119/`, mode 0700.
`database.sql.gz` and `private-files.tar.gz` are mode 0600. The SQL gzip was fully
read and checked for the expected application/user tables; every archived file
was read; SHA-256 values are recorded in `sha256.json`. `original-commit.txt`
records the original code revision. `entity-ids.json`, `active-dataset.json` and
`removed-paths.json` document preservation/extraction/removal without public
participant records. Keep this directory private; never commit it.

Restore into a **separate recovery checkout** of the original revision, preserving
current cleanup work. Verify hashes against `sha256.json`; restore the matching
private archive and database together:

```sh
# In the separate recovery checkout configured with its own DDEV project name:
ddev import-db --file=/path/to/private/informational-backup-20261119/database.sql.gz
tar -xzf /path/to/private/informational-backup-20261119/private-files.tar.gz
ddev drush cr
```

The archive includes `.env` and local settings: preserve restrictive permissions,
do not print their contents, and adapt the recovery DDEV name/settings before
starting it. Importing a backup overwrites the chosen target database; never run
this on an unrelated site. Recovery instructions also live in `RESTORE.txt`.
A full database restoration drill has not yet been performed.

## Staged upgrade for another existing installation

Do not remove an enabled module's directory first. In a maintenance window, keep
the original `lit_contribution` directory available while applying new `lit_map`
code and migration scripts. Make and verify that site's own database/private
backup. The scripts use `LIT_MIGRATION_BACKUP` (absolute container directory), or
this local backup directory by default; the backup must contain `sha256.json`.

1. Run `scripts/extract-informational-dataset.php` with Drush before uninstall.
   This captures the active aggregate and reconciles the synthetic baseline.
   This extractor expects the known 10,000-response local baseline and must be
   adapted explicitly for another source; it will refuse an unexpected count.
2. Rebuild caches, run additive `updatedb`, then run
   `scripts/migrate-informational.php`. It checks backup hashes, migrates resource
   references, validates/activates the dataset, removes only LiT role access,
   uninstalls the old module and verifies preserved entity IDs.
3. Remove the old module code only after successful uninstall. Remove only
   verified application-owned private paths covered by that site's backup;
   never use a broad private-directory reset. Rebuild caches.
4. Choose any front-page/legacy redirect target explicitly with the local helper
   only on a demo host. Verify routes, resources and counts before serving users.

Clean installations need only `lit_map` and the ordinary setup in the README;
they do not need the retired module or migration scripts.
