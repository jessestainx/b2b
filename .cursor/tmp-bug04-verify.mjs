#!/usr/bin/env node
/**
 * BUG-04 post-fix verification — account prompt scrollH vs clientH
 */
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const BASE = process.env.AWA_BASE_URL || 'https://awamotos.com/';

function log(payload) {
  const line = JSON.stringify({
    sessionId: '199043',
    timestamp: Date.now(),
    runId: process.env.RUN_ID || 'post-fix',
    ...payload,
  });
  fs.appendFileSync(LOG, line + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForSelector('.awa-header-account-prompt', { timeout: 30000 });

const data = await page.evaluate(() => {
  const el = document.querySelector('.awa-header-account-prompt');
  if (!el) return { found: false };
  const cs = getComputedStyle(el);
  const text = el.querySelector('.awa-header-account-prompt__text, .awa-header-account-prompt__guest');
  const textCs = text ? getComputedStyle(text) : null;
  return {
    found: true,
    scrollH: el.scrollHeight,
    clientH: el.clientHeight,
    offsetH: el.offsetHeight,
    height: cs.height,
    maxHeight: cs.maxHeight,
    minHeight: cs.minHeight,
    overflow: cs.overflow,
    alignItems: cs.alignItems,
    paddingBlock: cs.paddingBlock,
    textScrollH: text ? text.scrollHeight : null,
    textOverflow: textCs ? textCs.overflow : null,
    distillId: document.getElementById('awa-header-distill-terminal-20260616d')
      ? '20260616d'
      : document.querySelector('[id^="awa-header-distill-terminal-"]')?.id || 'missing',
  };
});

const ok = data.found && data.scrollH <= data.clientH && data.height !== '44px';
log({
  hypothesisId: 'BUG-04',
  location: 'tmp-bug04-verify.mjs',
  message: ok ? 'BUG-04_FIXED' : 'BUG-04_FAIL',
  data: { ...data, ok },
});

console.log(JSON.stringify({ ok, ...data }, null, 2));
await browser.close();
process.exit(ok ? 0 : 1);
