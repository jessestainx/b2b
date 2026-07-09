#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const urls = [
  'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html',
  'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html',
];

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-tokens', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

for (const url of urls) {
  await page.goto(url + '?tok=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(2500);
  const data = await page.evaluate(() => {
    const root = getComputedStyle(document.documentElement);
    const body = getComputedStyle(document.body);
    const pm = document.querySelector('.page-main.container');
    const pmCs = pm ? getComputedStyle(pm) : null;
    const hdr = document.querySelector('.awa-main-header__inner, [data-awa-header-row]');
    const tokens = [
      '--awa-container-max', '--awa-cro-container-max', '--awa-grid-shell-max',
      '--awa-container-catalog', '--awa-pdp-axis-container-max', '--awa-pdp-axis-content-max',
      '--awa-grid-container-pad', '--awa-hdr-container-max', '--awa-hdr-content-max',
    ].map((t) => ({ t, root: root.getPropertyValue(t).trim(), body: body.getPropertyValue(t).trim() }));
    return {
      url: location.pathname,
      tokens,
      pageMain: pm ? { w: Math.round(pm.getBoundingClientRect().width), maxW: pmCs.maxWidth, x: Math.round(pm.getBoundingClientRect().left) } : null,
      header: hdr ? { w: Math.round(hdr.getBoundingClientRect().width), x: Math.round(hdr.getBoundingClientRect().left) } : null,
      drift: hdr && pm ? Math.round(pm.getBoundingClientRect().left - hdr.getBoundingClientRect().left) : null,
      widthDrift: hdr && pm ? Math.round(pm.getBoundingClientRect().width - hdr.getBoundingClientRect().width) : null,
    };
  });
  const issue = data.drift != null && Math.abs(data.drift) > 2 || data.widthDrift != null && Math.abs(data.widthDrift) > 2;
  log({ hypothesisId: 'H-tokens', location: 'tmp-pdp-tokens.mjs', message: issue ? 'PDP_AXIS_DRIFT' : 'PDP_AXIS_ALIGNED', data: { ...data, issue } });
  console.log(JSON.stringify(data, null, 2));
}
await browser.close();
