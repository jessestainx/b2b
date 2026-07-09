import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text().slice(0, 150)); });

const start = Date.now();
await page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'load', timeout: 20000 });
console.log('load fired at', Date.now() - start, 'ms');

await page.waitForTimeout(2000);
const usable = await page.evaluate(() => {
  const body = document.body;
  return {
    readyState: document.readyState,
    hasEmptyCartMsg: !!document.querySelector('.cart-empty'),
    hasCartTable: !!document.querySelector('.cart.table-wrapper'),
    bodyClasses: body ? body.className.slice(0, 200) : null,
    jqueryReady: typeof window.jQuery !== 'undefined',
    requireReady: typeof window.require === 'function',
  };
});
console.log('usable check at t+2s:', JSON.stringify(usable));
console.log('errors so far:', JSON.stringify(errors));
console.log('connected:', browser.isConnected());
await browser.close().catch(() => {});
console.log('DONE - clean exit');
