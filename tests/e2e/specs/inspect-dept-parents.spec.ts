import { test } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';

test('padding/margin chain do trigger', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(targetUrl('/bagageiros.html', 'inspect-dept-parents'), { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

  const chain = await page.evaluate(() => {
    const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const nodes: Element[] = [];
    let el: Element | null = trigger;
    for (let i = 0; i < 8 && el; i++) { nodes.push(el); el = el.parentElement; }
    return nodes.map((node) => {
      const r = node.getBoundingClientRect();
      const c = getComputedStyle(node);
      return {
        tag: node.tagName,
        cls: (node.className || '').toString().slice(0, 80),
        rect: { t: Math.round(r.top), b: Math.round(r.bottom), h: Math.round(r.height) },
        pad: `${c.paddingTop}/${c.paddingBottom}`,
        margin: `${c.marginTop}/${c.marginBottom}`,
        display: c.display,
        alignItems: c.alignItems,
      };
    });
  });
  console.log('[CHAIN]', JSON.stringify(chain, null, 2));
});
