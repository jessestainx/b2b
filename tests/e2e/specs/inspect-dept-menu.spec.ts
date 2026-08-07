import { test } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';

test('inspeciona alinhamento menu Departamentos', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(targetUrl('/bagageiros.html', 'inspect-dept'), { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await new Promise((r) => setTimeout(r, 800));

  const info = await page.evaluate(() => {
    const navBar = document.querySelector('.header-control.awa-nav-bar, .awa-nav-bar');
    const deptBtn = document.querySelector('[data-role="awa-vertical-menu-trigger"], .awa-header-categories, .menu_left_home1 .togge-menu > li.level0:first-child > a, .awa-header-categories .togge-menu');
    const deptTrigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const primaryNav = document.querySelector('#awa-primary-navigation, .awa-header-primary-nav');
    const navInner = document.querySelector('.awa-nav-bar__inner');
    const get = (el: Element | null) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const c = getComputedStyle(el);
      return {
        selector: el.className,
        rect: { top: Math.round(r.top), bottom: Math.round(r.bottom), height: Math.round(r.height), left: Math.round(r.left), width: Math.round(r.width) },
        css: { display: c.display, alignItems: c.alignItems, alignSelf: c.alignSelf, height: c.height, marginTop: c.marginTop, marginBottom: c.marginBottom, position: c.position, top: c.top },
      };
    };
    return {
      navBar: get(navBar),
      deptTrigger: get(deptTrigger),
      deptCategories: get(document.querySelector('.awa-header-categories.menu_left_home1')),
      primaryNav: get(primaryNav),
      navInner: get(navInner),
      navLinks: Array.from(document.querySelectorAll('.awa-header-primary-nav a, #awa-primary-navigation a')).slice(0, 3).map((el) => get(el)),
    };
  });
  console.log('[DEPT]', JSON.stringify(info, null, 2));
  await page.screenshot({ path: '/tmp/dept-menu-desktop.png', clip: { x: 0, y: 80, width: 1440, height: 120 } }).catch(() => {});
});
