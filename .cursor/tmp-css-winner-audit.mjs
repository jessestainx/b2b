import { chromium } from 'playwright';
import fs from 'fs';

const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-2f476f.log';
const log = (hypothesisId, message, data) => {
  fs.appendFileSync(
    logPath,
    JSON.stringify({
      sessionId: '2f476f',
      runId: 'css-winner-audit',
      hypothesisId,
      location: 'playwright:css-winner',
      message,
      data,
      timestamp: Date.now(),
    }) + '\n'
  );
};

async function auditPage(url, label, viewport) {
  const browser = await chromium.launch({ timeout: 30000 });
  const page = await browser.newPage({ viewport });
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(8000);

  const data = await page.evaluate(() => {
    const promo = document.querySelector(
      '#awa-b2b-promo-bar, .top-header.awa-b2b-promo-bar, .awa-b2b-promo-bar[data-awa-header-utility]'
    );
    const brand = document.querySelector(
      '.header-wrapper-sticky .awa-header-brand-cell, .header-wrapper-sticky .col-md-2.awa-header-brand'
    );
    const row = document.querySelector(
      '.awa-main-header__inner.wp-header, .awa-main-header__inner[data-awa-header-row]'
    );
    const pageMain = document.querySelector('.page-main, main.page-main');
    const sticky = document.querySelector('.header-wrapper-sticky');

    const issues = [];
    const pcs = promo && getComputedStyle(promo);
    const bcs = brand && getComputedStyle(brand);
    const rcs = row && getComputedStyle(row);

    if (pcs) {
      const blob = pcs.backgroundImage + '|' + pcs.backgroundColor;
      if (/gradient/i.test(blob) || /rgb\\(183,? 51,? 55\\)|#b73337/i.test(blob)) {
        issues.push('promo_red');
      }
    }
    if (bcs?.alignSelf === 'start') issues.push('brand_align_start');
    if (rcs && window.innerWidth >= 992) {
      const mh = parseFloat(rcs.minHeight);
      const xh = parseFloat(rcs.maxHeight);
      if (mh >= 71 && mh <= 73 && xh >= 87) issues.push('visual_audit_72_88');
      if (parseFloat(rcs.height) > 72) issues.push('row_tall_' + Math.round(parseFloat(rcs.height)));
    }
    if (window.innerWidth < 992 && row) {
      const rh = Math.round(row.getBoundingClientRect().height);
      if (rh > 110) issues.push('mobile_row_' + rh);
    }

    let headerOverlap = null;
    if (pageMain && sticky && window.scrollY < 20) {
      const hb = sticky.getBoundingClientRect().bottom;
      const mt = pageMain.getBoundingClientRect().top;
      if (hb > mt + 10) {
        issues.push('header_overlap_' + Math.round(hb - mt));
        headerOverlap = { headerBottom: Math.round(hb), mainTop: Math.round(mt), gap: Math.round(hb - mt) };
      }
    }

    const bodyLinks = Array.from(document.querySelectorAll('body > link[rel="stylesheet"]')).map((l) => ({
      href: (l.href || '').split('/').pop()?.slice(0, 60),
      index: Array.from(document.body.children).indexOf(l),
    }));

    return {
      issues,
      vw: window.innerWidth,
      promoBg: pcs?.backgroundColor,
      promoBgImg: pcs?.backgroundImage?.slice(0, 40),
      brandAlign: bcs?.alignSelf,
      rowH: row ? Math.round(row.getBoundingClientRect().height) : null,
      rowMin: rcs?.minHeight,
      rowMax: rcs?.maxHeight,
      stickyH: sticky ? Math.round(sticky.getBoundingClientRect().height) : null,
      headerOverlap,
      hasGlobalCritical: !!document.getElementById('awa-header-impeccable-critical-global'),
      hasV12: !!document.getElementById('awa-header-impeccable-cascade-lock-v12'),
      guardHasRules: !!document.getElementById('awa-header-cascade-lock-guard')?.textContent?.includes('textContent=rules'),
      styleIsLast: document.getElementById('awa-header-impeccable-cascade-lock-v12') === document.body?.lastElementChild,
      bodyStylesheetCount: bodyLinks.length,
      refinementsInHead: !!document.querySelector('link[href*="awa-bundle-refinements"]'),
    };
  });

  log('H75-H80', `${label} @8s`, data);
  await browser.close();
  return data;
}

for (const spec of [
  { url: 'https://awamotos.com/', label: 'home-mobile', w: 390, h: 844 },
  { url: 'https://awamotos.com/', label: 'home-desktop', w: 1280, h: 800 },
  { url: 'https://awamotos.com/motos.html', label: 'plp-desktop', w: 1280, h: 800 },
  { url: 'https://awamotos.com/motos.html', label: 'plp-mobile', w: 390, h: 844 },
]) {
  await auditPage(spec.url, spec.label, { width: spec.w, height: spec.h });
}

console.log('audit done');
