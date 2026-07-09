#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-block-shell', ...payload }) + '\n');
}

async function probe(page, label) {
  return page.evaluate((label) => {
    const pm = document.querySelector('.page-main.container');
    const cols = document.querySelector('.page-main > .columns');
    const cm = document.querySelector('.col-main');
    const m = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), maxW: getComputedStyle(el).maxWidth, x: Math.round(el.getBoundingClientRect().left) } : null;
    return { label, pm: m(pm), cols: m(cols), cm: m(cm), pdpShell: !!document.querySelector('link[href*="awa-pdp-shell-final"]') };
  }, label);
}

const browser = await chromium.launch({ headless: true });

// Baseline
const page1 = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page1.goto(PDP + '?base=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
await page1.waitForTimeout(2500);
const baseline = await probe(page1, 'baseline');

// Block pdp-shell-final.css only
const ctx2 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
await ctx2.route('**/*', (route) => {
  if (/awa-pdp-shell-final/i.test(route.request().url())) return route.abort();
  return route.continue();
});
const page2 = await ctx2.newPage();
await page2.goto(PDP + '?noshell=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
await page2.waitForTimeout(2500);
const noShell = await probe(page2, 'no-pdp-shell-final');

const issue = noShell.cols && baseline.cols && noShell.cols.w < baseline.cols.w - 20;
log({ hypothesisId: 'H-shell', location: 'tmp-pdp-block-shell.mjs', message: issue ? 'PDP_SHELL_CSS_REQUIRED' : 'PDP_SHELL_REDUNDANT', data: { baseline, noShell, issue } });
console.log(JSON.stringify({ issue, baseline, noShell }, null, 2));
await browser.close();
