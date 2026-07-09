import { chromium, devices } from '/opt/playwright-job/node_modules/playwright/index.mjs';

const pages = [
  { name: 'Home', url: 'https://awamotos.com/' },
  { name: 'PLP-bagageiros', url: 'https://awamotos.com/bagageiros.html?price=80-90' },
  { name: 'PDP-manopla', url: 'https://awamotos.com/manopla-titan-150-titan-cg-160-mod-16-25-preta-0087.html' },
  { name: 'Search', url: 'https://awamotos.com/catalogsearch/result/?q=manopla' },
  { name: 'Cart', url: 'https://awamotos.com/checkout/cart/' },
  { name: 'Login-B2B', url: 'https://awamotos.com/b2b/account/login/' },
];

const viewports = [
  { name: 'mobile', device: devices['iPhone 13'] },
  { name: 'tablet', device: devices['iPad Mini'] },
  { name: 'desktop', device: { viewport: { width: 1440, height: 900 } } },
];

let browser = await chromium.launch();
const report = [];

for (const p of pages) {
  for (const vp of viewports) {
    if (!browser.isConnected()) {
      browser = await chromium.launch();
    }
    let context;
    try {
      context = await browser.newContext({ ...vp.device });
    } catch (e) {
      report.push({ page: p.name, viewport: vp.name, error: 'newContext failed: ' + e.message.slice(0, 200) });
      console.log(JSON.stringify(report[report.length - 1]));
      browser = await chromium.launch();
      continue;
    }
    const page = await context.newPage();
    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 200)); });
    page.on('pageerror', (err) => consoleErrors.push('pageerror: ' + err.message.slice(0, 200)));

    let status = null;
    try {
      const resp = await page.goto(p.url, { waitUntil: 'networkidle', timeout: 45000 });
      status = resp ? resp.status() : null;
      await page.waitForTimeout(1200);
    } catch (e) {
      const entry = { page: p.name, viewport: vp.name, error: 'goto failed: ' + e.message.slice(0, 200) };
      report.push(entry);
      console.log(JSON.stringify(entry));
      await context.close().catch(() => {});
      continue;
    }

    const data = await page.evaluate(() => {
      const header = document.querySelector('header, .page-header, .header.content');
      const footer = document.querySelector('footer, .page-footer');
      const nav = document.querySelector('nav, .nav-sections, .block-menu');
      const brokenImages = Array.from(document.images).filter((img) => img.complete && img.naturalWidth === 0).length;
      const overflowX = document.documentElement.scrollWidth > window.innerWidth ? document.documentElement.scrollWidth - window.innerWidth : 0;
      const interactive = Array.from(document.querySelectorAll('a, button')).filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
      });
      const tiny = interactive.filter((el) => {
        const r = el.getBoundingClientRect();
        const text = (el.textContent || '').trim();
        const isIconOnly = !text && (el.querySelector('svg, img, i.icon, .icon') || el.className.toString().match(/icon|toggle|action|btn/i));
        return r.height < 40 && r.width < 40 && isIconOnly;
      }).length;
      return {
        hasHeader: !!header,
        hasFooter: !!footer,
        hasNav: !!nav,
        brokenImages,
        overflowX,
        tinyIconTargets: tiny,
        interactiveCount: interactive.length,
        title: document.title,
      };
    });

    const entry = { page: p.name, viewport: vp.name, status, consoleErrors, ...data };
    report.push(entry);
    console.log(JSON.stringify(entry));
    await context.close().catch(() => {});
  }
}
await browser.close().catch(() => {});
console.log('---FINAL---');
console.log(JSON.stringify(report, null, 2));
