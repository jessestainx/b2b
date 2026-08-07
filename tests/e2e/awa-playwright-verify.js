const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1430, height: 900 } });
  await page.goto('https://awamotos.com/bauletos.html?pw-verify=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1500);

  const data = await page.evaluate(() => {
    const btn = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const txt = btn ? btn.querySelector('.awa-vmenu-trigger-text') : null;
    const sorter = document.querySelector('#sorter');
    const limiter = document.querySelector('#limiter');
    function box(el) {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        top: Math.round(r.top),
        h: Math.round(r.height),
        inline: el.getAttribute('style'),
        fontSize: s.fontSize,
        fontWeight: s.fontWeight,
        lineHeight: s.lineHeight,
        marginTop: s.marginTop,
      };
    }
    return {
      trigger: box(btn),
      triggerText: box(txt),
      sorter: box(sorter),
      limiter: box(limiter),
      toolbarMisalign: sorter && limiter ? Math.round(sorter.getBoundingClientRect().top - limiter.getBoundingClientRect().top) : null,
      alignGridHref: (Array.from(document.querySelectorAll('link[rel="stylesheet"]')).find(l => l.href.includes('align-grid')) || {}).href || null,
    };
  });

  const entries = [
    {
      sessionId: 'ec818a',
      runId: 'playwright-post-repro',
      hypothesisId: 'M',
      location: 'playwright:departamentos',
      message: 'Runtime computed styles after JS init',
      data: {
        trigger: data.trigger,
        triggerText: data.triggerText,
        alignGridHref: data.alignGridHref,
      },
      timestamp: Date.now(),
    },
    {
      sessionId: 'ec818a',
      runId: 'playwright-post-repro',
      hypothesisId: 'L',
      location: 'playwright:toolbar',
      message: 'Runtime toolbar sorter/limiter alignment',
      data: {
        sorter: data.sorter,
        limiter: data.limiter,
        toolbarMisalign: data.toolbarMisalign,
      },
      timestamp: Date.now() + 1,
    },
  ];

  fs.appendFileSync(logPath, entries.map(e => JSON.stringify(e)).join('\n') + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
