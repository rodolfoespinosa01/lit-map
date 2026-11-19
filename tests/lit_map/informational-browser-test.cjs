const {chromium}=require(process.env.LIT_PLAYWRIGHT_PATH||'/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const assert=require('node:assert/strict');const fs=require('node:fs');
const base=process.env.LIT_TEST_BASE||'https://rutgers-lit-m1.ddev.site';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try {
  const page=await browser.newPage({ignoreHTTPSErrors:true,viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  assert.equal((await page.goto(base+'/',{waitUntil:'networkidle'})).status(),200);assert.equal(await page.locator('.lit-bubbles').count(),2);
  const text=await page.textContent('body');assert(text.includes('Synthetic demonstration data.'));assert(!/approved demonstration|Approved publication|review and publication|Owner dashboard/.test(text));
  const payload=await page.evaluate(()=>JSON.stringify(drupalSettings.litMapBubbles));assert(!/response_id|survey_id|email|submitted_at|"questions"|"resolutions"/.test(payload));
  const count=await page.evaluate(()=>Object.values(drupalSettings.litMapBubbles).find(x=>x.survey.counties.length===21).survey.combinations.reduce((n,g)=>n+g.count,0));assert.equal(count,10000);
  const root=page.locator('.lit-bubbles').first();await root.locator('.lit-bubbles__stage').focus();await page.keyboard.press('+');await page.keyboard.press('ArrowRight');await page.keyboard.press('Home');
  fs.mkdirSync('test-results',{recursive:true});await page.screenshot({path:'test-results/informational-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:'test-results/informational-mobile.png',fullPage:true});
  const login=await page.goto(base+'/user/login');assert.equal(login.status(),200);assert.equal(await page.locator('input[name=name]').count(),1);
  assert.equal((await page.request.get(base+'/admin/content/lit-map-dataset')).status(),403);
  for(const path of ['/lit/local-survey','/lit/sign-in','/lit/superadmin/login','/lit/administrator/login','/lit/sign-in/confirm','/admin/lit','/admin/people/lit-administrators','/admin/content/lit-contributions','/admin/content/lit-contributions/import','/admin/config/lit-contributions','/admin/content/lit-contributions/publish','/admin/content/lit-contributions/synchronize'])assert.equal((await page.request.get(base+path)).status(),404,path);
  const redirect=await page.request.get(base+'/lit-map/contribution-preview?lit_county=34013',{maxRedirects:0});assert.equal(redirect.status(),302);assert(redirect.headers().location.includes('lit_county=34013'));
  assert.deepEqual(errors,[]);console.log('PASS: root, 10,000 responses, privacy, standard login, protected upload, retired routes, redirect filters, keyboard, desktop/mobile screenshots.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1);});
