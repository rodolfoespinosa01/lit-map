// Changes ONLY the module-owned synthetic fixture, and restores it in finally.
const {chromium} = require(process.env.LIT_PLAYWRIGHT_PATH || '/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const fixture = 'web/modules/custom/lit_map/data/survey.synthetic.v2.json';
const backup = fixture + '.qa-backup';
const original = fs.readFileSync(fixture);
const metadata = fs.statSync(fixture);
assert(!fs.existsSync(backup), 'Unexpected backup exists; do not overwrite it.');
const url = 'https://rutgers-lit-m1.ddev.site/lit-map/interaction-preview?lit_lang=es&lit_county=34003';

(async () => {
  const browser = await chromium.launch({executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true});
  const page = await browser.newPage();
  const checks = [];
  async function refreshUntil(test) {
    // Mutagen may need a moment to sync host edits into the container.
    for (let i = 0; i < 15; i++) {
      const response = await page.goto(url, {waitUntil: 'networkidle'});
      assert.equal(response.status(), 200);
      assert((response.headers()['x-drupal-cache'] || '').startsWith('UNCACHEABLE'));
      if (await test()) return;
      await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw new Error('Changed fixture was not reflected on a fresh request.');
  }
  const totalIs = count => async () => {
    const section = page.locator('[data-county-totals="34003"]');
    return await section.count() === 1 && (await section.innerText()).includes(`${count} survey responses`);
  };
  try {
    await refreshUntil(totalIs(20));
    const changed = JSON.parse(original);
    changed.records[0].response_count = 13;
    const replacement = JSON.stringify(changed, null, 2) + '\n';
    assert.equal(Buffer.byteLength(replacement), original.length);
    fs.writeFileSync(fixture, replacement);
    fs.utimesSync(fixture, metadata.atime, metadata.mtime);
    await refreshUntil(totalIs(21));
    checks.push('Same-URL requests show changed fixture values (20 -> 21), with unchanged byte length and mtime, without drush cr.');
    fs.writeFileSync(fixture, '{ malformed JSON');
    await refreshUntil(async () => await page.locator('.lit-interaction__error').count() === 1);
    assert.equal(await page.locator('[data-controls]').count(), 0);
    assert.equal(await page.evaluate(() => Boolean(window.drupalSettings?.litMapPreview)), false);
    assert(!(await page.content()).includes('/var/www/html'));
    checks.push('Malformed JSON produces friendly fallback; no raw data/settings or filesystem path exposed.');
    fs.writeFileSync(fixture, original);
    await refreshUntil(totalIs(20));
    fs.renameSync(fixture, backup);
    await refreshUntil(async () => await page.locator('.lit-interaction__error').count() === 1);
    assert.equal(await page.evaluate(() => Boolean(window.drupalSettings?.litMapPreview)), false);
    checks.push('Missing fixture produces the same friendly fallback.');
  } finally {
    if (fs.existsSync(backup)) fs.renameSync(backup, fixture);
    fs.writeFileSync(fixture, original);
    fs.utimesSync(fixture, metadata.atime, metadata.mtime);
    try {
      await refreshUntil(totalIs(20));
      assert(fs.readFileSync(fixture).equals(original));
      checks.push('Original fixture restored byte-for-byte; fresh request shows 20 again without cache rebuild.');
    } finally { await browser.close(); }
  }
  fs.writeFileSync('docs/interactions/fixture-refresh-checks.json', JSON.stringify({checks}, null, 2) + '\n');
  console.log('PASS: fixture freshness, malformed/missing fallback, restoration.');
})().catch(e => { console.error(e); process.exit(1); });
