#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-dom-probe', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PDP, { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(2000);

const data = await page.evaluate(() => {
  const columns = document.querySelector('.page-main > .columns');
  const children = columns ? Array.from(columns.children).map((el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return {
      tag: el.tagName,
      cls: el.className.slice(0, 100),
      w: Math.round(r.width),
      x: Math.round(r.left),
      display: cs.display,
      flex: cs.flex,
      order: cs.order,
      gridColumn: cs.gridColumn,
    };
  }) : [];
  const colsLayout = columns ? getComputedStyle(columns) : null;
  const narrow = Array.from(document.querySelectorAll('.page-main *')).filter((el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    if (r.width < 200 || r.height < 20) return false;
    if (!['DIV', 'SECTION', 'MAIN', 'ARTICLE'].includes(el.tagName)) return false;
    const colMain = document.querySelector('.col-main, .column.main');
    if (!colMain || !colMain.contains(el)) return false;
    const parent = el.parentElement;
    if (!parent) return false;
    const pr = parent.getBoundingClientRect();
    if (pr.width < 400) return false;
    return r.width < pr.width * 0.75 && cs.maxWidth !== 'none' && cs.maxWidth !== '100%';
  }).slice(0, 12).map((el) => ({
    cls: el.className.toString().slice(0, 80),
    w: Math.round(el.getBoundingClientRect().width),
    parentW: Math.round(el.parentElement.getBoundingClientRect().width),
    maxW: getComputedStyle(el).maxWidth,
  }));
  return {
    columnsDisplay: colsLayout ? { display: colsLayout.display, flexDir: colsLayout.flexDirection, gridCols: colsLayout.gridTemplateColumns } : null,
    children,
    narrowContainers: narrow,
  };
});

log({ hypothesisId: 'H-dom', location: 'tmp-pdp-dom-probe.mjs', message: 'PDP_DOM_STRUCTURE', data });
console.log(JSON.stringify(data, null, 2));
await browser.close();
