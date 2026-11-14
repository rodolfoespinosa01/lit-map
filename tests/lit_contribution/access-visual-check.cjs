// Read-only final screenshots and unsafe redirect check. No administrator/data changes.
const {chromium}=require('/private/tmp/lit-geometry-qa/node_modules/playwright-core'),{execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
const origin='https://rutgers-lit-m1.ddev.site',out='docs/contribution/screenshots';
const run=code=>execFileSync('ddev',['drush','php:eval',code],{encoding:'utf8'}).trim();
const mail=path=>JSON.parse(execFileSync('ddev',['exec','curl','-fsS','http://localhost:8025/api/v1/'+path],{encoding:'utf8'}));
(async()=>{const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
 const p=await browser.newPage({viewport:{width:1440,height:1000}});let external=0;await p.route('https://example.org/**',route=>{external++;return route.abort();});
 const email=run('echo \\Drupal\\user\\Entity\\User::load(\\Drupal::service("lit_contribution.owner")->ownerId())->getEmail();');
 await p.goto(origin+'/lit/sign-in');await p.screenshot({path:out+'/signin-desktop.png',fullPage:true});await p.getByLabel('Email address').fill(email);await p.getByRole('button',{name:'Email sign-in link',exact:true}).click();
 const message=mail('messages').messages.find(m=>m.Subject==='LiT local administrator sign-in'&&m.To.some(t=>t.Address===email));const link=mail('message/'+message.ID).Text.match(/https?:\/\/[^\s]+#token=[a-f0-9]{64}/)[0];const url=new URL(link);url.searchParams.set('destination','https://example.org/unsafe');
 await p.goto(url.href);await p.getByRole('button',{name:'Confirm sign-in',exact:true}).click();assert(new URL(p.url()).pathname==='/admin/lit');await p.screenshot({path:out+'/owner-dashboard.png',fullPage:true});assert.equal(external,0);
 await p.goto(origin+'/admin/people/lit-administrators');assert.equal(await p.getByRole('button',{name:'Add administrator',exact:true}).count(),1);await p.screenshot({path:out+'/owner-administrators.png',fullPage:true});
 await p.goto(origin+'/admin/content/lit-contributions');await p.getByRole('link',{name:/SYN_LIT_10000_V1 \/ R_LARGE_10003/}).click();await p.locator('details summary').first().click();await p.screenshot({path:out+'/review-pending.png',fullPage:true});
 const mobile=await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});await mobile.goto(origin+'/lit/sign-in');await mobile.screenshot({path:out+'/signin-mobile.png',fullPage:true});assert(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
 console.log('Unsafe destination ignored; owner/review/sign-in screenshots captured; mobile sign-in has no horizontal overflow.');
}finally{await browser.close();}})().catch(e=>{console.error(String(e).replace(/#token=[a-f0-9]{64}/g,'#token=[redacted]'));process.exit(1);});
