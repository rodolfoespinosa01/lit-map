#!/usr/bin/env bash
set -euo pipefail
lit_test_root=$(cat /tmp/lit-reset-qa-current)
[[ "$lit_test_root" == /tmp/lit-reset-qa.* && -f "$lit_test_root/site.sqlite" ]] || exit 1
cd "$lit_test_root/web"
echo $$ > /tmp/lit-reset-http.pid
exec php -S 127.0.0.1:8099 -t . .ht.router.php
