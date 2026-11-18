#!/usr/bin/env bash
set -euo pipefail
lit_test_root=$(cat /tmp/lit-reset-qa-current)
[[ "$lit_test_root" == /tmp/lit-reset-qa.* && -f "$lit_test_root/site.sqlite" ]] || exit 1
cp -a web/modules/custom/lit_contribution "$lit_test_root/web/modules/custom/"
cp -a scripts tools "$lit_test_root/"
mkdir -p "$lit_test_root/tests/lit_contribution"
cp tests/lit_contribution/local-reset-test.php tests/lit_contribution/questionnaire-test.php "$lit_test_root/tests/lit_contribution/"
cd "$lit_test_root"
vendor/bin/drush cr >/dev/null
vendor/bin/drush php:script tests/lit_contribution/local-reset-test.php
vendor/bin/drush php:script tests/lit_contribution/questionnaire-test.php
