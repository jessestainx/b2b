import { test } from '@playwright/test';
import { targetUrl } from '/home/jessessh/htdocs/srv1113343.hstgr.cloud/tests/e2e/helpers/target-url';
test('nav bar attrs', async ({ page }) => {
  await page.goto(targetUrl('/bagageiros.html', 'check-nav'), { waitUntil: 'domcontentloaded' });
  const r = await page.evaluate(() => ({
    navDataAttr: document.querySelector('.awa-nav-bar')?.getAttribute('data-awa-header-nav'),
    navInner: document.querySelector('.awa-nav-bar__inner')?.getAttribute('data-awa-header-nav'),
    categoriesData: document.querySelector('.awa-header-categories')?.className,
    quickLinks: !!document.querySelector('.awa-nav-quick-links'),
    navLinksVisible: Array.from(document.querySelectorAll('.awa-nav-quick-links a, .awa-header-primary-nav .top-menu a')).map(a => ({ text: (a as HTMLElement).innerText.trim(), rect: (a as HTMLElement).getBoundingClientRect() })).filter(x => x.text),
  }));
  console.log('[NAV]', JSON.stringify(r, null, 2));
});
