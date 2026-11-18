#!/usr/bin/env bash
set -euo pipefail
lit_test_root=$(cat /tmp/lit-reset-qa-current)
[[ "$lit_test_root" == /tmp/lit-reset-qa.* && -f "$lit_test_root/site.sqlite" ]] || exit 1
cp tests/lit_contribution/local-navigation-test.py "$lit_test_root/tests/lit_contribution/"
cd "$lit_test_root"
(cd web && exec php -S 127.0.0.1:8099 -t . .ht.router.php) >/tmp/lit-reset-http.log 2>&1 &
lit_http_pid=$!
trap 'kill "$lit_http_pid" 2>/dev/null || true' EXIT
sleep 1
kill -0 "$lit_http_pid" # Refuse to test another process on the same port.
python3 tests/lit_contribution/local-navigation-test.py
