const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1430, height: 900 } });

  await page.addInitScript(() => {
    window.__awaStyleMutations = [];
    const obs = new MutationObserver((records) => {
      records.forEach((rec) => {
        const el = rec.target;
        if (!el.matches?.('[data-role="awa-vertical-menu-trigger"], .awa-vmenu-trigger-text')) return;
        window.__awaStyleMutations.push({
          type: rec.type,
          attr: rec.attributeName,
          value: el.getAttribute('style'),
          stack: (new Error()).stack.split('\n').slice(1, 6).join(' | '),
          t: Date.now(),
        });
      });
    });
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('[data-role="awa-vertical-menu-trigger"], .awa-vmenu-trigger-text').forEach((el) => {
        obs.observe(el, { attributes: true, attributeFilter: ['style'] });
      });
    }, { once: true });
  });

  await page.goto('https://awamotos.com/bauletos.html?style-trace=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(3000);

  const data = await page.evaluate(() => ({
    mutations: window.__awaStyleMutations || [],
    triggerInline: document.querySelector('[data-role="awa-vertical-menu-trigger"]')?.getAttribute('style') || null,
  }));

  fs.appendFileSync(logPath, JSON.stringify({ sessionId: 'ec818a', runId: 'style-mutation-trace', hypothesisId: 'M4', location: 'playwright:style-mutation', message: 'Style attribute mutations on trigger', data, timestamp: Date.now() }) + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
