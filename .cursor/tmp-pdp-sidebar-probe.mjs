#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-sidebar-probe', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

for (const vp of [{ w: 992, h: 900 }, { w: 768, h: 1024 }, { w: 1024, h: 768 }]) {
  await page.setViewportSize({ width: vp.w, height: vp.h });
  await page.goto(PDP, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(2000);
  const data = await page.evaluate((vw) => {
    const q = (s) => document.querySelector(s);
    const m = (el) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return { w: Math.round(r.width), x: Math.round(r.left), display: cs.display, vis: cs.visibility, maxW: cs.maxWidth, flex: cs.flex };
    };
    const pageMain = q('.page-main.container, #maincontent');
    const columns = q('.page-main > .columns');
    const sidebar = q('.sidebar-main, .col-left.sidebar, .sidebar.sidebar-main-1, [class*="sidebar"]');
    const colMain = q('.col-main, .column.main');
    const viewProduct = q('.view-product');
    const containers = Array.from(document.querySelectorAll('.page-main .container, .col-main .container, .view-product .container'))
      .slice(0, 8)
      .map((el) => ({ cls: el.className.slice(0, 60), ...m(el) }));
    return {
      vw,
      pageMain: m(pageMain),
      columns: m(columns),
      colMain: m(colMain),
      sidebar: sidebar ? { sel: sidebar.className.slice(0, 80), ...m(sidebar) } : null,
      viewProduct: m(viewProduct),
      containers,
      colMainFill: pageMain && colMain ? Math.round((colMain.getBoundingClientRect().width / (pageMain.getBoundingClientRect().width - parseFloat(getComputedStyle(pageMain).paddingLeft) * 2)) * 100) : null,
    };
  }, vp.w);
  const issue = (data.colMainFill != null && data.colMainFill < 85) || (data.sidebar && data.sidebar.display !== 'none' && data.sidebar.w > 10);
  log({ hypothesisId: 'H-sidebar', location: 'tmp-pdp-sidebar-probe.mjs', message: issue ? 'PDP_LAYOUT_ISSUE' : 'PDP_LAYOUT_OK', data: { vp, ...data, issue } });
  console.log(JSON.stringify({ vp, issue, colMainFill: data.colMainFill, sidebar: data.sidebar, pageMainW: data.pageMain?.w, colMainW: data.colMain?.w, viewProductW: data.viewProduct?.w }, null, 2));
}
await browser.close();
