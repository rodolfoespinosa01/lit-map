#!/usr/bin/env bash
set -euo pipefail
lit_test_root=$(cat /tmp/lit-reset-qa-current)
[[ "$lit_test_root" == /tmp/lit-reset-qa.* && -f "$lit_test_root/site.sqlite" ]] || exit 1
cp -a web/modules/custom/lit_contribution "$lit_test_root/web/modules/custom/"
cp scripts/lit-local-reset.php "$lit_test_root/scripts/"
cp tests/lit_contribution/reset-cli-test.py "$lit_test_root/tests/lit_contribution/"
cd "$lit_test_root"
vendor/bin/drush cr >/dev/null
find web/modules/custom/lit_contribution -name '*.php' -exec php -l {} \; >/dev/null
python3 tests/lit_contribution/reset-cli-test.py
