import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd = (o) => fs.appendFileSync(LOG, JSON.stringify({ sessionId: '7d58c7', timestamp: Date.now(), ...o }) + '\n');

const EN_RE = /\b(Shop by|Filter|Sort By|Add to Cart|Add to Wish List|Compare Products|Sign In|Create an Account|My Cart|Search|Next|Previous|Items|of|Show|per page|View as|Grid|List|Shopping Options|Price|Color|Brand|Category|In stock|Out of stock|Learn More|Read More|Continue|Checkout|Proceed|Update|Remove|Qty|Quantity|Subtotal|Grand Total|Shipping|Payment|Order|Wishlist|Recently Ordered|Subscribe|Newsletter|Privacy Policy|Terms|Contact Us|About Us|Home|New|Sale|Hot|All Categories|Departments|Our Brands|Loading|Please wait|No results|Sorry|Error|Success|Warning|Close|Open|Menu|Account|Login|Register|Password|Email|Phone|Address|Company|Tax|SKU|SKU:|COD:|Buy Now|See all|View all|Related Products|Upsell|Cross-sell|Customer|Guest|Wholesale|Retail)\b/i;

const pages = [
  { id: 'home', url: 'https://awamotos.com/' },
  { id: 'search', url: 'https://awamotos.com/catalogsearch/result/?q=retro' },
  { id: 'category', url: 'https://awamotos.com/retrovisores.html' },
  { id: 'pdp', url: 'https://awamotos.com/catalogsearch/result/?q=549' }
];

const viewports = [
  { name: 'desktop', width: 1366, height: 900 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 }
];

const browser = await chromium.launch({ headless: true, args: ['--disable-dev-shm-usage', '--no-sandbox'] });

async function auditPage(page, pageId, vp) {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));

  await page.setViewportSize({ width: vp.width, height: vp.height });
  const url = pages.find((p) => p.id === pageId).url;
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2200);

  // if search for PDP-like, click first product if present
  if (pageId === 'pdp') {
    const first = page.locator('.product-item-link, .product-item-name a, a.product-item-photo').first();
    if (await first.count()) {
      await Promise.race([
        first.click({ timeout: 5000 }).catch(() => null),
        page.waitForTimeout(100)
      ]);
      await page.waitForTimeout(1800);
    }
  }

  const data = await page.evaluate(({ EN_SOURCE, pageId, vpName }) => {
    const EN = new RegExp(EN_SOURCE, 'i');
    const issues = [];
    const english = [];
    const overflow = [];

    // visible text nodes with English
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
    let node;
    while ((node = walker.nextNode())) {
      const t = (node.textContent || '').replace(/\s+/g, ' ').trim();
      if (!t || t.length < 3 || t.length > 120) continue;
      const el = node.parentElement;
      if (!el) continue;
      const tag = el.tagName;
      if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'SVG', 'PATH'].includes(tag)) continue;
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') continue;
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) continue;
      if (EN.test(t) && /[A-Za-z]{3,}/.test(t)) {
        // skip obvious Portuguese mixed / brand names
        if (/^(AWA|Honda|Yamaha|Suzuki|Kawasaki|COD:|SKU)/i.test(t)) continue;
        english.push({ text: t, tag, top: Math.round(r.top), left: Math.round(r.left), cls: (el.className || '').toString().slice(0, 80) });
      }
    }

    // overflow horizontal
    const docW = document.documentElement.clientWidth;
    document.querySelectorAll('header, main, footer, .page-wrapper, .columns, .products, .toolbar, .block.filter, .mst-searchautocomplete__autocomplete').forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.right > docW + 2 || r.left < -2) {
        overflow.push({
          sel: el.className ? String(el.className).slice(0, 90) : el.tagName,
          left: Math.round(r.left),
          right: Math.round(r.right),
          width: Math.round(r.width),
          top: Math.round(r.top)
        });
      }
    });

    // key geometry
    const header = document.querySelector('.awa-site-header, .page-header');
    const nav = document.querySelector('.awa-nav-bar__inner');
    const main = document.querySelector('#maincontent');
    const filter = document.querySelector('.block.filter');
    const toolbar = document.querySelector('.toolbar.toolbar-products');
    const grid = document.querySelector('.products-grid, .product-items, ul.products');
    const search = document.querySelector('#search');
    const title = document.querySelector('.page-title, h1.page-title');

    const geom = {};
    [[ 'header', header ], [ 'nav', nav ], [ 'main', main ], [ 'filter', filter ], [ 'toolbar', toolbar ], [ 'grid', grid ], [ 'search', search ], [ 'title', title ]].forEach(([k, el]) => {
      if (!el) { geom[k] = null; return; }
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      geom[k] = {
        top: Math.round(r.top), left: Math.round(r.left), w: Math.round(r.width), h: Math.round(r.height),
        display: cs.display, position: cs.position, gap: cs.gap || null, pad: cs.padding
      };
    });

    // empty / collapsed areas
    if (main && main.getBoundingClientRect().height < 80) {
      issues.push('maincontent height too small');
    }
    if (nav && nav.getBoundingClientRect().height > 80 && vpName === 'desktop') {
      issues.push('nav bar unexpectedly tall: ' + Math.round(nav.getBoundingClientRect().height));
    }
    if (filter && filter.getBoundingClientRect().top < -50) {
      issues.push('filter sticky top negative: ' + Math.round(filter.getBoundingClientRect().top));
    }

    // unique english sample
    const seen = new Set();
    const englishUniq = [];
    for (const e of english) {
      const key = e.text.toLowerCase();
      if (seen.has(key)) continue;
      seen.add(key);
      englishUniq.push(e);
      if (englishUniq.length >= 40) break;
    }

    return {
      pageId,
      vp: vpName,
      url: location.href,
      title: document.title,
      english: englishUniq,
      overflow: overflow.slice(0, 20),
      geom,
      issues,
      bodyClasses: (document.body.className || '').split(/\s+/).slice(0, 20)
    };
  }, { EN_SOURCE: EN_RE.source, pageId, vpName: vp.name });

  data.status = resp ? resp.status() : null;
  data.pageErrors = errors.slice(0, 10);
  return data;
}

const results = [];
for (const vp of viewports) {
  const page = await browser.newPage();
  for (const p of pages) {
    try {
      const r = await auditPage(page, p.id, vp);
      results.push(r);
      nd({ runId: 'visual-audit', hypothesisId: 'V1', location: `${p.id}:${vp.name}`, message: 'visual audit page', data: {
        status: r.status,
        englishCount: r.english.length,
        overflowCount: r.overflow.length,
        issues: r.issues,
        englishSample: r.english.slice(0, 15),
        overflowSample: r.overflow.slice(0, 8),
        geom: r.geom,
        pageErrors: r.pageErrors
      }});
      console.log(JSON.stringify({ page: p.id, vp: vp.name, status: r.status, en: r.english.length, overflow: r.overflow.length, issues: r.issues, enSample: r.english.slice(0, 8).map(e => e.text) }, null, 2));
    } catch (e) {
      nd({ runId: 'visual-audit', hypothesisId: 'V1', location: `${p.id}:${vp.name}`, message: 'audit failed', data: { error: String(e) } });
      console.error(p.id, vp.name, e);
    }
  }
  await page.close();
}

await browser.close();
fs.writeFileSync('/tmp/awa-visual-audit.json', JSON.stringify(results, null, 2));
console.log('done pages', results.length);
