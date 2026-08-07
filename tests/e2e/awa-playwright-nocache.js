const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1430, height: 900 },
    bypassCSP: true,
    serviceWorkers: 'block',
  });
  const page = await context.newPage();
  await context.route('**/*', (route) => {
    const headers = { ...route.request().headers(), 'cache-control': 'no-cache', pragma: 'no-cache' };
    route.continue({ headers });
  });

  await page.addInitScript(() => {
    const orig = CSSStyleDeclaration.prototype.setProperty;
    window.__awaSetPropertyLog = [];
    CSSStyleDeclaration.prototype.setProperty = function (prop, value, priority) {
      const owner = this.ownerElement || null;
      if (owner && (owner.matches?.('[data-role="awa-vertical-menu-trigger"]') || owner.classList?.contains('awa-vmenu-trigger-text'))) {
        window.__awaSetPropertyLog.push({ prop, value, stack: (new Error()).stack.split('\n').slice(1, 5).join(' | ') });
      }
      return orig.call(this, prop, value, priority);
    };
  });

  await page.goto('https://awamotos.com/bauletos.html?nocache=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(2500);

  const data = await page.evaluate(async () => {
    const btn = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const txt = btn ? btn.querySelector('.awa-vmenu-trigger-text') : null;
    const sorter = document.querySelector('#sorter');
    const limiter = document.querySelector('#limiter');
    const sorterWrap = document.querySelector('.toolbar-sorter.sorter');
    const limiterWrap = document.querySelector('.field.limiter');
    function info(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return { top: Math.round(r.top), h: Math.round(r.height), marginTop: s.marginTop, display: s.display, alignItems: s.alignItems, inline: el.getAttribute('style') };
    }
    return {
      trigger: info(btn),
      triggerText: info(txt),
      sorter: info(sorter),
      limiter: info(limiter),
      sorterWrap: info(sorterWrap),
      limiterWrap: info(limiterWrap),
      misalign: sorter && limiter ? Math.round(sorter.getBoundingClientRect().top - limiter.getBoundingClientRect().top) : null,
      setPropertyLog: window.__awaSetPropertyLog || [],
      controllerHasTriggerTypography: await fetch(document.querySelector('script[src*="awa-menu-controller"]')?.src || '').then(r => r.text()).then(t => t.includes("triggerText.style.setProperty('font-weight'")).catch(() => null),
    };
  });

  fs.appendFileSync(logPath, JSON.stringify({ sessionId: 'ec818a', runId: 'playwright-nocache-r15', hypothesisId: 'M3', location: 'playwright:nocache', message: 'No-cache verification', data, timestamp: Date.now() }) + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
