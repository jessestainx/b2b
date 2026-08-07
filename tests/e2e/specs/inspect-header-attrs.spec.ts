import { test } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';

test('atributos do header na PLP', async ({ page }) => {
  await page.goto(targetUrl('/bagageiros.html', 'inspect-header-attrs'), { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  const info = await page.evaluate(() => {
    const header = document.querySelector('.awa-site-header');
    const navBar = document.querySelector('.header-control.awa-nav-bar');
    const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    return {
      bodyClass: document.body.className,
      headerClass: header?.className,
      headerMode: header?.getAttribute('data-awa-header-mode'),
      navBarClass: navBar?.className,
      triggerTag: trigger?.tagName,
      triggerClass: trigger?.className,
      triggerParentChain: (() => {
        let el = trigger?.parentElement;
        const chain = [];
        for (let i = 0; i < 6 && el; i++) {
          chain.push(`${el.tagName}.${el.className}`);
          el = el.parentElement;
        }
        return chain;
      })(),
    };
  });
  console.log('[HDR]', JSON.stringify(info, null, 2));
});
