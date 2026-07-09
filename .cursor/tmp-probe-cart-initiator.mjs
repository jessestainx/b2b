import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch();
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();
const client = await context.newCDPSession(page);
await client.send('Network.enable');

const initiators = {};
client.on('Network.requestWillBeSent', (params) => {
  const url = params.request.url;
  if (url.includes('jquery-ui-modules') || url.includes('js/lib/knockout/bindings') || url.includes('js/lib/logger')) {
    const stack = params.initiator?.stack?.callFrames?.map((f) => `${f.url}:${f.lineNumber}`).slice(0, 4) || [];
    initiators[url] = { type: params.initiator?.type, url: params.initiator?.url, stack };
  }
});

await page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'load', timeout: 25000 }).catch((e) => console.log('goto note:', e.message.split('\n')[0]));
await page.waitForTimeout(3000);

const keys = Object.keys(initiators);
console.log('total flagged requests:', keys.length);
console.log(JSON.stringify(initiators[keys[0]], null, 2));
console.log('--- unique initiator URLs ---');
const uniq = new Set(Object.values(initiators).map((i) => i.url));
console.log(JSON.stringify([...uniq], null, 2));
await browser.close();
