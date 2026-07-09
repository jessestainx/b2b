import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();

const inflight = new Map();
const start = Date.now();
page.on('request', (req) => inflight.set(req, { url: req.url(), type: req.resourceType(), t: Date.now() - start }));
page.on('requestfinished', (req) => inflight.delete(req));
page.on('requestfailed', (req) => { console.log('REQFAILED', req.resourceType(), req.url().slice(-100), req.failure()?.errorText); inflight.delete(req); });

page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'load', timeout: 40000 }).then(
  () => console.log('LOAD EVENT FIRED at', Date.now() - start, 'ms'),
  (e) => console.log('load timeout:', e.message.split('\n')[0])
);

await new Promise((r) => setTimeout(r, 35000));
console.log('--- still in-flight after 35s ---');
for (const [, info] of inflight) {
  console.log(JSON.stringify(info));
}
console.log('connected:', browser.isConnected());
await browser.close().catch(() => {});
