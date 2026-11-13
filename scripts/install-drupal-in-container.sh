#!/usr/bin/env bash
# Internal helper: use install-local.sh, which checks the database first.
set -euo pipefail
if [[ "${IS_DDEV_PROJECT:-}" != "true" ]]; then
  echo 'This helper must run inside DDEV.' >&2
  exit 1
fi
lit_table_count=$(mysql -h db -u db -pdb db -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'db';")
if [[ "$lit_table_count" != "0" ]]; then
  echo 'Refusing installation: database is not empty.' >&2
  exit 1
fi
lit_password=$(php -r 'echo bin2hex(random_bytes(32));')
vendor/bin/drush site:install minimal --site-name="Rutgers LiT Development" \
  --account-name=admin --account-pass="$lit_password" \
  --account-mail=admin@example.test --site-mail=site@example.test --yes --quiet
