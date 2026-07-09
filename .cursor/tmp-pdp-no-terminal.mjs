#!/usr/bin/env node
/** Hypothesis: without terminal CSS, consolidated legacy rules break PDP width */
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-no-terminal', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
await context.route('**/*', (route) => {
  const url = route.request().url();
  if (/awa-pdp-shell-final|awa-align-grid-terminal|awa-header-distill-terminal|distill-terminal/i.test(url)) {
    return route.abort();
  }
  return route.continue();
});
const page = await context.newPage();
await page.goto(PDP + '?noterm=' + Date.now(), { waitUntil: 'networkidle', timeout: 120000 }).catch(() => {});
await page.waitForTimeout(3000);

const data = await page.evaluate(() => {
  const hdr = document.querySelector('[data-awa-header-row]');
  const pm = document.querySelector('.page-main.container');
  const cols = document.querySelector('.page-main > .columns');
  const cm = document.querySelector('.col-main');
  const m = (el) => el ? { w: Math.round(el.getBoundingClientRect().width), x: Math.round(el.getBoundingClientRect().left), maxW: getComputedStyle(el).maxWidth } : null;
  const body = getComputedStyle(document.body);
  return {
    pm: m(pm), cols: m(cols), cm: m(cm), hdr: m(hdr),
    tokens: {
      pdpContainer: body.getPropertyValue('--awa-pdp-axis-container-max').trim(),
      pdpContent: body.getPropertyValue('--awa-pdp-axis-content-max').trim(),
      croMax: body.getPropertyValue('--awa-cro-container-max').trim(),
    },
    drift: hdr && pm ? pm.w - hdr.w : null,
    colMainVsCols: cols && cm ? cm.w - cols.w : null,
  };
});

const issue = (data.drift != null && Math.abs(data.drift) > 4) || (data.cols && data.pm && data.cols.w < data.pm.w - 64) || (data.cm && data.cols && data.cm.w < data.cols.w - 8);
log({ hypothesisId: 'H-no-terminal', location: 'tmp-pdp-no-terminal.mjs', message: issue ? 'PDP_BREAKS_WITHOUT_TERMINAL' : 'PDP_OK_WITHOUT_TERMINAL', data: { ...data, issue } });
console.log(JSON.stringify({ issue, ...data }, null, 2));
await browser.close();
