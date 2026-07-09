#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'post-fix', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PDP + '?postfix=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForSelector('.column.main, .col-main', { timeout: 30000 });
await page.waitForTimeout(2000);

const data = await page.evaluate(() => {
  const mainCol = document.querySelector('.column.main, .col-main');
  const pm = document.querySelector('.page-main.container');
  const bc = document.querySelector('.nav-breadcrumbs');
  const m = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), x: Math.round(el.getBoundingClientRect().left), maxW: getComputedStyle(el).maxWidth } : null;
  return {
    layout: document.body.className.match(/page-layout-\S+/)?.[0],
    columnsCls: document.querySelector('.page-main > .columns')?.className,
    mainColCls: mainCol?.className,
    pageMain: m(pm),
    mainCol: m(mainCol),
    navBc: m(bc),
    fillOk: mainCol && pm ? mainCol.getBoundingClientRect().width >= pm.getBoundingClientRect().width - 40 : false,
    alignOk: bc && pm ? Math.abs(bc.getBoundingClientRect().left - pm.getBoundingClientRect().left) <= 2 : true,
  };
});

const ok = data.fillOk && data.alignOk && data.layout === 'page-layout-1column';
log({ hypothesisId: 'BUG-34', location: 'tmp-pdp-postfix-verify.mjs', message: ok ? 'PDP_WIDTH_FIXED' : 'PDP_WIDTH_FAIL', data: { ...data, ok } });
console.log(JSON.stringify({ ok, ...data }, null, 2));
await browser.close();
process.exit(ok ? 0 : 1);
