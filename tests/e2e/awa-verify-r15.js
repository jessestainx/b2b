const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1430, height: 900 } });
  await page.goto('https://awamotos.com/bauletos.html?verify-r15=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(2500);

  const data = await page.evaluate(() => {
    const btn = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const txt = btn ? btn.querySelector('.awa-vmenu-trigger-text') : null;
    const sorter = document.querySelector('#sorter');
    const limiter = document.querySelector('#limiter');
    const scripts = Array.from(document.querySelectorAll('script[src]')).filter(s => s.src.includes('menu-controller') || s.src.includes('menu-bootstrap')).map(s => s.src);
    const alignGrid = (Array.from(document.querySelectorAll('link[rel="stylesheet"]')).find(l => l.href.includes('align-grid')) || {}).href;
    return {
      triggerInline: btn ? btn.getAttribute('style') : null,
      textInline: txt ? txt.getAttribute('style') : null,
      textFontSize: txt ? getComputedStyle(txt).fontSize : null,
      textFontWeight: txt ? getComputedStyle(txt).fontWeight : null,
      misalign: sorter && limiter ? Math.round(sorter.getBoundingClientRect().top - limiter.getBoundingClientRect().top) : null,
      sorterTop: sorter ? Math.round(sorter.getBoundingClientRect().top) : null,
      limiterTop: limiter ? Math.round(limiter.getBoundingClientRect().top) : null,
      alignGridHref: alignGrid || null,
      scripts,
    };
  });

  const entries = [
    { sessionId: 'ec818a', runId: 'playwright-post-fix-r15', hypothesisId: 'M', location: 'playwright:departamentos-post-fix', message: 'Trigger inline after r15 strip', data: { triggerInline: data.triggerInline, textInline: data.textInline, textFontSize: data.textFontSize, textFontWeight: data.textFontWeight, scripts: data.scripts }, timestamp: Date.now() },
    { sessionId: 'ec818a', runId: 'playwright-post-fix-r15', hypothesisId: 'L2', location: 'playwright:toolbar-post-fix', message: 'Toolbar alignment after r15', data: { misalign: data.misalign, sorterTop: data.sorterTop, limiterTop: data.limiterTop, alignGridHref: data.alignGridHref }, timestamp: Date.now() + 1 },
  ];
  fs.appendFileSync(logPath, entries.map(e => JSON.stringify(e)).join('\n') + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
