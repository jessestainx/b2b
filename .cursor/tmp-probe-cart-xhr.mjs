import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();

const xhrLog = [];
const start = Date.now();
page.on('request', (req) => {
  const type = req.resourceType();
  if (type === 'xhr' || type === 'fetch') {
    xhrLog.push({ t: Date.now() - start, url: req.url().replace('https://awamotos.com', ''), method: req.method() });
  }
});
page.on('console', (msg) => { if (msg.type() === 'error') console.log('CONSOLE ERROR:', msg.text().slice(0, 200)); });
page.on('pageerror', (err) => console.log('PAGEERROR:', err.message.slice(0, 300)));

page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'load', timeout: 60000 }).catch((e) => console.log('goto note:', e.message.split('\n')[0]));

for (let i = 0; i < 20; i++) {
  await new Promise((r) => setTimeout(r, 3000));
  console.log(`t=${((Date.now() - start) / 1000).toFixed(1)}s connected=${browser.isConnected()} xhrCount=${xhrLog.length}`);
  if (!browser.isConnected()) break;
}

console.log('--- full xhr/fetch log ---');
console.log(JSON.stringify(xhrLog, null, 2));
await browser.close().catch(() => {});
