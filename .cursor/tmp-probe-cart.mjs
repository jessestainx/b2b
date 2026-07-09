import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch();
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();
const requests = [];
page.on('request', (req) => requests.push({ t: Date.now(), url: req.url(), method: req.method() }));
page.on('requestfinished', (req) => {
  const r = requests.find((x) => x.url === req.url() && !x.done);
  if (r) r.done = true;
});

const start = Date.now();
try {
  await page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'load', timeout: 20000 });
} catch (e) {
  console.log('goto(load) failed:', e.message);
}
console.log('load event at', Date.now() - start, 'ms');

await page.waitForTimeout(8000);
const pending = requests.filter((r) => !r.done);
console.log('total requests:', requests.length, 'pending after 8s post-load:', pending.length);
console.log('pending URLs:', JSON.stringify(pending.map((r) => r.url), null, 2));
await browser.close();
