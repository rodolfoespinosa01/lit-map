# Milestone 1 verification — November 13, 2026

The local host is running at https://rutgers-lit-m1.ddev.site.

## Completed checks

- Initial folder inspection: empty, no Git repository, no applicable AGENTS.md.
  No existing project files were replaced. Finder later generated `.DS_Store`;
  one copy was preserved at `/private/tmp/lit-m1-finder-r9kakl3o/.DS_Store`.
- macOS 15.7.9 (24G830), x86_64; Docker Engine 29.8.2, Docker Desktop 4.94.0,
  DDEV 1.25.5. Docker daemon started successfully.
- DDEV web/database/router healthy; document root `web`, nginx-fpm, PHP 8.3.36,
  Composer 2.11.2, MariaDB 11.8.9-MariaDB-ubu2404-log (DDEV default 11.8 series).
- Existing Docker containers/volumes inspected before project startup; no
  `rutgers-lit-m1` database volume existed. The new database had **zero tables**
  immediately before installation. Existing volumes were not imported or modified.
- Drupal 10.6.21, Paragraphs 1.23.0, Entity Reference Revisions 1.15.0,
  Drush 13.8.1, Robo 5.1.1 installed and locked. Robo 5.2.3 failed at startup
  against Symfony Console 6.4; the stable 5.1 constraint resolves that failure.
- `composer validate --strict`, `composer check-platform-reqs`, and
  `composer audit` passed; no known security advisories reported at verification.
- Drush confirms database connection and successful Drupal bootstrap.
- Paragraphs, Entity Reference Revisions, and `lit_map` enabled successfully.
- Minimal profile installed; zero Paragraph types, zero node content types,
  and taxonomy module disabled. No map, importer, or media implementation exists.
- Cache rebuild succeeded; entity/field definitions and database updates current.
- HTTPS home page returned **HTTP 200** with normal certificate verification
  (no insecure TLS bypass). One-time admin login was generated and exercised;
  authenticated `/admin/reports/status` also returned **HTTP 200**. Test token
  and cookie were temporary and were not retained in project files.
- Installation safety guard tested against the populated database: exited 1
  with “Refusing installation”; did not reinstall or overwrite the site.
- Ignore rules tested using a temporary Git metadata directory: Composer
  manifests/lockfile, DDEV config, and custom module remain eligible for tracking;
  dependencies, local settings, uploaded files, private keys and SQL dumps ignored.
- Synthetic example parses as valid JSON.

## Status report and limitations

Final Drupal status report has **no errors** and two warnings:

1. Configuration file protection disabled: DDEV's generated local settings set
   `skip_permissions_hardening = TRUE` for development. These settings are ignored
   by Git and are not production configuration.
2. Drupal core security coverage ends December 9, 2026. Drupal 10.6 is still
   supported on the verification date, but future work must account for this date.

An initial module-release-information warning cleared after update information
was refreshed. Drupal core, Paragraphs, and Entity Reference Revisions then all
reported current releases.

The custom module's Drupal 11 requirement expresses intended compatibility only:
**Drupal 11 has not been tested**. No separate clean-room recreation was run;
the documented installation scripts were exercised on this new host. Interactive
browser appearance was not reviewed; HTTP and authenticated access were tested.
There are no Milestone 1 blockers or remaining required user setup actions.

Starting Docker Desktop automatically resumed the pre-existing `sasn-mysql56`
container under its own restart policy. No configuration or data changes were
made to that project. Homebrew's default installation housekeeping updated its
metadata and removed old cached downloads; no unrelated project files were deleted.
No deployment, publication, push, remote repository, or system PHP/MAMP changes
were performed.
