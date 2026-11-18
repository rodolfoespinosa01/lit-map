#!/usr/bin/env bash
# Disposable in-container SQLite Drupal site; never bootstraps the current database.
set -euo pipefail
lit_test_root=$(mktemp -d /tmp/lit-reset-qa.XXXXXXXX)
mkdir -p "$lit_test_root/web/sites/default" "$lit_test_root/private"
for part in core modules themes profiles; do cp -a "web/$part" "$lit_test_root/web/$part"; done
cp web/autoload.php web/index.php "$lit_test_root/web/"
cp -a scripts tools fixtures "$lit_test_root/"
cp -a vendor "$lit_test_root/vendor"
cp composer.json composer.lock "$lit_test_root/"
cp web/sites/default/default.settings.php "$lit_test_root/web/sites/default/default.settings.php"
cp web/sites/default/default.settings.php "$lit_test_root/web/sites/default/settings.php"
printf '\n$settings["lit_local_demo_enabled"] = TRUE;\n$settings["lit_local_reset_enabled"] = TRUE;\n' >> "$lit_test_root/web/sites/default/settings.php"
cd "$lit_test_root"
"$lit_test_root/vendor/bin/drush" --root="$lit_test_root/web" site:install minimal --db-url="sqlite://localhost/$lit_test_root/site.sqlite" --account-name=emergency-demo --account-pass=DisposableEmergencyOnly --site-name='Disposable LiT reset QA' -y >/dev/null
"$lit_test_root/vendor/bin/drush" --root="$lit_test_root/web" en lit_contribution -y >/dev/null
"$lit_test_root/vendor/bin/drush" --root="$lit_test_root/web" php:script scripts/seed-lit-references.php >/dev/null
printf '%s\n' "$lit_test_root" > /tmp/lit-reset-qa-current
printf 'Disposable site ready (path recorded in /tmp/lit-reset-qa-current).\n'
