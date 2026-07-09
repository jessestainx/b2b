#!/usr/bin/env node
/**
 * Compara eixo estrutural: home vs páginas internas (PLP, PDP, CMS, account).
 */
import { chromium } from 'playwright';

const PAGES = [
  { id: 'home', url: 'https://awamotos.com/', bodyMatch: /cms-index-index|cms-home/ },
  { id: 'plp', url: 'https://awamotos.com/eletronicos.html', bodyMatch: /catalog-category-view/ },
  { id: 'pdp', url: 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html', bodyMatch: /catalog-product-view/ },
  { id: 'cms', url: 'https://awamotos.com/about-us', bodyMatch: /cms-page-view/ },
  { id: 'login', url: 'https://awamotos.com/customer/account/login/', bodyMatch: /customer-account-login|b2b-account-login/ },
];

const SELECTORS = [
  { key: 'headerInner', sel: '.awa-main-header__inner, .header-wrapper-sticky .container, .awa-site-header .header-content' },
  { key: 'heroOrTitle', sel: '.content-top-home, .nav-breadcrumbs, .page-title-wrapper' },
  { key: 'main', sel: 'main.page-main#maincontent, #maincontent.page-main' },
  { key: 'columns', sel: 'main.page-main .columns, #maincontent .columns' },
  { key: 'columnMain', sel: 'main.page-main .column.main, #maincontent .column.main' },
  { key: 'footerInner', sel: '.page-footer .footer-container, .page_footer #footer .footer-container, footer.page-footer > .container' },
  { key: 'homeSection', sel: '.content-top-home .awa-carousel-section > .container, .content-top-home .top-home-content.awa-home-section > .container' },
];

function probeEl(el) {
  if (!el) return null;
  const r = el.getBoundingClientRect();
  const cs = getComputedStyle(el);
  return {
    w: Math.round(r.width),
    left: Math.round(r.left),
    right: Math.round(r.right),
    padL: cs.paddingLeft,
    padR: cs.paddingRight,
    marL: cs.marginLeft,
    marR: cs.marginRight,
    maxW: cs.maxWidth,
    box: cs.boxSizing,
  };
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const results = {};

for (const p of PAGES) {
  await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(2500);
  const snap = await page.evaluate(
    ({ selectors, bodyMatchSource }) => {
      const bodyMatch = new RegExp(bodyMatchSource);
      const bodyOk = bodyMatch.test(document.body.className);
      const out = { bodyClass: document.body.className.split(/\s+/).slice(0, 6).join(' '), bodyOk, zones: {} };
      for (const { key, sel } of selectors) {
        const el = document.querySelector(sel);
        if (!el) {
          out.zones[key] = { missing: true, sel };
          continue;
        }
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        out.zones[key] = {
          sel: sel.split(',')[0].trim(),
          w: Math.round(r.width),
          left: Math.round(r.left),
          right: Math.round(r.right),
          padL: cs.paddingLeft,
          padR: cs.paddingRight,
          marL: cs.marginLeft,
          marR: cs.marginRight,
          maxW: cs.maxWidth,
        };
      }
      const root = getComputedStyle(document.documentElement);
      out.tokens = {
        pageCatalog: root.getPropertyValue('--awa-page-catalog').trim(),
        shellGutter: root.getPropertyValue('--awa-shell-gutter').trim(),
        homeShellMax: root.getPropertyValue('--awa-home-shell-max').trim(),
        headerShellMax: root.getPropertyValue('--awa-header-shell-max').trim(),
      };
      return out;
    },
    { selectors: SELECTORS, bodyMatchSource: p.bodyMatch.source }
  );
  results[p.id] = snap;
}

await browser.close();

// Análise: eixo útil = largura do main; alinhamento = left/right simétricos
const homeMain = results.home?.zones?.main;
const refLeft = homeMain?.left ?? null;
const refWidth = homeMain?.w ?? null;

const divergences = [];
for (const [id, snap] of Object.entries(results)) {
  if (id === 'home') continue;
  const main = snap.zones?.main;
  if (!main || main.missing) continue;
  const dW = main.w - refWidth;
  const dL = main.left - refLeft;
  if (Math.abs(dW) > 8 || Math.abs(dL) > 8) {
    divergences.push({ page: id, ref: 'home', dW, dL, home: homeMain, pageMain: main });
  }
}

console.log(JSON.stringify({ results, ref: { refWidth, refLeft }, divergences }, null, 2));
