"""HTTP sessions on disposable PHP server; fictional credentials only."""
import urllib.request, urllib.parse, http.cookiejar
from html.parser import HTMLParser
class Form(HTMLParser):
    def __init__(self): super().__init__(); self.values={}
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='input' and a.get('name') and a.get('type') not in ('checkbox','radio'): self.values[a['name']]=a.get('value','')
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
base='http://127.0.0.1:8099'
def get(op,path):
    r=op.open(base+path); return r,r.read().decode()
def login(op,path,email,password):
    r,h=get(op,path+'?destination=https://example.org/unsafe'); f=Form();f.feed(h);f.values.update(email=email,pass_=password);f.values['pass']=f.values.pop('pass_')
    r=op.open(base+path+'?destination=https://example.org/unsafe',urllib.parse.urlencode(f.values).encode());h=r.read().decode();
    return r,h
anon=session();r,h=get(anon,'/')
for path,label in [('/lit-map/contribution-preview','View map'),('/lit/superadmin/login','Superadmin login'),('/lit/administrator/login','Administrator login')]:
    assert f'href="{path}"' in h and label in h; assert get(anon,path)[0].status==200
assert 'name="pass"' not in h
assert get(anon,'/user/login')[0].status==200
print('PASS anonymous landing, three links, public map and emergency login')
for path,email,password,target in [('/lit/superadmin/login','owner@example.org','DemoOwnerPassword123!','/admin/lit'),('/lit/administrator/login','admin@example.org','DemoAdminPassword123!','/admin/content/lit-contributions/pending')]:
    op=session();r,h=login(op,path,email,password);assert urllib.parse.urlparse(r.url).path==target,(r.url,target)
    assert 'Log out' in h
    _,h=get(op,'/');assert 'Signed in as ' in h and target in h
    r,h=get(op,'/user/logout/confirm');f=Form();f.feed(h)
    # Core logout confirmation uses a form; submit its hidden fields and button.
    r=op.open(base+'/user/logout/confirm',urllib.parse.urlencode(f.values).encode());r.read()
    r,h=get(op,'/');assert 'Signed in as ' not in h
    assert get(op,'/lit-map/contribution-preview')[0].status==200
print('PASS owner/admin fixed redirects, unsafe destinations ignored, logout and logged-out map')

emergency=session();_,h=get(emergency,'/user/login');f=Form();f.feed(h)
f.values.update(name='emergency-demo');f.values['pass']='DisposableEmergencyOnly'
r=emergency.open(base+'/user/login',urllib.parse.urlencode(f.values).encode());r.read()
assert urllib.parse.urlparse(r.url).path!='/user/login'
print('PASS UID 1 signs in through standard Drupal login with unchanged credentials')
