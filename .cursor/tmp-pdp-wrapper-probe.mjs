#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-wrapper-probe', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });
await page.goto(PDP, { waitUntil: 'networkidle', timeout: 120000 }).catch(() => {});
await page.waitForTimeout(2000);

const data = await page.evaluate(() => {
  const vw = document.documentElement.clientWidth;
  const sels = ['.page-wrapper', '.page-main', '.columns.layout', '.col-main', '.product-view', '.main-detail', '.product-info-main', '.product.media'];
  const chain = sels.map((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { sel, missing: true };
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return { sel, w: Math.round(r.width), x: Math.round(r.left), right: Math.round(r.right), maxW: cs.maxWidth, padL: cs.paddingLeft, padR: cs.paddingRight };
  });
  const unusedTotal = chain[0] ? vw - chain[0].w : null;
  const sideGutter = chain.find(c => c.sel === '.page-main')?.x ?? 0;
  return { vw, chain, sideGutter, unusedTotal: unusedTotal != null ? Math.round(unusedTotal) : null, fillPct: chain.find(c => c.sel === '.col-main') ? Math.round((chain.find(c => c.sel === '.col-main').w / vw) * 100) : null };
});

const issue = data.vw >= 1920 && data.fillPct < 70;
log({ hypothesisId: 'H-ultrawide', location: 'tmp-pdp-wrapper-probe.mjs', message: issue ? 'PDP_NARROW_ON_ULTRAWIDE' : 'PDP_ULTRAWIDE_OK', data: { ...data, issue } });
console.log(JSON.stringify(data, null, 2));
await browser.close();
