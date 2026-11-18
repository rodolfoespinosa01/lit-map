"""Explicit DDEV-only development opt-in; no account or survey records changed."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
p=root/'web/sites/default/settings.php'
text=p.read_text()
marker='// LiT explicit local survey development opt-in.'
addition="\n"+marker+"\nif (getenv('IS_DDEV_PROJECT') === 'true' && is_file($app_root . '/../private/lit-local-development.php')) {\n  include $app_root . '/../private/lit-local-development.php';\n}\n"
if marker not in text:p.write_text(text+addition)
flag=root/'private/lit-local-development.php'
if not flag.exists():
    with flag.open('x') as f:f.write("<?php\n// Local demonstration only. Never deploy this configuration.\n$settings['lit_local_demo_enabled'] = TRUE;\n")
    flag.chmod(0o600)
print('Explicit development include installed; existing settings preserved. Run configure-local-survey.php to choose Local mode.')

# Reset remains a separate, explicit opt-in, never implied by survey mode.
import sys
if '--enable-reset' in sys.argv:
    content=flag.read_text()
    if "$settings['lit_local_reset_enabled']" not in content:
        flag.write_text(content+"\n$settings['lit_local_reset_enabled'] = TRUE;\n")
    print('Local reset tool explicitly enabled. No data reset performed.')
