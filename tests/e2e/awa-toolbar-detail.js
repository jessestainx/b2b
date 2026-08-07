const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1430, height: 900 } });
  await page.goto('https://awamotos.com/bauletos.html?toolbar-debug=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1500);

  const data = await page.evaluate(() => {
    function cs(el) {
      if (!el) return null;
      const s = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        top: Math.round(r.top),
        h: Math.round(r.height),
        marginTop: s.marginTop,
        paddingTop: s.paddingTop,
        alignSelf: s.alignSelf,
        display: s.display,
        alignItems: s.alignItems,
        position: s.position,
        transform: s.transform,
      };
    }
    const center = document.querySelector('.shop-tab-select .toolbar.toolbar-products .center');
    const sorter = document.querySelector('.toolbar-sorter.sorter');
    const label = document.querySelector('.sorter-label');
    const limiter = document.querySelector('.field.limiter');
    const control = document.querySelector('.field.limiter > .control');
    return {
      center: cs(center),
      sorter: cs(sorter),
      label: cs(label),
      limiter: cs(limiter),
      control: cs(control),
      sorterSelect: cs(document.querySelector('#sorter')),
      limiterSelect: cs(document.querySelector('#limiter')),
      misalign: Math.round(document.querySelector('#sorter').getBoundingClientRect().top - document.querySelector('#limiter').getBoundingClientRect().top),
    };
  });

  fs.appendFileSync(logPath, JSON.stringify({ sessionId: 'ec818a', runId: 'toolbar-detail', hypothesisId: 'L2', location: 'playwright:toolbar-detail', message: 'Toolbar alignment detail', data, timestamp: Date.now() }) + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
