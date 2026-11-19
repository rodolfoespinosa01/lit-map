# Informational cleanup verification — November 19, 2026

Executed during this cleanup on the working Drupal **10.6.21 / PHP 8.3** host and
a new isolated **Drupal 11.4.13 / PHP 8.4** host. Earlier contribution/prototype
results are not evidence for the new scope.

## Executed checks

- PHP syntax: 31 module PHP/install/module files; all 66 installation YAML files
  parsed. `git diff --check` passed.
- Normalized aggregate suite: 46 validation/aggregation checks.
- Retained bubble model: 16 checks. New union/date suite: **3,643** county and
  municipality assertions across selected language unions and date windows.
- Dataset integration: **14 assertions on both Drupal versions**, including
  identical-ID collapse, reimport idempotency, full replacement instead of append,
  missing dates, synthetic/non-synthetic metadata, rejected files retaining the
  last valid pointer, aggregate consistency, public allowlisting and equivalent
  normalized CSV/JSON files. Tests restore the original active snapshot.
- The extracted active baseline exactly matched the retained 10,000-response
  aggregate. Deterministic regeneration reproduced all aggregate records,
  disjoint groups, date buckets and questionnaire cohorts exactly. The existing
  stored synthetic timestamp anchor is November 14, 2026, 12:56 UTC; the former
  generator's 12:00 anchor was corrected without changing stored data.
- Component integration: 25 checks on Drupal 10 and 1,100 on Drupal 11 (the isolated page exercises more restricted combination groups): taxonomy stability/labels/aliases, restricted
  references, entity cache tags, native external/transcript links, published and
  unpublished Media, inaccessible Media routes and Paragraph updates.
- Browser component suite passed on **both versions**: normal anonymous page,
  two independent instances, county/language restrictions, date counts, URL
  Back/Forward/reload, repeat attach, detach/unload/reinsert, keyboard selection,
  unique IDs, mobile overflow, no-JS tables and no JavaScript errors.
- Standard editor browser test on Drupal 10 created a temporary Paragraph page,
  chose taxonomy/resource references, configured an explicitly labeled
  `https://example.org/qualtrics-test` link, verified anonymous display and hide
  control, edited content and accessed the normal protected dataset form. The
  temporary page was deleted. No real survey URL was configured.
- Public route/privacy suite: `/` renders two maps and 10,000 aggregate responses;
  public settings contain no source IDs, contacts or raw questionnaire fields;
  `/user/login` returns 200; anonymous dataset upload returns 403; retired survey,
  login, review, import, publish, synchronization and owner routes return 404.
  Legacy public map redirect retains filter parameters and maps them to the
  first Paragraph's state namespace.
- Desktop (1440px) and mobile (390px) screenshots were visually inspected;
  map controls/results remain readable and there is no horizontal overflow.
  Keyboard pan/zoom/Home and focus outlines were exercised. Screenshots are in
  ignored `test-results/informational-{desktop,mobile}.png`.
- Clean Drupal 11 installation, taxonomy seeding, synthetic dataset activation,
  normal two-Paragraph page and test Media setup passed. Portable installation
  preserved its original `/user/login` front page; no contribution module was
  installed. The working site's Composer lock/core version were not changed. The isolated test checkout is `/private/tmp/lit-informational-d11`; its runtime was stopped after verification.
- After migration: no `lit_contribution_*` tables or settings remain; all original
  entity IDs and all 16 users remain. Local root points to `/node/3`.

The Drupal 11 Media-settings test exposed a test-harness rollback assumption: router rebuilds can commit DDL on MariaDB. The test now explicitly restores original labels, Paragraph headings, Media status and settings, and passed on rerun.

## Re-run

```sh
ddev exec php tests/lit_map/component-lint.php
ddev exec php tests/lit_map/normalizer-test.php
node tests/lit_map/bubble-model-test.cjs
node tests/lit_map/union-date-test.cjs
ddev drush php:script tests/lit_map/dataset-test.php
ddev drush php:script tests/lit_map/component-integration.php
node tests/lit_map/component-browser-test.cjs
node tests/lit_map/component-editor-test.cjs
node tests/lit_map/informational-browser-test.cjs
```

The browser scripts use local Chrome and Playwright Core at
`/private/tmp/lit-geometry-qa/node_modules/playwright-core`, overridable with
`LIT_PLAYWRIGHT_PATH`. Use `LIT_COMPONENT_URL` for another component page. The
editor test uses a private standard Drupal UID 1 one-time login without printing
it. Run mutable dataset/component tests sequentially, outside active editing.
For reproduction, generate the private synthetic source first, then run
`ddev drush php:script tests/lit_map/demo-reproduction.php`.

## Limits

No actual native Qualtrics export has been supplied or mapped. Questionnaire
choices/matching assumptions and the disclosure threshold still need research
team confirmation. Production Rutgers integration, production-scale performance,
and a full recovery restoration drill were not exercised. The 22 MiB synthetic
aggregate is retained for exact reproducibility; larger deployments may warrant
measured optimization. Media tests use the labeled local test document, not real
participant recordings. No deployment was performed.
