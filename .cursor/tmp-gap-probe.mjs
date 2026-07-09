#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
function log(id, data, ok) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId:'199043',timestamp:Date.now(),runId:'gap-probe',hypothesisId:id,message:ok?'OK':'FAIL',data:{...data,ok}})+'\n');
}

const browser = await chromium.launch({ headless:true});
const page = await browser.newPage({ viewport:{width:1280,height:900}});
await page.goto('https://awamotos.com/', {waitUntil:'domcontentloaded',timeout:90000});
await page.waitForTimeout(3000);

const home = await page.evaluate(() => {
  const els = [
    '.content-top-home',
    'main.page-main',
    '.page-main .columns',
    'footer.page-footer',
    '.content-top-home .rokan-bestseller:last-of-type',
    '.content-top-home .awa-shelf--carousel:last-of-type',
  ].map(sel => {
    const el = document.querySelector(sel);
    if (!el) return { sel, missing:true };
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return { sel, y:Math.round(r.top+window.scrollY), bottom:Math.round(r.bottom+window.scrollY), h:Math.round(r.height), mb:cs.marginBottom, pb:cs.paddingBottom, mt:cs.marginTop, minH:cs.minHeight };
  });
  const footer = document.querySelector('footer.page-footer');
  const lastShelf = document.querySelector('.content-top-home .awa-shelf--carousel:last-of-type, .content-top-home .rokan-bestseller:last-of-type');
  return { els, gap: footer && lastShelf ? Math.round(footer.getBoundingClientRect().top+window.scrollY - (lastShelf.getBoundingClientRect().bottom+window.scrollY)) : null };
});

const b04 = await page.evaluate(() => {
  const el = document.querySelector('.awa-header-account-prompt');
  if (!el) return { ok:false, reason:'missing' };
  const cs = getComputedStyle(el);
  return { scrollH:el.scrollHeight, clientH:el.clientHeight, height:cs.height, maxH:cs.maxHeight, align:cs.alignItems };
});

const mainProbe = await page.evaluate(() => {
  const main = document.querySelector('main.page-main#maincontent');
  if (!main) return { ok: false, reason: 'missing' };
  const cs = getComputedStyle(main);
  const r = main.getBoundingClientRect();
  return {
    h: Math.round(r.height),
    height: cs.height,
    maxH: cs.maxHeight,
    minH: cs.minHeight,
    overflow: cs.overflow,
    pb: cs.paddingBottom
  };
});

const contentTop = home.els.find((e) => e.sel === '.content-top-home');
const footerEl = home.els.find((e) => e.sel === 'footer.page-footer');
const footerGap = contentTop && footerEl ? footerEl.y - contentTop.bottom : null;

console.log(JSON.stringify({ home, b04, mainProbe, footerGap }, null, 2));
log('BUG-10', { ...home, mainProbe, footerGap }, mainProbe.h <= 52 && footerGap <= 64);
log('BUG-04', b04, b04.scrollH <= b04.clientH && b04.align === 'flex-start');
await browser.close();
