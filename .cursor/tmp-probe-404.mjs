import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const browser = await chromium.launch();
const context = await browser.newContext({ ...devices['iPhone 13'] });
const page = await context.newPage();
const failed = [];
page.on('requestfailed', (req) => failed.push({ url: req.url(), reason: req.failure()?.errorText }));
page.on('response', (res) => { if (res.status() === 404) failed.push({ url: res.url(), status: 404 }); });

await page.goto('https://awamotos.com/', { waitUntil: 'networkidle', timeout: 45000 });
await page.waitForTimeout(1500);
console.log(JSON.stringify(failed, null, 2));
await browser.close();
