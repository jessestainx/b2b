const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1430, height: 900 }, serviceWorkers: 'block' });
  const page = await context.newPage();
  await page.goto('https://awamotos.com/bauletos.html?final=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(4000);

  const data = await page.evaluate(() => {
    const btn = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const txt = btn ? btn.querySelector('.awa-vmenu-trigger-text') : null;
    return {
      observed: btn ? btn.dataset.awaTypographyStripObserved : null,
      triggerInline: btn ? btn.getAttribute('style') : null,
      textInline: txt ? txt.getAttribute('style') : null,
      loadedScripts: performance.getEntriesByType('resource').filter(r => r.name.includes('menu-controller')).map(r => r.name),
    };
  });

  fs.appendFileSync(logPath, JSON.stringify({ sessionId: 'ec818a', runId: 'playwright-final-r15b', hypothesisId: 'M6', location: 'playwright:final', message: 'Final guard check', data, timestamp: Date.now() }) + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
