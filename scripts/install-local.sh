#!/usr/bin/env bash
# Run from the project root, after ddev start and ddev composer install.
set -euo pipefail
cd "$(dirname "$0")/.."

# DDEV's mysql command targets this project's isolated db service.
lit_table_count=$(ddev mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'db';")
if [[ "$lit_table_count" != "0" ]]; then
  echo 'Refusing installation: the project database is not empty (or could not be checked).' >&2
  exit 1
fi

# Generate a password inside the container without saving or printing it.
# Use ddev drush uli for local administration.
ddev exec bash scripts/install-drupal-in-container.sh
ddev drush en paragraphs lit_map -y
ddev drush cr
echo 'Local host installed. Run: ddev drush uli'
