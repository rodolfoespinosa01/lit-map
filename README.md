# Rutgers Lives in Translation — informational map

LiT is a reusable Drupal `lit_map` Paragraph with Counties and Languages taxonomy,
entity references, preprocessing, Twig/SVG, plain CSS and vanilla JavaScript using
Drupal Behaviors/core once. The local public page is
<https://rutgers-lit-m1.ddev.site/node/3>; `/` shows that page. Site editors use
<https://rutgers-lit-m1.ddev.site/user/login> and Drupal's standard administration.

The data flow is **Qualtrics export → explicit column mapping → validation and
normalization → complete aggregate snapshot → map**. There is no hosted survey,
contribution review, custom administrator account system, Qualtrics API, polling,
synchronization or outbound response delivery. The optional survey invitation is
only an external HTTPS link. Normal Drupal users, permissions, Media access and
UID 1 administration remain available.

The retained demo has exactly **10,000 synthetic responses**, including the
existing geographic distribution, source-date buckets, disjoint language groups
and supported questionnaire aggregates. Counts describe responses, not unique
people or population. No source IDs, contacts or free text enter public payloads.

- [Editor, installation and component guide](docs/component/README.md)
- [Supported files, replacement contract and demo regeneration](docs/dataset.md)
- [Migration, private backup and restoration](docs/informational-migration.md)
- [Current verification and limitations](docs/component/verification.md)
- [Geography attribution and regeneration](docs/geometry/README.md)

## Local use

```sh
ddev start
ddev composer install --no-interaction
ddev drush cr
```

For a **new empty** Drupal database only, `bash scripts/install-local.sh` enables
the component. It refuses an existing installation. Then explicitly seed the
reference terms, synthetic dataset and demonstration page:

```sh
ddev drush php:script scripts/seed-lit-references.php
ddev drush php:script scripts/lit-reseed-demo.php
ddev drush php:script scripts/setup-lit-component-demo.php
```

The page-setup command prints its node ID. To use it as the local front page:

```sh
ddev exec env LIT_MAP_PAGE_ID=3 drush php:script scripts/setup-informational-local.php
```

Replace `3` with that ID. Portable installation never changes a receiving site's
front page. Optional test Media: `scripts/setup-lit-media-demo.php` (explicit demo
setup only). Existing sites use additive updates, not whole-site configuration
imports. Read the migration guide before updating a site with the former module.

The working lockfile uses Drupal 10.6, PHP 8.3, Paragraphs 1.x and Drush 13.
The component declares Drupal 10.6/11 compatibility. Current execution evidence
and historical Drupal 11 results are distinguished in the verification record.
No frontend build or runtime framework is required. Composer dependencies remain
unchanged. Standard `ddev drush uli` is available for authorized local recovery;
keep its one-time login URL private.

Raw exports, credentials, private data and recovery archives belong outside
`web/`, in Git-ignored storage. Never commit participant data. Source geometry
licenses and attribution are retained in `docs/geometry/` and the SVG assets.
