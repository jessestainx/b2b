#!/usr/bin/env node
/** First-paint PDP width — before networkidle / async CSS */
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-first-paint', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

const snapshots = [];
page.on('domcontentloaded', async () => {
  try {
    const d = await page.evaluate(() => {
      const pm = document.querySelector('.page-main.container');
      const cols = document.querySelector('.page-main > .columns');
      const cm = document.querySelector('.col-main');
      const m = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), maxW: getComputedStyle(el).maxWidth } : null;
      return { pm: m(pm), cols: m(cols), cm: m(cm), hasDistill: !!document.querySelector('[id^="awa-header-distill-terminal"]'), hasAlign: !!document.getElementById('awa-align-grid-terminal-2026-06-11') };
    });
    snapshots.push({ phase: 'domcontentloaded', ...d });
  } catch (_) {}
});

await page.goto(PDP + '?fp=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(500);
const mid = await page.evaluate(() => {
  const pm = document.querySelector('.page-main.container');
  const cols = document.querySelector('.page-main > .columns');
  const cm = document.querySelector('.col-main');
  const hdr = document.querySelector('[data-awa-header-row]');
  const m = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), x: Math.round(el.getBoundingClientRect().left), maxW: getComputedStyle(el).maxWidth } : null;
  return { pm: m(pm), cols: m(cols), cm: m(cm), hdr: m(hdr) };
});
snapshots.push({ phase: '500ms', ...mid });
await page.waitForTimeout(3000);
const late = await page.evaluate(() => {
  const pm = document.querySelector('.page-main.container');
  const cols = document.querySelector('.page-main > .columns');
  const cm = document.querySelector('.col-main');
  const m = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), maxW: getComputedStyle(el).maxWidth } : null;
  return { pm: m(pm), cols: m(cols), cm: m(cm) };
});
snapshots.push({ phase: '3500ms', ...late });

const issue = snapshots.some(s => s.cols && s.pm && s.cols.w < s.pm.w - 80) || snapshots.some(s => s.cm && s.cols && s.cm.w < s.cols.w - 40);
log({ hypothesisId: 'H-fouc', location: 'tmp-pdp-first-paint.mjs', message: issue ? 'PDP_WIDTH_FOUC' : 'PDP_WIDTH_STABLE', data: { snapshots, issue } });
console.log(JSON.stringify({ issue, snapshots }, null, 2));
await browser.close();
