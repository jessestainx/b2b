import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch();
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();
const consoleMsgs = [];
page.on('console', (msg) => consoleMsgs.push(msg.text().slice(0, 150)));
page.on('pageerror', (err) => consoleMsgs.push('PAGEERROR: ' + err.message.slice(0, 200)));

const client = await context.newCDPSession(page);
await client.send('Profiler.enable');
await client.send('Profiler.start');

const navPromise = page.goto('https://awamotos.com/checkout/cart/', { waitUntil: 'commit', timeout: 15000 });
await navPromise.catch((e) => console.log('nav note:', e.message.split('\n')[0]));
console.log('navigation committed, polling responsiveness...');

for (let i = 0; i < 4; i++) {
  const t0 = Date.now();
  try {
    const rs = await Promise.race([
      page.evaluate(() => document.readyState),
      new Promise((_, rej) => setTimeout(() => rej(new Error('evaluate timeout')), 2000)),
    ]);
    console.log(`t+${i * 2}s readyState=${rs} (evalTook=${Date.now() - t0}ms)`);
  } catch (e) {
    console.log(`t+${i * 2}s EVALUATE UNRESPONSIVE (${e.message})`);
  }
}

try {
  const profile = await client.send('Profiler.stop');
  const fs = await import('fs');
  fs.writeFileSync('/tmp/cart-hang-profile.cpuprofile', JSON.stringify(profile.profile));
  console.log('profile saved, nodes:', profile.profile.nodes.length);
} catch (e) {
  console.log('profiler stop failed:', e.message);
}

console.log('--- console/error messages captured ---');
console.log(JSON.stringify(consoleMsgs.slice(0, 30), null, 2));

await browser.close().catch(() => {});
