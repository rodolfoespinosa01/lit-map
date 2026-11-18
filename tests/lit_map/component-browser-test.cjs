const {chromium}=require(process.env.LIT_PLAYWRIGHT_PATH||'/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const assert=require('node:assert/strict');
const url=process.env.LIT_COMPONENT_URL||'https://rutgers-lit-m1.ddev.site/node/3';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  assert.equal((await page.goto(url,{waitUntil:'networkidle'})).status(),200);
  const maps=page.locator('.lit-bubbles');assert.equal(await maps.count(),2);
  const first=maps.nth(0),second=maps.nth(1);
  const expand=async()=>{await page.locator('[data-filter-disclosure]').evaluateAll(nodes=>nodes.forEach(n=>n.open=true));};await expand();
  assert.equal(await first.locator('select[data-county] option').count(),22);
  assert.equal(await second.locator('select[data-county] option').count(),2);
  assert.equal(await second.locator('[data-languages] input').count(),2);
  assert.equal(await second.locator('[data-breakdown]').isVisible(),false);
  await first.locator('select[data-county]').selectOption('34013');
  await second.locator('select[data-county]').selectOption('34003');
  await page.goBack();assert.equal(await first.locator('select[data-county]').inputValue(),'34013');assert.equal(await second.locator('select[data-county]').inputValue(),'');
  await page.goForward();assert.equal(await second.locator('select[data-county]').inputValue(),'34003');
  if(await first.locator('[data-date-from]').count()){
    const expected=await first.evaluate(root=>{const data=drupalSettings.litMapBubbles[root.id].survey;const day=data.days.find(d=>d.day && d.combinations.some(g=>g.county==='34013'&&g.languages.includes('en')));return day?{day:day.day,count:day.combinations.filter(g=>g.county==='34013'&&g.languages.includes('en')).reduce((n,g)=>n+g.count,0)}:null;});
    if(expected){await first.locator('[data-date-from]').fill(expected.day);await first.locator('[data-date-through]').fill(expected.day);await first.locator('[data-date-through]').blur();assert((await first.locator('[data-combined-count]').first().textContent()).includes(expected.count.toLocaleString()+' matching survey responses'));await first.locator('[data-date-from]').fill('');await first.locator('[data-date-through]').fill('');await first.locator('[data-date-through]').blur();}
  }
  const mediaLink=first.locator('[data-resource-link]').first();
  if(await mediaLink.count()){
    const mediaPage=await browser.newPage();const target=new URL(await mediaLink.getAttribute('href'),url).href;
    assert.equal((await mediaPage.goto(target)).status(),200);assert((await mediaPage.textContent('body')).includes('lit-component-test.txt'));await mediaPage.close();
  }
  const keys=await page.evaluate(()=>Object.values(drupalSettings.litMapBubbles).map(x=>x.stateKey));
  assert(keys.every(k=>new URL(page.url()).searchParams.has(k+'_lit_county')));
  await page.reload({waitUntil:'networkidle'});await expand();
  assert.equal(await first.locator('select[data-county]').inputValue(),'34013');assert.equal(await second.locator('select[data-county]').inputValue(),'34003');
  const before=await first.locator('[data-languages] input').count();
  await page.evaluate(()=>{Drupal.attachBehaviors(document,drupalSettings);Drupal.attachBehaviors(document,drupalSettings);});
  assert.equal(await first.locator('[data-languages] input').count(),before);
  await page.evaluate(()=>{const root=document.querySelector('.lit-bubbles');const parent=root.parentNode;Drupal.detachBehaviors(parent,drupalSettings,'unload');root.remove();parent.append(root);Drupal.attachBehaviors(parent,drupalSettings);});
  assert.equal(await first.locator('[data-languages] input').count(),before);
  assert.equal(await second.locator('select[data-county]').inputValue(),'34003');
  await page.evaluate(()=>{const root=document.querySelector('.lit-bubbles');Drupal.detachBehaviors(root,drupalSettings,'unload');Drupal.attachBehaviors(root,drupalSettings);});
  assert.equal(await first.locator('[data-languages] input').count(),before);
  await expand();await first.locator('[data-languages] input[value="es"]').focus();await page.keyboard.press('Space');
  const duplicates=await page.evaluate(()=>{const ids=[...document.querySelectorAll('[id]')].map(n=>n.id);return ids.filter((id,i)=>ids.indexOf(id)!==i);});assert.deepEqual(duplicates,[]);
  await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  assert.deepEqual(errors,[]);
  const nojs=await browser.newContext({javaScriptEnabled:false});const p=await nojs.newPage();await p.goto(url);assert(await p.locator('noscript table').count()===2);await nojs.close();
  console.log('PASS: normal page; restrictions; two URL states; repeated attach; unload/reinsert; keyboard; unique IDs; mobile; no-JS; no JS errors.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
