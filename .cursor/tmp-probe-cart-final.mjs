import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();
const consoleErrors = [];
page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 200)); });
page.on('pageerror', (err) => consoleErrors.push('pageerror: ' + err.message.slice(0, 200)));

const start = Date.now();
try {
  await page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'networkidle', timeout: 45000 });
  console.log('networkidle reached at', Date.now() - start, 'ms');
} catch (e) {
  console.log('networkidle timeout after', Date.now() - start, 'ms:', e.message.split('\n')[0]);
}
console.log('browser still connected:', browser.isConnected());
console.log('consoleErrors:', JSON.stringify(consoleErrors, null, 2));
const visible = await page.evaluate(() => ({
  hasCartContainer: !!document.querySelector('.cart-container, .cart.table-wrapper, .cart-empty'),
  title: document.title,
})).catch((e) => ({ evalError: e.message }));
console.log('page state:', JSON.stringify(visible));
await browser.close().catch(() => {});
