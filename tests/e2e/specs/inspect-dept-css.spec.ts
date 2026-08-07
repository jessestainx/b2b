import { test } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';

test('computed CSS do trigger Departamentos', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(targetUrl('/bagageiros.html', 'inspect-dept-css'), { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await new Promise((r) => setTimeout(r, 800));

  const css = await page.evaluate(() => {
    const el = document.querySelector('[data-role="awa-vertical-menu-trigger"], .our_categories.title-category-dropdown') as HTMLElement | null;
    const navBar = document.querySelector('.header-control.awa-nav-bar') as HTMLElement | null;
    const navInner = document.querySelector('.awa-nav-bar__inner') as HTMLElement | null;
    const get = (node: HTMLElement | null) => node ? {
      borderRadius: getComputedStyle(node).borderRadius,
      height: getComputedStyle(node).height,
      minHeight: getComputedStyle(node).minHeight,
      maxHeight: getComputedStyle(node).maxHeight,
      marginTop: getComputedStyle(node).marginTop,
      marginBottom: getComputedStyle(node).marginBottom,
      alignSelf: getComputedStyle(node).alignSelf,
      position: getComputedStyle(node).position,
      top: getComputedStyle(node).top,
      transform: getComputedStyle(node).transform,
      overflow: getComputedStyle(node).overflow,
    } : null;
    const root = getComputedStyle(document.documentElement);
    return {
      awaHeaderNavH: root.getPropertyValue('--awa-header-nav-h').trim(),
      awaVmenuTriggerW: root.getPropertyValue('--awa-vmenu-trigger-w').trim(),
      trigger: get(el),
      navBar: get(navBar),
      navInner: get(navInner),
      navBarOverflow: navBar ? getComputedStyle(navBar).overflow : null,
    };
  });
  console.log('[DEPT-CSS]', JSON.stringify(css, null, 2));
});
