// Development-only QA. Uses an existing Chrome installation, not a runtime library.
const { chromium } = require(process.env.LIT_PLAYWRIGHT_PATH || '/private/tmp/lit-geometry-qa/node_modules/playwright-core');
const fs = require('node:fs');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
  });
  const results = [];
  try {
    for (const width of [1280, 390, 320]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      const errors = [], remote = [];
      page.on('pageerror', e => errors.push(e.message));
      page.on('request', r => {
        if (new URL(r.url()).hostname !== 'rutgers-lit-m1.ddev.site') remote.push(r.url());
      });
      const response = await page.goto('https://rutgers-lit-m1.ddev.site/lit-map/geometry-preview', {waitUntil: 'networkidle'});
      assert.equal(response.status(), 200);
      const result = await page.evaluate(() => {
        const svg = document.querySelector('.lit-map-geometry');
        const box = svg.getBoundingClientRect();
        const paths = [...svg.querySelectorAll('path')];
        const labels = [...svg.querySelectorAll('text')].map(t => {
          const b = t.getBoundingClientRect();
          return {name: t.textContent, x: b.x, y: b.y, width: b.width, height: b.height};
        });
        const overlaps = [];
        labels.forEach((a, i) => labels.slice(i + 1).forEach(b => {
          if (a.x < b.x+b.width && a.x+a.width > b.x && a.y < b.y+b.height && a.y+a.height > b.y) overlaps.push([a.name,b.name]);
        }));
        return {
          width: innerWidth, pageWidth: document.documentElement.scrollWidth,
          svgWidth: box.width, svgHeight: box.height,
          aspectRatio: box.width / box.height, viewBoxRatio: svg.viewBox.baseVal.width / svg.viewBox.baseVal.height,
          pathCount: paths.length, uniqueKeys: new Set(paths.map(p => p.dataset.countyKey)).size,
          uniqueGeoids: new Set(paths.map(p => p.dataset.geoid)).size, labels: labels.length,
          highlighted: paths.filter(p => getComputedStyle(p).fill === 'rgb(181, 28, 54)').map(p => p.dataset.countyKey).sort(),
          overlappingLabels: overlaps,
          escapedLabels: labels.filter(b => b.x < box.x || b.x+b.width > box.right || b.y < box.y || b.y+b.height > box.bottom).map(b => b.name),
          activeSvgElements: svg.querySelectorAll('script,foreignObject,image,use,a').length,
        };
      });
      assert.equal(result.pathCount, 21);
      assert.equal(result.uniqueKeys, 21);
      assert.equal(result.uniqueGeoids, 21);
      assert.equal(result.labels, 21);
      assert.deepEqual(result.highlighted, ['nj-bergen','nj-essex','nj-hudson','nj-morris']);
      assert(result.pageWidth <= width, 'Horizontal page overflow');
      assert(Math.abs(result.aspectRatio - result.viewBoxRatio) < .001, 'Distorted aspect ratio');
      assert.equal(result.activeSvgElements, 0);
      assert.deepEqual(result.escapedLabels, []);
      assert.deepEqual(result.overlappingLabels, []);
      assert.deepEqual(errors, []);
      assert.deepEqual(remote, []);
      await page.screenshot({path: `/private/tmp/lit-geometry-${width}.png`, fullPage: true});
      results.push({...result, consoleErrors: errors, remoteRequests: remote});
      await page.close();
    }
    fs.writeFileSync('/private/tmp/lit-geometry-browser.json', JSON.stringify(results, null, 2) + '\n');
    console.log(JSON.stringify(results, null, 2));
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
