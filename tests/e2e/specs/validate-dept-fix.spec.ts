import { test, expect } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';

test('HEADER-P0-002 — Departamentos alinhado à nav bar', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(targetUrl('/bagageiros.html', 'validate-dept'), { waitUntil: 'domcontentloaded' });
  await page.evaluate(async () => {
    if ('serviceWorker' in navigator) {
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(regs.map((r) => r.unregister()));
    }
    if ('caches' in window) {
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
    }
  });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await new Promise((r) => setTimeout(r, 1000));

  const m = await page.evaluate(() => {
    const navBar = document.querySelector('.header-control.awa-nav-bar');
    const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]') as HTMLElement | null;
    const navInner = document.querySelector('.awa-nav-bar__inner');
    if (!navBar || !trigger) return null;
    const nb = navBar.getBoundingClientRect();
    const tr = trigger.getBoundingClientRect();
    const ni = navInner?.getBoundingClientRect();
    const cs = getComputedStyle(trigger);
    return {
      navBar: { t: Math.round(nb.top), b: Math.round(nb.bottom), h: Math.round(nb.height) },
      trigger: { t: Math.round(tr.top), b: Math.round(tr.bottom), h: Math.round(tr.height) },
      navInner: ni ? { t: Math.round(ni.top), b: Math.round(ni.bottom) } : null,
      borderRadius: cs.borderRadius,
      paddingBlock: `${cs.paddingTop}/${cs.paddingBottom}`,
      height: cs.height,
    };
  });

  console.log('[VALIDATE-DEPT]', JSON.stringify(m, null, 2));
  expect(m).not.toBeNull();
  if (m) {
    expect(m.trigger.t, 'trigger top deve alinhar com navInner/navBar').toBeLessThanOrEqual((m.navInner?.t ?? m.navBar.t) + 2);
    expect(m.trigger.b, 'trigger nao deve transbordar nav bar').toBeLessThanOrEqual(m.navBar.b + 1);
    expect(m.borderRadius, 'border-radius deve ser 0 (tab flush)').toMatch(/^0/);
    expect(m.paddingBlock, 'sem padding vertical no trigger').toBe('0px/0px');
  }

  await page.screenshot({ path: '/tmp/dept-menu-after-fix.png', clip: { x: 0, y: 80, width: 1440, height: 120 } }).catch(() => {});
});
