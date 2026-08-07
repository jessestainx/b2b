const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1430, height: 900 }, serviceWorkers: 'block' });
  const page = await context.newPage();
  await page.goto('https://awamotos.com/bauletos.html?verify-r15b=' + Date.now(), { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForTimeout(3500);

  const data = await page.evaluate(() => {
    const btn = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const txt = btn ? btn.querySelector('.awa-vmenu-trigger-text') : null;
    const sorter = document.querySelector('#sorter');
    const limiter = document.querySelector('#limiter');
    const resources = performance.getEntriesByType('resource')
      .filter(r => /menu-controller|menu-bootstrap|align-grid/.test(r.name))
      .map(r => r.name);
    return {
      menuV2: !!window.__AWA_MENU_V2,
      menuReady: document.body && document.body.classList.contains('awa-menu-v2-ready'),
      observed: btn ? btn.dataset.awaTypographyStripObserved : null,
      triggerInline: btn ? btn.getAttribute('style') : null,
      textInline: txt ? txt.getAttribute('style') : null,
      textFontSize: txt ? getComputedStyle(txt).fontSize : null,
      textFontWeight: txt ? getComputedStyle(txt).fontWeight : null,
      textLineHeight: txt ? getComputedStyle(txt).lineHeight : null,
      misalign: sorter && limiter ? Math.round(sorter.getBoundingClientRect().top - limiter.getBoundingClientRect().top) : null,
      sorterTop: sorter ? Math.round(sorter.getBoundingClientRect().top) : null,
      limiterTop: limiter ? Math.round(limiter.getBoundingClientRect().top) : null,
      hasBootstrapConfig: !!document.getElementById('awa-menu-bootstrap-config'),
      hasBootstrapRuntime: !!document.getElementById('awa-menu-bootstrap-runtime'),
      resources,
    };
  });

  const entries = [
    { sessionId: 'ec818a', runId: 'user-repro-verify', hypothesisId: 'M', location: 'playwright:departamentos', message: 'Departamentos inline/guard state', data: { observed: data.observed, triggerInline: data.triggerInline, textInline: data.textInline, textFontSize: data.textFontSize, textFontWeight: data.textFontWeight, textLineHeight: data.textLineHeight, menuV2: data.menuV2, menuReady: data.menuReady, hasBootstrapConfig: data.hasBootstrapConfig, hasBootstrapRuntime: data.hasBootstrapRuntime, resources: data.resources }, timestamp: Date.now() },
    { sessionId: 'ec818a', runId: 'user-repro-verify', hypothesisId: 'L2', location: 'playwright:toolbar', message: 'Toolbar alignment', data: { misalign: data.misalign, sorterTop: data.sorterTop, limiterTop: data.limiterTop }, timestamp: Date.now() + 1 },
  ];
  fs.appendFileSync(logPath, entries.map(e => JSON.stringify(e)).join('\n') + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
