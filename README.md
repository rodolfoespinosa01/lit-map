# Rutgers Lives in Translation — Milestones 1–3

This Composer-managed Drupal installation is a **local development/test host**
for a custom component destined for an existing Drupal website. It is not a
replacement production website. The `lit_map` module now includes the foundation
and a verified static New Jersey county SVG with local geometry and synthetic
interaction previews.

The interaction preview uses fabricated JSON metrics only. No final Paragraph,
taxonomy vocabulary, importer, media integration, frontend framework, or build
pipeline is implemented. See [the versioned survey format](docs/survey-format.md).
Survey data and SVG geometry remain separate.

## Milestone 3 interaction preview

Open https://rutgers-lit-m1.ddev.site/lit-map/interaction-preview after `ddev start`.
Select multiple languages, a county, and optionally a town/city. Counts are
fabricated **survey responses**, never population or unique people. A persistent
notice identifies the synthetic data. Numbered county callouts keep counts legible;
the results panel provides equivalent data and municipality breakdowns.

State is stored in URL parameters and restored on refresh and Back/Forward.
Filtering is local JavaScript using Drupal Behaviors and core/once; no AJAX.
The PHP service reads and validates a fixed module-owned version 2 fixture.
This route deliberately disables page/render caching, so fixture edits appear
on the next request without a cache rebuild. Existing open pages show their
loaded snapshot until refreshed.

See [interaction setup, state and caching](docs/interactions/README.md),
[validation/aggregation and browser verification](docs/interactions/verification.md),
and [the JSON contract and known totals](docs/survey-format.md).
The original geometry preview remains available and the SVG is unchanged.

## Milestone 2 geometry preview

Open https://rutgers-lit-m1.ddev.site/lit-map/geometry-preview after `ddev start`.
It uses a Drupal render array, theme hook, attached CSS libraries, and a fixed
Twig SVG include. The preview highlights Bergen, Hudson, Morris, and Essex for
geometry/style inspection only, with no survey data or interactivity.

The 21 counties come from official 2025 Census cartographic boundaries at
1:500,000, projected in NAD83 / New Jersey. See [geometry documentation](docs/geometry/README.md)
for source and reuse terms, conversion commands, tool versions, identifier mapping,
and verification. Development-only Python tools are optional for regeneration;
the running Drupal site needs none of them. Existing PHP/database configuration
and Composer dependencies are unchanged by Milestone 2.

## Prerequisites and Mac setup

Inspected November 13, 2026: the project directory was empty, was not a Git
repository, and had no applicable AGENTS.md files. The Mac runs macOS 15.7.9
(24G830), Intel x86_64. Homebrew and Docker Desktop were already installed;
Docker was stopped and DDEV was absent. DDEV was installed with Homebrew and
Docker Desktop started. System PHP and MAMP were not changed.

On this Mac, the required installation commands are:

```sh
open -a Docker
brew install ddev/ddev/ddev
```

Wait for Docker Desktop to report its engine is running, and complete any Docker
Desktop permission/onboarding prompts. On a fresh Mac, install Homebrew from
https://brew.sh and the architecture-appropriate Docker Desktop from
https://docs.docker.com/desktop/setup/install/mac-install/ first.
No host PHP, Composer, Node, or database installation is required.

For trusted local HTTPS, DDEV recommends `mkcert -install` (may request a macOS
administrator password). An existing mkcert CA was present on this Mac.
Homebrew reports Intel macOS as Tier 3; DDEV itself installed successfully.

## Versions and compatibility

| Component | Version |
| --- | --- |
| DDEV | 1.25.5 |
| Docker Desktop / Engine | 4.94.0 / 29.8.2 |
| PHP | 8.3.36 |
| Composer | 2.11.2 |
| Database | DDEV default MariaDB 11.8; actual 11.8.9-MariaDB-ubu2404-log |
| Drupal core | 10.6.21 |
| Paragraphs | 1.23.0 (Drupal release label 8.x-1.23) |
| Entity Reference Revisions | 1.15.0 (Drupal release label 8.x-1.15) |
| Drush (development dependency) | 13.8.1 |
| Robo (development dependency) | 5.1.1 |

`composer.lock` records all exact dependency versions. Core constraints use
`~10.6.0`, keeping updates within 10.6. Stable releases only are allowed.
Drupal 10.6.21 was the latest stable 10.6 release in the official release list
at setup. Drupal 10 security support ends **December 9, 2026**. Recheck support
before recreating this environment after that date; do not silently select
another major version.

Robo is constrained to `~5.1.0`: Robo 5.2.3 called `Application::addCommand()`,
which is unavailable in Drupal 10's Symfony Console 6.4, and prevented Drush
from starting. This is an explicit Composer constraint, not a vendor patch.

The custom module declares `~10.6.0 || ^11` and PHP 8.3. **Drupal 11 is untested**;
compatibility must be verified separately in an isolated host before claiming
support. The current host remains Drupal 10.6.

## Everyday use

Run commands from this directory:

```sh
ddev start
ddev describe
ddev drush uli
```

Local website: https://rutgers-lit-m1.ddev.site

Open the one-time URL printed by `ddev drush uli` to log in as `admin` (UID 1).
The password was randomly generated inside the container and was not saved in
the repository. Generate another one-time link whenever needed. Do not share
login links. After login, the status report is at `/admin/reports/status`.
The minimal profile has no editorial content types or taxonomy vocabularies.

```sh
ddev stop
```

This stops only this project and preserves its database. Avoid `ddev poweroff`
when other DDEV projects need to remain running. Docker may automatically resume
unrelated containers according to their own restart policies when Desktop starts.

## Recreate the test host

Copy/clone the tracked project files into a new directory, keeping
`composer.json`, `composer.lock`, `.ddev/config.yaml`, custom module, scripts,
and documentation. No Git repository or remote was created by this setup.

For a second simultaneous copy, choose an unused project name **before starting**:

```sh
ddev config --project-name=rutgers-lit-m1-copy --project-type=drupal10 --docroot=web --php-version=8.3
ddev start
ddev composer install --no-interaction
bash scripts/install-local.sh
```

For the original project use its existing name, without reconfiguring it.
The committed DDEV configuration explicitly records its default MariaDB 11.8
selection. A second name creates a separate project database and local URL.
Check `ddev list` first to avoid reusing an existing project's name or volume.

The install script checks that database `db` has zero tables and refuses to
overwrite an existing installation. It installs Drupal's minimal profile,
enables Paragraphs and LiT Map (and required dependencies), and rebuilds caches.
Do not use direct `drush site:install` on an existing database. Re-running the
script on this installed site is expected to refuse; use `ddev start` instead.
There is no database dump to import and no survey database to create.

The original setup used DDEV's official Drupal workflow with PHP 8.3 and `web/`
as document root. Finder's regenerated `.DS_Store` blocked DDEV's empty-folder
check, so the recommended-project template was created in the container's `/tmp`
and copied into the project without overwriting files before Composer install.
Recreation from this repository uses `composer install`, avoiding that issue.

## Verification commands

```sh
ddev php --version
ddev mysql -N -B -e 'SELECT VERSION();'
ddev composer show --direct
ddev composer validate --strict
ddev composer check-platform-reqs
ddev composer audit
ddev drush status
ddev drush core:requirements
ddev drush pm:list --status=enabled --type=module
curl -I https://rutgers-lit-m1.ddev.site
```

See [verification results](docs/verification.md) for actual results and any
remaining limitations. Dependencies, uploads, local settings/secrets, database
dumps and generated DDEV artifacts are ignored by Git. Commit the manifests and
lockfile; never commit raw survey exports or real credentials.

## Official references

- [DDEV installation](https://docs.ddev.com/en/stable/users/install/ddev-installation/)
- [DDEV Drupal quickstart](https://docs.ddev.com/en/stable/users/quickstart/#drupal)
- [DDEV configuration and database default](https://docs.ddev.com/en/stable/users/configuration/config/)
- [Drupal releases](https://www.drupal.org/project/drupal/releases?version=10)
- [Drupal support schedule](https://www.drupal.org/about/core/policies/core-release-cycles/schedule)
- [Paragraphs](https://www.drupal.org/project/paragraphs)
- [Entity Reference Revisions](https://www.drupal.org/project/entity_reference_revisions)
- [Drush compatibility](https://www.drush.org/13.x/install/)

## Milestone 4: statewide synthetic bubble preview

Open <https://rutgers-lit-m1.ddev.site/lit-map/bubble-preview> after `ddev start`.
Choose languages, select a county, and enable **Show towns/cities** to load its
municipality boundaries. The active language alone controls bubble area; all
counts are fabricated survey responses. USA overview, bounded pan/zoom, keyboard
controls, URL state and an optional reduced-motion-aware floating effect are
included. The original geometry and interaction previews remain available.

See [Milestone 4 instructions and reproducible sources](docs/bubbles/README.md),
[verification](docs/bubbles/verification.md), and the retained scripts in
`tools/geometry/`. This adds no survey tables or frontend runtime libraries.

## Repository contents

Keep the custom module (including its static SVGs and synthetic data), Composer
manifests/lockfile, `.ddev/config.yaml`, installation scripts, tests, conversion
tools, documentation and license notices in Git. Preserve the current Composer
scaffold configuration and the eligible root/web scaffold files; dependencies
remain excluded and are restored by Composer.

Screenshots and browser/cache result JSON files referenced by the milestone notes
are local QA outputs, excluded by exact paths in `.gitignore`. Run the documented
checks to regenerate them. The authored verification notes remain documentation.
Keep `docs/bubbles/fixture-report.json`: the statewide PHP test reads its expected
totals and missing/zero cases. Also keep `docs/geometry/conversion-report.json`
and `docs/bubbles/geography-report.json`: converter `--check` modes read them for
byte-for-byte comparison. Source metadata XML, identifier CSVs, survey-format
documentation and the synthetic example remain tracked candidates.

Use ignored `private/`, `survey-exports/`, `recordings/` or `secrets/` directories
for local sensitive material; never put real responses or credentials in the
module's public synthetic data directory. Ignore rules are path-based and cannot
identify sensitive contents placed elsewhere.
