"""Run only inside the disposable site's working directory."""
import os, pathlib, subprocess, re, pty, select, time
assert str(pathlib.Path.cwd()).startswith('/tmp/lit-reset-qa.')
base=['vendor/bin/drush','php:script','scripts/lit-local-reset.php']
env=dict(os.environ,LIT_RESET_PRESET='empty')
preview=subprocess.run(base,env=env,capture_output=True,text=True);assert preview.returncode==0 and 'Preview only' in preview.stdout
no_tty=subprocess.run(base,env=dict(env,LIT_RESET_EXECUTE='yes'),capture_output=True,text=True);assert no_tty.returncode!=0
backup=subprocess.run(base,env=dict(env,LIT_RESET_BACKUP='yes'),capture_output=True,text=True);assert backup.returncode==0
assert re.search(r'Recovery backup: \d{8}-\d{6}-[a-f0-9]{16}',backup.stdout)
master,slave=pty.openpty();p=subprocess.Popen(base,env=dict(env,LIT_RESET_EXECUTE='yes'),stdin=slave,stdout=slave,stderr=slave);os.close(slave)
out=b'';sent=False;deadline=time.monotonic()+120
while p.poll() is None and time.monotonic()<deadline:
    if select.select([master],[],[],1)[0]:
        try: chunk=os.read(master,65536)
        except OSError:break
        out+=chunk
        if b'Type exactly: RESET empty' in out and not sent:os.write(master,b'RESET empty\n');sent=True
p.wait(timeout=5);os.close(master)
assert p.returncode==0 and sent and b'Completed.' in out
assert b'@' not in out and b'DemoOwnerPassword' not in out
print('PASS CLI preview, standalone backup, noninteractive refusal, typed execution and private-safe output')
