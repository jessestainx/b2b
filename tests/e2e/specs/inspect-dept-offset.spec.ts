import { test } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';
test('offset chain', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(targetUrl('/bagageiros.html', 'offset'), { waitUntil: 'domcontentloaded' });
  await page.evaluate(async () => { if('caches' in window){const k=await caches.keys(); await Promise.all(k.map(x=>caches.delete(x)));} });
  await page.reload();
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  const chain = await page.evaluate(() => {
    const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const nodes: Element[] = [];
    let el: Element | null = trigger;
    for (let i = 0; i < 12 && el; i++) { nodes.push(el); el = el.parentElement; }
    return nodes.map((node) => {
      const r = node.getBoundingClientRect();
      const c = getComputedStyle(node);
      return {
        tag: node.tagName,
        cls: (node.className||'').toString().slice(0,70),
        top: Math.round(r.top),
        h: Math.round(r.height),
        padT: c.paddingTop, padB: c.paddingBottom, marT: c.marginTop,
        display: c.display, alignItems: c.alignItems, boxSizing: c.boxSizing,
      };
    });
  });
  console.log('[OFFSET]', JSON.stringify(chain, null, 2));
});
