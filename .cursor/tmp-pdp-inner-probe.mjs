#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-inner-probe', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PDP, { waitUntil: 'networkidle', timeout: 120000 }).catch(() => {});
await page.waitForTimeout(3000);

const data = await page.evaluate(() => {
  const pick = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return { w: Math.round(r.width), x: Math.round(r.left), maxW: cs.maxWidth, width: cs.width, pad: cs.paddingLeft, margin: cs.marginLeft };
  };
  const vw = document.documentElement.clientWidth;
  const headerInner = document.querySelector('.awa-main-header__inner, .awa-header-primary-row, [data-awa-header-row]');
  const pageMain = document.querySelector('#maincontent.page-main, .page-main.container');
  const colMain = document.querySelector('.col-main, .column.main');
  const row = document.querySelector('.main-detail > .row');
  const col1 = row?.querySelector(':scope > .col-md-6:first-child');
  const col2 = row?.querySelector(':scope > .col-md-6:last-child');
  const media = document.querySelector('.product.media, .gallery-placeholder');
  const info = document.querySelector('.product-info-main');
  const detailed = document.querySelector('.product.info.detailed');
  const related = document.querySelector('.awa-pdp-related');
  const tabs = document.querySelector('#product\\.info\\.detailed, .product.data.items');

  const col1W = pick(col1);
  const col2W = pick(col2);
  const mediaW = pick(media);
  const infoW = pick(info);

  return {
    vw,
    header: pick(headerInner),
    pageMain: pick(pageMain),
    colMain: pick(colMain),
    row: pick(row),
    col1: col1W,
    col2: col2W,
    media: mediaW,
    info: infoW,
    mediaFillPct: col1W && mediaW ? Math.round((mediaW.w / col1W.w) * 100) : null,
    infoFillPct: col2W && infoW ? Math.round((infoW.w / col2W.w) * 100) : null,
    detailed: pick(detailed),
    related: pick(related),
    tabs: pick(tabs),
    alignHeaderToMain: headerInner && pageMain ? Math.round(headerInner.getBoundingClientRect().left - pageMain.getBoundingClientRect().left) : null,
    bodyClasses: document.body.className,
    layoutClass: document.querySelector('.page-layout')?.className || null,
  };
});

const issues = [];
if (data.mediaFillPct != null && data.mediaFillPct < 95) issues.push('media_not_filling_col');
if (data.infoFillPct != null && data.infoFillPct < 95) issues.push('info_not_filling_col');
if (data.alignHeaderToMain != null && Math.abs(data.alignHeaderToMain) > 4) issues.push('header_main_misaligned');
if (data.colMain && data.pageMain && data.colMain.w < data.pageMain.w - 40) issues.push('col_main_narrow_vs_page_main');
if (data.detailed && data.colMain && data.detailed.w < data.colMain.w * 0.95) issues.push('detailed_narrow');

log({ hypothesisId: 'H-inner', location: 'tmp-pdp-inner-probe.mjs', message: issues.length ? 'PDP_INNER_ISSUES' : 'PDP_INNER_OK', data: { ...data, issues } });
console.log(JSON.stringify({ ...data, issues }, null, 2));
await browser.close();
