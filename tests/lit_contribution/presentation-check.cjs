// Read-only final screenshots after restoring the normal placeholder catalogue.
const {chromium}=require('/private/tmp/lit-geometry-qa/node_modules/playwright-core'),assert=require('node:assert/strict');
const url='https://rutgers-lit-m1.ddev.site/lit-map/contribution-preview',out='docs/contribution/screenshots';
(async()=>{const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});let checks=0;try{
 for(const mobile of [false,true]){
   const page=await browser.newPage({viewport:mobile?{width:390,height:844}:{width:1440,height:1000},isMobile:mobile,hasTouch:mobile,reducedMotion:'reduce'});
   await page.goto(url);assert.equal(await page.locator('[data-bubble]').count(),21);checks++;
   await page.locator('.lit-bubbles__stage').screenshot({path:out+`/final-floating-${mobile?'mobile':'desktop'}.png`});
   const data=await page.evaluate(()=>drupalSettings.litMapBubbles[document.querySelector('.lit-bubbles').id].survey),hack=data.municipalities.find(t=>t.name==='Hackensack city');
   await page.goto(url+`?lit_lang=ko&lit_county=34003&lit_breakdown=1&lit_level=municipality&lit_municipality=${hack.id}`);
   await page.waitForSelector('[data-town-layer]');
   assert(await page.locator('[data-bubble]').evaluateAll(items=>items.every(i=>getComputedStyle(i).opacity==='1')));checks++;
   assert.equal(await page.locator('[data-resource-panel] a').count(),0);checks++;
   assert((await page.locator('[data-selected-details]').innerText()).includes('6 matching survey responses'));checks++;
   await page.locator('.lit-bubbles__stage').screenshot({path:out+`/final-town-${mobile?'mobile':'desktop'}.png`});
   await page.locator('.lit-bubbles__panel').screenshot({path:out+`/final-panel-${mobile?'mobile':'desktop'}.png`});
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));checks++;
   const panel=await page.locator('[data-selected-details]').innerText();await page.locator('[data-bubble]').first().dispatchEvent('mouseenter');assert.equal(await page.locator('[data-selected-details]').innerText(),panel);checks++;
 }
 console.log(checks+' final desktop/mobile visibility, readable town counts, restored placeholders and stable-panel checks passed.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
