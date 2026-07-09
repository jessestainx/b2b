import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto('https://awamotos.com/', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(4000);

const data = await page.evaluate(() => {
  const nav = document.querySelector('.header-control.awa-nav-bar');
  if (!nav) return { error: 'no nav' };
  const cs = getComputedStyle(nav);
  const sheets = [];
  for (const sheet of document.styleSheets) {
    let href = sheet.href || 'inline';
    try {
      for (const rule of sheet.cssRules || []) {
        if (!rule.cssText || !rule.cssText.includes('awa-nav-bar')) continue;
        if (/height:\s*56px/i.test(rule.cssText)) {
          sheets.push({ href: href.split('/').pop(), snippet: rule.cssText.slice(0, 120) });
        }
      }
    } catch (e) {
      sheets.push({ href: href.split('/').pop(), snippet: 'blocked' });
    }
  }
  return {
    height: cs.height,
    minHeight: cs.minHeight,
    maxHeight: cs.maxHeight,
    rules56: sheets.slice(0, 8),
    cascadeLock: !!document.getElementById('awa-header-impeccable-cascade-lock-v12'),
  };
});

console.log(JSON.stringify(data, null, 2));
await browser.close();
