import { test } from '@playwright/test';
import { navigateTo } from '../helpers/deep-audit.helpers';

test('probe catalogo com navigateTo + listener, sem interacao', async ({ page }) => {
  test.setTimeout(40000);
  page.on('crash', () => console.log('PAGE CRASHED'));
  page.on('close', () => console.log('PAGE CLOSED'));
  const requests: string[] = [];
  page.on('response', (res) => {
    if (/\.css(\?|$)/i.test(res.url())) requests.push(res.url());
  });
  const ok = await navigateTo(page, 'https://awamotos.com/catalogo');
  console.log('NAV OK?', ok, 'closed?', page.isClosed());
  await page.waitForTimeout(2000).catch(e => console.log('WAIT1 ERR', e.message));
  console.log('after 2s closed?', page.isClosed());
  await page.waitForTimeout(2000).catch(e => console.log('WAIT2 ERR', e.message));
  console.log('after 4s closed?', page.isClosed(), 'css count so far', requests.length);
});
