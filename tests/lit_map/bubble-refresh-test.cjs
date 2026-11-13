// Run serially. Changes ONLY the synthetic statewide fixture; restores bytes/mtime.
const {chromium}=require(process.env.LIT_PLAYWRIGHT_PATH||'/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const assert=require('node:assert/strict'),fs=require('node:fs');
const file='web/modules/custom/lit_map/data/survey.statewide.v2.json',original=fs.readFileSync(file),stat=fs.statSync(file),backup=file+'.qa-missing';
assert(!fs.existsSync(backup),'Do not overwrite an existing backup');
const url='https://rutgers-lit-m1.ddev.site/lit-map/bubble-preview?lit_lang=es&lit_county=34003';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 const page=await browser.newPage();const checks=[];
 async function refreshUntil(test){
  // DDEV Mutagen must first sync Mac edits into the web container.
  for(let i=0;i<15;i++){
   const response=await page.goto(url,{waitUntil:'networkidle'});
   assert.equal(response.status(),200);assert(response.headers()['x-drupal-cache'].startsWith('UNCACHEABLE'));
   if(await test())return;
   await new Promise(r=>setTimeout(r,300));
  }
  throw Error('Fixture did not update on fresh requests after file sync');
 }
 const total=()=>page.evaluate(()=>window.drupalSettings?.litMapBubbles?.[document.querySelector('.lit-bubbles').id]?.survey.totals['34003'].es);
 const unavailable=async()=>(await page.locator('.lit-bubbles').innerText()).includes('temporarily unavailable');
 try{
  await refreshUntil(async()=>await total()===8796);
  const fixture=JSON.parse(original);fixture.records.find(r=>r.county_geoid==='34003'&&r.language==='es').response_count+=7;
  const changed=JSON.stringify(fixture,null,2)+'\n';assert.equal(Buffer.byteLength(changed),original.length);
  fs.writeFileSync(file,changed);fs.utimesSync(file,stat.atime,stat.mtime);
  await refreshUntil(async()=>await total()===8803);
  assert((await page.locator('[data-results]').innerText()).includes('8,803 survey responses'));
  checks.push('Same-URL requests update normalized and displayed counts 8796 -> 8803 after file sync, with unchanged bytes/mtime and no cache rebuild');
  fs.writeFileSync(file,'{malformed');await refreshUntil(unavailable);
  assert(!await page.evaluate(()=>Boolean(drupalSettings.litMapBubbles)));assert(!(await page.content()).includes('/var/www/html'));
  checks.push('Malformed fixture fails closed with friendly message, no metrics or filesystem details');
  fs.writeFileSync(file,original);await refreshUntil(async()=>await total()===8796);
  fs.renameSync(file,backup);await refreshUntil(unavailable);
  assert(!await page.evaluate(()=>Boolean(drupalSettings.litMapBubbles)));
  checks.push('Missing fixture fails closed without filesystem details');
 }finally{
  if(fs.existsSync(backup))fs.renameSync(backup,file);
  fs.writeFileSync(file,original);fs.utimesSync(file,stat.atime,stat.mtime);
  try{await refreshUntil(async()=>await total()===8796);assert(fs.readFileSync(file).equals(original));checks.push('Original fixture restored byte-for-byte and fresh page returns original total');}finally{await browser.close();}
 }
 fs.writeFileSync('docs/bubbles/cache-results.json',JSON.stringify({checks,fixtureRestored:true},null,2)+'\n');console.log(`${checks.length} fixture freshness/failure checks passed; original restored.`);
})().catch(e=>{console.error(e);process.exit(1);});
