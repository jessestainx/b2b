const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1430, height: 900 } });

  await page.addInitScript(() => {
    const orig = CSSStyleDeclaration.prototype.setProperty;
    window.__awaSetPropertyLog = [];
    CSSStyleDeclaration.prototype.setProperty = function (prop, value, priority) {
      const owner = this.ownerElement || null;
      if (
        owner &&
        (owner.matches?.('[data-role="awa-vertical-menu-trigger"]') ||
          owner.classList?.contains('awa-vmenu-trigger-text'))
      ) {
        window.__awaSetPropertyLog.push({ tag: owner.tagName, className: owner.className, prop, value, priority: priority || '' });
      }
      return orig.call(this, prop, value, priority);
    };
  });

  await page.goto('https://awamotos.com/bauletos.html?pw-trace=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(2500);

  const data = await page.evaluate(() => {
    const btn = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const txt = btn ? btn.querySelector('.awa-vmenu-trigger-text') : null;
    return {
      triggerInline: btn ? btn.getAttribute('style') : null,
      textInline: txt ? txt.getAttribute('style') : null,
      setPropertyLog: window.__awaSetPropertyLog || [],
      controllerSrc: Array.from(document.querySelectorAll('script[src]')).filter(s => s.src.includes('menu-controller')).map(s => s.src),
    };
  });

  fs.appendFileSync(logPath, JSON.stringify({ sessionId: 'ec818a', runId: 'playwright-trace-r14', hypothesisId: 'M2', location: 'playwright:setProperty-trace', message: 'Trigger inline source', data, timestamp: Date.now() }) + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
