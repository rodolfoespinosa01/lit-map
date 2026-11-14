// Local QA only; no survey link is followed, and no real source is contacted.
const {chromium}=require(process.env.LIT_PLAYWRIGHT_PATH||'/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const assert=require('node:assert/strict'),fs=require('node:fs'),{execFileSync}=require('node:child_process');
const origin='https://rutgers-lit-m1.ddev.site',adminPath='/admin/content/lit-contributions',publicPath='/lit-map/contribution-preview';
const output='docs/contribution/screenshots';fs.mkdirSync(output,{recursive:true});
const loginUrl=name=>execFileSync('ddev',['drush','user:login','--name='+name,'--no-browser','--uri='+origin],{encoding:'utf8'}).trim().match(/https:\/\/\S+/)[0];
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 const checks=[],errors=[];const check=(v,label)=>{assert(v,label);checks.push(label);};
 const anonymous=await browser.newPage({viewport:{width:1440,height:1100}});
 anonymous.on('pageerror',e=>errors.push(e.message));
 const admin=await browser.newPage({viewport:{width:1440,height:1000}}),reviewer=await browser.newPage(),viewer=await browser.newPage();
 try{
  const routes=[adminPath,adminPath+'/synchronize',adminPath+'/publish','/admin/config/lit-contributions'];
  for(const route of routes){const r=await anonymous.goto(origin+route);check(r.status()===403,'Anonymous denied '+route);}
  for(const path of ['/fixtures/lit-contribution/submissions.v1.json','/private/lit-contribution/','/sites/default/files/lit-contribution/submissions.v1.json']){const r=await anonymous.goto(origin+path);check([403,404].includes(r.status()),'Private source/storage not web-accessible '+path);}
  await admin.goto(loginUrl('admin'),{waitUntil:'networkidle'});
  await reviewer.goto(loginUrl('lit_qa_reviewer'),{waitUntil:'networkidle'});
  await viewer.goto(loginUrl('lit_qa_viewer'),{waitUntil:'networkidle'});
  check((await viewer.goto(origin+adminPath)).status()===403,'Ordinary authenticated user denied private list');
  check((await reviewer.goto(origin+adminPath)).status()===200,'Reviewer permission permits list');
  for(const route of routes.slice(1))check((await reviewer.goto(origin+route)).status()===403,'Reviewer cannot perform privileged operation '+route);
  await reviewer.goto(origin+adminPath);
  const detail=await reviewer.getByRole('link',{name:'SYN_LIT_PROVISIONAL_V1 / R_DEMO_001',exact:true}).getAttribute('href');
  check((await anonymous.goto(origin+detail)).status()===403,'Anonymous denied private detail');
  const privateResponse=await reviewer.goto(origin+detail);
  check(privateResponse.headers()['cache-control'].includes('no-store'),'Private detail uses no-store response policy');
  if((await reviewer.locator('select[name="decision"] option').allTextContents()).some(t=>t.startsWith('Return to pending'))){await reviewer.locator('select[name="decision"]').selectOption('pending');await reviewer.locator('textarea[name="reason"]').fill('Repeatable synthetic QA review reset.');await reviewer.getByRole('button',{name:'Save review decision'}).click();}
  check((await reviewer.locator('body').innerText()).includes('person-001@example.org'),'Reviewer can fetch current private mock response');
  check(await reviewer.locator('input[name="form_token"]').count()===1,'Review action has Drupal Form API CSRF token');
  const before=await reviewer.locator('table').first().innerText();
  const rejectedReview=await reviewer.evaluate(async path=>fetch(path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({form_id:'lit_contribution_decision',decision:'approved',reason:'Forged request without token'})}).then(r=>r.text()),detail);
  check(rejectedReview.includes('The form has become outdated'),'Drupal explicitly rejects review POST without CSRF token');
  await reviewer.reload();check(await reviewer.locator('table').first().innerText()===before,'Forged review POST without token does not mutate metadata');
  await reviewer.locator('select[name="decision"]').selectOption('approved');
  await reviewer.locator('textarea[name="reason"]').fill('Synthetic QA review: provisional fixed-answer response, permission checked.');
  await reviewer.getByRole('button',{name:'Save review decision'}).click();
  check((await reviewer.locator('table').first().innerText()).includes('approved'),'Reviewer can approve with protected form');
  check((await reviewer.locator('table').first().innerText()).includes('lit_qa_reviewer'),'Detail shows reviewer identity');
  check((await reviewer.locator('table').first().innerText()).includes('2026-11-10T09:00:00-05:00'),'Source offset displayed separately from import/review/publication times');
  await reviewer.screenshot({path:output+'/private-detail-desktop.png',fullPage:true});
  // Approve a varied synthetic subset; every response was imported pending first.
  for(let n=2;n<=8;n++){
   await reviewer.goto(origin+adminPath);
   await reviewer.getByRole('link',{name:'SYN_LIT_PROVISIONAL_V1 / R_DEMO_'+String(n).padStart(3,'0'),exact:true}).click();
   const options=await reviewer.locator('select[name="decision"] option').allTextContents();
   if(options.includes('Approve')){await reviewer.locator('select[name="decision"]').selectOption('approved');await reviewer.locator('textarea[name="reason"]').fill('Synthetic QA approval for local aggregate demonstration; no real respondent.');await reviewer.getByRole('button',{name:'Save review decision'}).click();}
  }
  await admin.goto(origin+adminPath+'/synchronize');
  const rejectedSync=await admin.evaluate(async path=>fetch(path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({form_id:'lit_contribution_sync',confirm:'1',op:'Confirm'})}).then(r=>r.text()),adminPath+'/synchronize');
  check(rejectedSync.includes('The form has become outdated'),'Synchronization POST requires valid CSRF token');
  await admin.getByRole('button',{name:'Confirm',exact:true}).click();
  check((await admin.locator('body').innerText()).includes('64 unchanged'),'Protected synchronize form is idempotent');
  await admin.goto(origin+adminPath+'/publish');
  const emptyBefore=await anonymous.goto(origin+publicPath,{waitUntil:'networkidle'});check(emptyBefore.status()===200,'Public approved aggregate route reachable anonymously');
  const prePublish=await anonymous.locator('[data-publication-date]').innerText();
  const rejectedPublication=await admin.evaluate(async path=>fetch(path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({form_id:'lit_contribution_publish',confirm:'1',op:'Confirm'})}).then(r=>r.text()),adminPath+'/publish');
  check(rejectedPublication.includes('The form has become outdated'),'Drupal explicitly rejects publication POST without CSRF token');
  await anonymous.reload({waitUntil:'networkidle'});check(await anonymous.locator('[data-publication-date]').innerText()===prePublish,'Forged publication POST does not publish');
  await admin.getByRole('button',{name:'Confirm',exact:true}).click();
  check((await admin.locator('body').innerText()).includes('Local aggregate published'),'Publisher protected form succeeds');
  await anonymous.goto(origin+publicPath,{waitUntil:'networkidle'});
  check(!(await anonymous.locator('[data-publication-date]').innerText()).includes('No approved'),'Public update date appears after publication');
  const settings=await anonymous.evaluate(()=>drupalSettings.litMapBubbles[document.querySelector('.lit-bubbles').id]);
  check(settings.survey.languages.length===8,'Expanded provisional language catalogue');
  const payload=JSON.stringify(settings),html=await anonymous.content();
  check(!/person-\d|@example\.|R_DEMO|SYN_LIT|email_digest|reviewer_uid|submitted_at|source_updated_at/.test(payload+html),'Public HTML/settings contain no private source or reviewer data');
  check(await anonymous.locator('[data-invitation]').count()===0,'Invitation defaults off');
  const lang=id=>anonymous.locator(`[data-languages] input[value="${id}"]`);
  await lang('en').check();check(await anonymous.locator('[data-bubble="34003"]').getAttribute('data-color-mode')==='solid','One positive selected language is a solid color');
  const radius=await anonymous.locator('[data-bubble="34003"] .disc').getAttribute('r');
  await lang('es').check();check(await anonymous.locator('[data-bubble="34003"] .lit-bubbles__segment').count()===2,'Two positive selected languages have equal segments');
  await lang('ko').check();check(await anonymous.locator('[data-bubble="34003"] .lit-bubbles__segment').count()===3,'Three positive selected languages have equal segments');
  check(await anonymous.locator('[data-bubble="34003"] .disc').getAttribute('r')===radius,'Adding comparison languages does not alter active-language size');
  check(await anonymous.locator('[data-bubble="34003"] .lit-bubbles__segment').evaluateAll(paths=>{const lengths=paths.map(p=>p.getTotalLength());return Math.max(...lengths)-Math.min(...lengths)<.01;}),'Categorical segments have equal angular geometry');
  await anonymous.locator('.lit-bubbles').screenshot({path:output+'/three-languages-desktop.png'});
  for(const id of ['pt','zh','ar','hi','ase'])await lang(id).check();
  check(await anonymous.locator('[data-bubble="34003"]').getAttribute('data-color-mode')==='many' && await anonymous.locator('[data-bubble="34003"] .lit-bubbles__many').count()===1,'More than six positive matches uses documented neutral plus fallback');
  check((await anonymous.locator('[data-bubble="34003"]').getAttribute('aria-label')).includes('American Sign Language'),'Many-language fallback has full text equivalent');
  check(await anonymous.locator('[data-color-legend] li').count()===8,'Accessible legend names all selected language colors');
  await anonymous.locator('.lit-bubbles').screenshot({path:output+'/many-languages-desktop.png'});
  await anonymous.locator('select[data-county]').selectOption('34003');await anonymous.locator('select[data-municipality]').selectOption('_unspecified');
  await anonymous.waitForFunction(()=>document.querySelector('[data-map-status]').textContent.includes('municipality boundaries loaded'));
  check((await anonymous.locator('[data-results]').innerText()).includes('Chinese (provisional grouping): 0 survey responses'),'Explicit zero survives approved publication');
  // Use old M4 dataset to exercise positive subset matches with substantial radii.
  await anonymous.goto(origin+'/lit-map/bubble-preview?lit_lang=en&lit_lang=es&lit_lang=ko&lit_county=34003&lit_breakdown=1&lit_level=municipality',{waitUntil:'networkidle'});
  await anonymous.waitForSelector('[data-town-layer] path');
  const subset=await anonymous.evaluate(()=>{const s=drupalSettings.litMapBubbles[document.querySelector('.lit-bubbles').id].survey;return [...document.querySelectorAll('[data-bubble]')].some(b=>{const expected=['en','es','ko'].filter(l=>(s.breakdown['34003'][b.dataset.bubble]?.[l]??0)>0);return expected.length>0&&expected.length<3&&b.dataset.matches===expected.join(',');});});
  check(subset,'Places with only a subset of selected positive languages get only those colors');
  await anonymous.locator('.lit-bubbles').screenshot({path:output+'/categorical-dense-desktop.png'});
  await admin.goto(origin+'/admin/config/lit-contributions');
  const initialEnabled=await admin.locator('input[name="invitation_enabled"]').isChecked(),initialUrl=await admin.locator('input[name="survey_url"]').inputValue();
  try{
   await admin.locator('input[name="invitation_enabled"]').check();await admin.locator('input[name="survey_url"]').fill('https://example.org/not-a-qualtrics-survey');await admin.getByRole('button',{name:'Save configuration'}).click();
   check((await admin.locator('body').innerText()).includes('Enter a valid HTTPS Qualtrics survey URL'),'Invalid invitation URL rejected by settings form');
   await admin.locator('input[name="survey_url"]').fill('https://example.qualtrics.com/jfe/form/SV_DemoOnly123');await admin.getByRole('button',{name:'Save configuration'}).click();
   await anonymous.goto(origin+publicPath,{waitUntil:'networkidle'});check(await anonymous.locator('[data-invitation]').getAttribute('href')==='https://example.qualtrics.com/jfe/form/SV_DemoOnly123','Enabled invitation uses only the configured valid survey URL');
  }finally{
   await admin.goto(origin+'/admin/config/lit-contributions');await admin.locator('input[name="invitation_enabled"]').setChecked(initialEnabled);await admin.locator('input[name="survey_url"]').fill(initialUrl);await admin.getByRole('button',{name:'Save configuration'}).click();
  }
  await anonymous.reload({waitUntil:'networkidle'});check(await anonymous.locator('[data-invitation]').count()===0,'Disabling invitation hides link; settings restored');
  const mobile=await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
  await mobile.goto(origin+publicPath+'?lit_lang=en&lit_lang=es&lit_lang=ko&lit_county=34003&lit_breakdown=1&lit_level=municipality',{waitUntil:'networkidle'});
  await mobile.waitForSelector('[data-town-layer] path');await mobile.locator('.lit-bubbles__stage').scrollIntoViewIfNeeded();await mobile.screenshot({path:output+'/approved-mobile.png'});
  check(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Approved map has no mobile horizontal overflow');
  await mobile.emulateMedia({reducedMotion:'reduce'});await mobile.waitForFunction(()=>document.querySelector('[data-motion]').disabled);check(!await mobile.locator('[data-motion]').isChecked(),'Approved map preserves reduced-motion control');
  await mobile.locator('[data-color-legend]').scrollIntoViewIfNeeded();await mobile.screenshot({path:output+'/legend-mobile.png'});await mobile.close();
  check(errors.length===0,'No public map JavaScript errors');
  fs.writeFileSync('docs/contribution/browser-results.json',JSON.stringify({checks,errors},null,2)+'\n');console.log(`${checks.length} contribution browser checks passed.`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1);});
