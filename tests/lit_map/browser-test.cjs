// Development QA only. Uses the existing temporary driver and installed Chrome.
const {chromium} = require(process.env.LIT_PLAYWRIGHT_PATH || '/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = 'https://rutgers-lit-m1.ddev.site/lit-map/interaction-preview';
const output = path.resolve('docs/interactions/screenshots');
fs.mkdirSync(output, {recursive: true});

(async () => {
  const browser = await chromium.launch({executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true});
  const page = await browser.newPage({viewport: {width: 1440, height: 1100}});
  const errors = [];
  const checks = [];
  page.on('pageerror', e => errors.push(e.message));
  const check = (value, name) => { assert(value, name); checks.push(name); };
  const county = page.locator('[data-county]');
  const town = page.locator('[data-municipality]');
  const toggle = page.locator('[data-breakdown]');
  const lang = id => page.locator(`[data-languages] input[value="${id}"]`);
  const result = () => page.locator('[data-results]').innerText();
  const reset = () => page.locator('[data-reset]').click();
  const go = async (query = '') => {
    const response = await page.goto(base + query, {waitUntil: 'networkidle'});
    assert.equal(response.status(), 200);
    await page.locator('[data-controls]').waitFor({state: 'visible'});
    return response;
  };
  try {
    const response = await go('?campaign=qa#demo');
    check((response.headers()['x-drupal-cache'] || '').startsWith('UNCACHEABLE'), 'Anonymous page cache disabled');
    check((response.headers()['x-drupal-dynamic-cache'] || '').startsWith('UNCACHEABLE'), 'Dynamic page cache disabled');
    check(await county.inputValue() === '' && await page.locator('[data-languages] input:checked').count() === 0, 'No initial county or language');
    check(await town.isDisabled() && !await toggle.isChecked(), 'Breakdown initially off and municipality disabled');
    check((await result()).includes('Choose a language or county'), 'Initial prompt');
    await page.screenshot({path: path.join(output, 'initial-desktop.png'), fullPage: true});

    await county.selectOption('34003');
    const countyOnly = await result();
    check(countyOnly.includes('20 survey responses') && countyOnly.includes('14 survey responses') && countyOnly.includes('8 survey responses') && !countyOnly.includes('Portuguese'), 'County-only lists all available languages');
    await town.selectOption('hackensack');
    check(await toggle.isChecked(), 'Municipality before language automatically enables visible breakdown');
    await lang('es').check();
    const firstOrder = await result();
    check((await page.locator('[data-municipality-result="hackensack"]').innerText()).includes('12 survey responses'), 'Municipality-specific counts');
    await reset();
    await lang('es').check();
    await county.selectOption('34003');
    await town.selectOption('hackensack');
    check(await result() === firstOrder, 'Language-first and county/municipality-first give identical results');
    await toggle.uncheck();
    check(await town.inputValue() === '' && !new URL(page.url()).searchParams.has('lit_municipality'), 'Turning breakdown off clears visible and URL municipality filter');
    await toggle.check();
    check((await page.locator('[data-municipality-result="_unspecified"]').innerText()).includes('5 survey responses'), 'Unspecified municipality count included in breakdown');
    await town.selectOption('fort-lee');
    await county.selectOption('34013');
    check(await town.inputValue() === '', 'Changing county clears incompatible municipality');
    await town.selectOption('newark');
    await lang('en').check();
    const selectedResult = await result();
    check(selectedResult.includes('26 survey responses') && selectedResult.includes('16 survey responses') && !selectedResult.includes('42 survey responses'), 'Multiple languages remain separate, no combined-person total');
    check(await page.locator('.lit-map-county.has-data').count() === 4, 'Four matching counties highlighted');
    check((await page.locator('[data-callout-county="34017"]').innerText()).includes('28 survey responses'), 'Readable on-map county callout totals');
    await page.screenshot({path: path.join(output, 'selected-desktop.png'), fullPage: true});
    const beforeRefresh = page.url();
    await page.reload({waitUntil: 'networkidle'});
    check(await result() === selectedResult && page.url() === beforeRefresh, 'Refresh restores all URL state');
    await town.selectOption('montclair');
    await page.goBack();
    await page.waitForFunction(() => document.querySelector('[data-municipality]').value === 'newark');
    check(await town.inputValue() === 'newark', 'Back restores municipality');
    await page.goForward();
    await page.waitForFunction(() => document.querySelector('[data-municipality]').value === 'montclair');
    check(await town.inputValue() === 'montclair', 'Forward restores municipality');
    await toggle.uncheck();
    await page.goBack();
    await page.waitForFunction(() => document.querySelector('[data-breakdown]').checked && document.querySelector('[data-municipality]').value === 'montclair');
    check(await toggle.isChecked(), 'Back restores breakdown and municipality together');
    await reset();
    const url = new URL(page.url());
    check(url.searchParams.get('campaign') === 'qa' && url.hash === '#demo' && ![...url.searchParams.keys()].some(k => k.startsWith('lit_')), 'Reset clears only owned query parameters; preserves unrelated query/hash');
    await page.goBack();
    await page.waitForFunction(() => document.querySelector('[data-municipality]').value === 'montclair');
    check(await county.inputValue() === '34013' && await toggle.isChecked() && await page.locator('[data-languages] input:checked').count() === 2, 'Back after reset restores county, languages, municipality, and toggle');
    await page.goForward();
    await page.waitForFunction(() => document.querySelector('[data-county]').value === '');
    check(await page.locator('[data-languages] input:checked').count() === 0 && !await toggle.isChecked(), 'Forward after reset restores empty state');
    await lang('ko').check();
    await county.selectOption('34017');
    check((await result()).includes('0 survey responses'), 'Explicit zero shown');
    await county.selectOption('34013');
    check((await result()).includes('No data available') && !(await result()).includes('0 survey responses'), 'Missing language record is not zero');
    await county.selectOption('34001');
    check((await result()).includes('No data available') && !(await result()).includes('0 survey responses'), 'Unrecorded county is not zero');
    await page.locator('[data-clear-languages]').click();
    check(await county.inputValue() === '34001' && await page.locator('[data-languages] input:checked').count() === 0, 'Clear languages preserves county');
    await go('?campaign=kept&lit_lang=es&lit_lang=es&lit_lang=%3Cscript%3E&lit_county=34003&lit_breakdown=1&lit_municipality=newark');
    check(await page.locator('[data-languages] input:checked').count() === 1 && await town.inputValue() === '' && new URL(page.url()).searchParams.get('campaign') === 'kept', 'Invalid and duplicate URL values ignored safely; mismatched municipality removed');
    await go('?lit_county=bad&lit_municipality=hackensack&lit_breakdown=wrong&lit_lang=__proto__');
    check(await county.inputValue() === '' && await town.inputValue() === '' && !await toggle.isChecked() && await page.locator('[data-languages] input:checked').count() === 0, 'Invalid county/language/breakdown URL values reset safely');

    // Keyboard: native language checkbox, county control, roving SVG buttons.
    await lang('es').focus(); await page.keyboard.press('Space');
    check(await lang('es').isChecked(), 'Language checkbox works by keyboard');
    // Headless macOS does not expose native popup arrow selection; typeahead is native keyboard operation.
    await county.focus(); await page.keyboard.press('b'); await page.keyboard.press('Enter');
    check(await county.inputValue() === '34003', 'County selector native keyboard typeahead works');
    await page.locator('.lit-map-county').first().focus();
    await page.keyboard.press('ArrowRight'); await page.keyboard.press('Space');
    check(await county.inputValue() === '34003', 'SVG arrow navigation and Space selection work');
    const focusStyle = await page.evaluate(() => getComputedStyle(document.activeElement).stroke);
    check(focusStyle === 'rgb(0, 99, 206)', 'Visible SVG keyboard focus style');
    await toggle.focus(); await page.keyboard.press('Space');
    check(await toggle.isChecked(), 'Breakdown toggle works by keyboard');
    await page.evaluate(() => { Drupal.attachBehaviors(document, drupalSettings); Drupal.attachBehaviors(document, drupalSettings); });
    check(await page.locator('[data-languages] input').count() === 4, 'Drupal once prevents duplicate attachment');
    let network = 0;
    const observe = () => { network++; };
    page.on('request', observe);
    await lang('en').check(); await town.selectOption('fort-lee'); await toggle.uncheck();
    page.off('request', observe);
    check(network === 0, 'Filtering makes no AJAX or navigation requests');

    const layouts = [];
    for (const width of [1440, 390, 320]) {
      await page.setViewportSize({width, height: 1000});
      await go('?lit_lang=en&lit_lang=es&lit_lang=ko&lit_lang=pt&lit_county=34013&lit_breakdown=1');
      const layout = await page.evaluate(() => {
        const svg = document.querySelector('.lit-map-geometry');
        const box = svg.getBoundingClientRect();
        return {width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
          aspectRatio: box.width / box.height, expectedRatio: svg.viewBox.baseVal.width / svg.viewBox.baseVal.height,
          calloutFontSize: parseFloat(getComputedStyle(document.querySelector('.lit-interaction__callout dd')).fontSize),
          callouts: document.querySelectorAll('[data-callout-county]').length};
      });
      check(layout.scrollWidth <= width && Math.abs(layout.aspectRatio - layout.expectedRatio) < .001 && layout.calloutFontSize >= 14 && layout.callouts === 4, `Responsive layout and legible callouts at ${width}px`);
      layouts.push(layout);
      await page.screenshot({path: path.join(output, `all-languages-${width}.png`), fullPage: true});
    }
    const mobile = await browser.newPage({viewport: {width: 390, height: 844}, hasTouch: true, isMobile: true});
    await mobile.goto(base, {waitUntil: 'networkidle'});
    await mobile.locator('[data-languages] input[value="es"]').tap();
    await mobile.locator('[data-callout-county="34003"] button').tap();
    check(await mobile.locator('[data-county]').inputValue() === '34003', 'Touch callout provides a large county-selection target');
    await mobile.locator('[data-results]').scrollIntoViewIfNeeded();
    const noticePosition = await mobile.locator('.lit-interaction__notice').boundingBox();
    check(noticePosition.y >= -1 && noticePosition.y < 2, 'Synthetic notice stays visible while scrolling mobile results');
    await mobile.screenshot({path: path.join(output, 'mobile-results-viewport.png')});
    await mobile.close();
    check(errors.length === 0, 'No browser JavaScript errors');
    fs.writeFileSync('docs/interactions/browser-checks.json', JSON.stringify({checks, layouts, errors}, null, 2) + '\n');
    console.log(`PASS: ${checks.length} browser checks.`);
  } finally { await browser.close(); }
})().catch(e => {console.error(e); process.exit(1);});
