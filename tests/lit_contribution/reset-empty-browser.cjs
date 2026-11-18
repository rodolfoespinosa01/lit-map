// Anonymous current-site navigation; empty map is served only by disposable site.
const {chromium}=require('/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const {execFile}=require('node:child_process');
const {promisify}=require('node:util');
const assert=require('node:assert/strict');
const run=promisify(execFile);
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const live=await browser.newPage({ignoreHTTPSErrors:true});
  await live.goto('https://rutgers-lit-m1.ddev.site/');
  for(const [name,path] of [['View map','/lit-map/contribution-preview'],['Superadmin login','/lit/superadmin/login'],['Administrator login','/lit/administrator/login']])assert.equal(await live.getByRole('link',{name,exact:true}).getAttribute('href'),path);
  assert.equal(await live.locator('input[type=password]').count(),0);
  console.log('PASS actual local root shows public landing and three correct links');
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://lit-reset.test/**',async route=>{
   const u=new URL(route.request().url());
   const {stdout}=await run('ddev',['exec','--raw','--','curl','-sS','-H','Host: lit-reset.test','-D','-','http://127.0.0.1:8099'+u.pathname+u.search],{encoding:'buffer',maxBuffer:40*1024*1024});
   const split=stdout.indexOf('\r\n\r\n');assert(split>=0);const lines=stdout.subarray(0,split).toString().split('\r\n');const status=Number(lines.shift().split(' ')[1]);const headers={};
   for(const line of lines){const i=line.indexOf(':');if(i<0)continue;const k=line.slice(0,i).toLowerCase();if(!['transfer-encoding','content-length','connection','content-encoding'].includes(k))headers[k]=line.slice(i+1).trim();}
   await route.fulfill({status,headers,body:stdout.subarray(split+4)});
  });
  assert.equal((await page.goto('http://lit-reset.test/lit-map/contribution-preview',{waitUntil:'networkidle'})).status(),200);
  await page.waitForTimeout(500);
  const text=await page.locator('body').innerText();assert(text.includes('No data available'),'Empty map text missing');
  assert.equal(errors.length,0,'Empty map has no JavaScript exceptions');
  assert(await page.locator('svg').count()>0,'Map geography rendered');
  console.log('PASS empty map renders geography and No data available without JavaScript errors');
 }finally{await browser.close();}
})().catch(e=>{console.error('Browser verification failed:',e.name,e.message.split('\n')[0]);process.exit(1);});
