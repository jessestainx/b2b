import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();

page.on('crash', () => console.log('*** PAGE CRASH EVENT FIRED ***'));
page.on('close', () => console.log('*** PAGE CLOSE EVENT FIRED ***'));
context.on('close', () => console.log('*** CONTEXT CLOSE EVENT FIRED ***'));
browser.on('disconnected', () => console.log('*** BROWSER DISCONNECTED EVENT FIRED ***'));

const consoleMsgs = [];
page.on('console', (msg) => { consoleMsgs.push(msg.text().slice(0, 150)); console.log('CONSOLE:', msg.type(), msg.text().slice(0, 150)); });
page.on('pageerror', (err) => console.log('PAGEERROR:', err.message.slice(0, 300)));
page.on('requestfailed', (req) => console.log('REQFAIL:', req.url().slice(-80), req.failure()?.errorText));

console.log('t=0 navigating...');
page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'domcontentloaded', timeout: 20000 }).then(
  () => console.log('domcontentloaded resolved'),
  (e) => console.log('goto rejected:', e.message.split('\n')[0])
);

for (let i = 1; i <= 12; i++) {
  await new Promise((r) => setTimeout(r, 1500));
  console.log(`t=${i * 1.5}s isConnected=${browser.isConnected()}`);
}
await browser.close().catch(() => {});
