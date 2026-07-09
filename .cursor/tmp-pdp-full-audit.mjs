#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-full-audit', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PDP, { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForSelector('.col-main, .column.main', { timeout: 30000 }).catch(() => {});
await page.waitForTimeout(2500);

const data = await page.evaluate(() => {
  const vw = document.documentElement.clientWidth;
  const measure = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return { w: Math.round(r.width), x: Math.round(r.left), right: Math.round(r.right), maxW: cs.maxWidth, padL: cs.paddingLeft, display: cs.display };
  };
  const navBc = document.querySelector('.nav-breadcrumbs');
  const navBcContainer = document.querySelector('.nav-breadcrumbs .container');
  const pm = document.querySelector('#maincontent.page-main.container, .page-main.container');
  const columns = document.querySelector('.page-main > .columns');
  const layout2col = document.querySelector('.columns.layout.layout-2-col');
  const sidebar = document.querySelector('.sidebar-main-1, .sidebar-main, .col-left.sidebar');
  const colMain = document.querySelector('.col-main');
  const productView = document.querySelector('.product-view');
  const viewProduct = document.querySelector('.view-product');

  // Check if col-main bootstrap fraction limits width
  const colMainClasses = colMain?.className || '';

  return {
    vw,
    bodyLayout: document.body.className.match(/page-layout-\S+/)?.[0] || null,
    navBc: measure(navBc),
    navBcContainer: measure(navBcContainer),
    pageMain: measure(pm),
    columns: measure(columns),
    layout2col: measure(layout2col),
    sidebar: sidebar ? { ...measure(sidebar), cls: sidebar.className.slice(0, 60) } : null,
    colMain: { ...measure(colMain), classes: colMainClasses },
    productView: measure(productView),
    viewProduct: measure(viewProduct),
    hypotheses: {
      H1_sidebar_takes_space: sidebar ? sidebar.w > 0 && getComputedStyle(sidebar).display !== 'none' : false,
      H2_columns_narrower: columns && pm ? columns.w < pm.getBoundingClientRect().width - 48 : false,
      H3_bc_misaligned: navBc && pm ? Math.abs(navBc.getBoundingClientRect().left - pm.getBoundingClientRect().left) > 8 : false,
      H4_colmain_bootstrap: colMain ? colMain.getBoundingClientRect().width < columns.getBoundingClientRect().width * 0.95 : false,
      H5_viewport_gutter: pm ? (vw - pm.getBoundingClientRect().width) / 2 > 100 : false,
    },
  };
});

const issues = Object.entries(data.hypotheses).filter(([, v]) => v).map(([k]) => k);
log({ hypothesisId: 'PDP-AUDIT', location: 'tmp-pdp-full-audit.mjs', message: issues.length ? 'PDP_ISSUES_FOUND' : 'PDP_NO_ISSUES', data: { ...data, issues } });
console.log(JSON.stringify({ issues, ...data }, null, 2));
await browser.close();
process.exit(issues.length ? 1 : 0);
