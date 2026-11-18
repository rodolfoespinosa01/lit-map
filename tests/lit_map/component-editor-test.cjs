const {chromium}=require(process.env.LIT_PLAYWRIGHT_PATH||'/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const {execFileSync}=require('node:child_process');const assert=require('node:assert/strict');
const base=process.env.LIT_TEST_BASE||'https://rutgers-lit-m1.ddev.site';const cwd=process.env.LIT_TEST_CWD||process.cwd();
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const token=execFileSync('ddev',['drush','user:login','--uid=1','--no-browser','--uri='+base],{cwd,encoding:'utf8'}).trim();
  const page=await browser.newPage();await page.goto(token,{waitUntil:'networkidle'});await page.goto(base+'/node/add/lit_demo_page',{waitUntil:'networkidle'});
  const field=(name,suffix='[0][value]')=>page.locator(`[name="field_lit_components[0][subform][${name}]${suffix}"]`);
  await page.locator('[name="title[0][value]"]').fill('LiT editor verification — test page');
  await field('field_lit_heading').fill('Editor-created map');
  await field('field_lit_intro').fill('Explicit editor workflow test.');
  const choose=async(input,text)=>{await input.fill(text);await page.locator('.ui-menu-item').filter({hasText:text}).first().click();};
  await choose(field('field_lit_counties','[0][target_id]'),'Bergen');
  await choose(field('field_lit_languages','[0][target_id]'),'English');
  await choose(field('field_lit_resources','[0][target_id]'),'Local test document');
  await field('field_lit_source','').selectOption('informational');
  await page.locator('[name="status[value]"]').check();
  await page.getByRole('button',{name:'Save',exact:true}).click();await page.waitForURL(/\/node\/\d+$/);
  const publicUrl=page.url();const anonymous=await browser.newContext();const viewer=await anonymous.newPage();await viewer.goto(publicUrl,{waitUntil:'networkidle'});
  assert.equal(await viewer.locator('.lit-bubbles h2').textContent(),'Editor-created map');
  assert.equal(await viewer.locator('select[data-county] option').count(),2);
  assert.equal(await viewer.locator('[data-languages] input').count(),1);
  await viewer.locator('select[data-county]').selectOption('34003');
  assert.equal(await viewer.locator('[data-resource-link]').count(),1);
  await page.goto(publicUrl+'/edit',{waitUntil:'networkidle'});await field('field_lit_heading').fill('Edited map heading');
  await page.getByRole('button',{name:'Save',exact:true}).click();await page.waitForURL(publicUrl);await viewer.reload({waitUntil:'networkidle'});
  assert.equal(await viewer.locator('.lit-bubbles h2').textContent(),'Edited map heading');
  await page.goto(publicUrl+'/edit',{waitUntil:'networkidle'});await page.locator('[name="status[value]"]').uncheck();await page.getByRole('button',{name:'Save',exact:true}).click();await page.waitForURL(publicUrl);
  await anonymous.close();console.log('PASS: editor created a Paragraph page, selected terms/source/Media resource, saved, viewed anonymously, edited and saw updated markup. Test page retained as an unpublished draft.');

 }finally{await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1);});
