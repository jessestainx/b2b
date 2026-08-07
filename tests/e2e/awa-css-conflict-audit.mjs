import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-305635.log';
const pages = [
  { id: 'home', url: 'https://awamotos.com/?cssaudit=1' },
  { id: 'plp', url: 'https://awamotos.com/bauletos.html?cssaudit=1' },
  { id: 'pdp', url: 'https://awamotos.com/bauleto-awa-modelo-proos-34-litros-dourado-340-dr.html?cssaudit=1' },
];

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({
    sessionId: '305635',
    timestamp: Date.now(),
    ...payload,
  }) + '\n');
}

async function auditPage(page, pageId, viewport) {
  await page.setViewportSize(viewport);
  await page.goto(pages.find(p => p.id === pageId).url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2500);

  const data = await page.evaluate(() => {
    const cs = (el) => el ? getComputedStyle(el) : null;
    const box = (el) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const s = cs(el);
      return {
        w: Math.round(r.width), h: Math.round(r.height),
        t: Math.round(r.top), l: Math.round(r.left),
        overflow: s.overflow, overflowX: s.overflowX, overflowY: s.overflowY,
        maxH: s.maxHeight, minH: s.minHeight, height: s.height,
        maxW: s.maxWidth, padding: s.padding, margin: s.margin,
        position: s.position, z: s.zIndex, display: s.display,
        fontSize: s.fontSize, lineHeight: s.lineHeight,
      };
    };

    // Find stylesheet rules that set a prop with !important for a selector match approximation
    function findImportantHits(el, prop) {
      const hits = [];
      if (!el) return hits;
      for (const sheet of document.styleSheets) {
        let rules;
        try { rules = sheet.cssRules; } catch { continue; }
        if (!rules) continue;
        const href = sheet.href || 'inline';
        const walk = (list) => {
          for (const rule of list) {
            if (rule.type === CSSRule.MEDIA_RULE || rule.type === CSSRule.SUPPORTS_RULE) {
              try { walk(rule.cssRules); } catch {}
              continue;
            }
            if (rule.type === CSSRule.STYLE_RULE) {
              try {
                if (!el.matches(rule.selectorText)) continue;
              } catch { continue; }
              const pri = rule.style.getPropertyPriority(prop);
              const val = rule.style.getPropertyValue(prop);
              if (val && pri === 'important') {
                hits.push({ href: String(href).split('/').pop(), sel: rule.selectorText.slice(0, 120), prop, val: val.trim() });
              }
            }
          }
        };
        try { walk(rules); } catch {}
      }
      return hits.slice(0, 12);
    }

    const mainHeader = document.querySelector('.header.awa-main-header, .awa-main-header');
    const sticky = document.querySelector('.header-wrapper-sticky');
    const siteHeader = document.querySelector('.awa-site-header, header.awa-site-header');
    const navBar = document.querySelector('.awa-nav-bar, .header-control.awa-nav-bar');
    const search = document.querySelector('#search');
    const searchCol = document.querySelector('.awa-header-search-col .block-content');
    const quick = document.querySelector('.awa-nav-quick-links');
    const menuTrigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const main = document.querySelector('#maincontent, main.page-main');
    const pageWrapper = document.querySelector('.page-wrapper');
    const productGrid = document.querySelector('ul.products, ul.product-grid, .products-grid');
    const productInfo = document.querySelector('.product-info, .product-item-info');
    const gallery = document.querySelector('.product.media, .gallery-placeholder');
    const fotorama = document.querySelector('.fotorama__stage, .fotorama__wrap');
    const footer = document.querySelector('footer.page-footer, .page-footer');

    // clipping: child taller than parent with overflow hidden
    function clipCheck(parent, child) {
      if (!parent || !child) return null;
      const ps = cs(parent), pr = parent.getBoundingClientRect(), cr = child.getBoundingClientRect();
      const hidden = ps.overflow === 'hidden' || ps.overflowY === 'hidden' || ps.overflowX === 'hidden';
      const spillY = Math.round(cr.bottom - pr.bottom);
      const spillX = Math.round(cr.right - pr.right);
      return { hidden, spillY, spillX, clipped: hidden && (spillY > 1 || spillX > 1) };
    }

    const mh = box(mainHeader);
    const nav = box(navBar);
    let spillIntoNav = null;
    if (mainHeader && navBar) {
      spillIntoNav = Math.round(mainHeader.getBoundingClientRect().bottom - navBar.getBoundingClientRect().top);
    }

    // Count inline !important in header
    let inlineImportant = 0;
    if (siteHeader) {
      siteHeader.querySelectorAll('[style*="important"]').forEach(() => inlineImportant++);
    }

    // Sheet density from document
    const sheets = [...document.styleSheets].map(s => (s.href || 'inline').split('/').pop().split('?')[0]);

    return {
      bodyClass: document.body.className.slice(0, 200),
      vw: window.innerWidth,
      mainHeader: mh,
      sticky: box(sticky),
      navBar: nav,
      search: box(search),
      searchCol: box(searchCol),
      quick: box(quick),
      menuTrigger: box(menuTrigger),
      main: box(main),
      pageWrapper: box(pageWrapper),
      productGrid: box(productGrid),
      productInfo: box(productInfo),
      gallery: box(gallery),
      fotorama: box(fotorama),
      footer: box(footer),
      spillIntoNav,
      clipMainHeaderSearch: clipCheck(mainHeader, searchCol || search),
      clipStickyNav: clipCheck(sticky, navBar),
      inlineImportant,
      importantOverflowHits: findImportantHits(mainHeader, 'overflow').concat(findImportantHits(mainHeader, 'max-height')).slice(0, 15),
      sheetsSample: sheets.filter(Boolean).slice(0, 40),
    };
  });

  // Hypotheses evaluation helpers
  const hyp = {
    A_overflowHiddenHeader: data.mainHeader && (data.mainHeader.overflow === 'hidden' || data.mainHeader.overflowY === 'hidden'),
    B_heightWar: data.mainHeader && data.mainHeader.maxH !== 'none' && data.mainHeader.minH !== '0px' && data.mainHeader.maxH !== data.mainHeader.minH,
    C_spillIntoNav: typeof data.spillIntoNav === 'number' && data.spillIntoNav > 2,
    D_searchClipped: !!(data.clipMainHeaderSearch && data.clipMainHeaderSearch.clipped),
    E_galleryWeird: data.gallery && (data.gallery.h < 100 || (data.gallery.maxH !== 'none' && parseFloat(data.gallery.maxH) < 200)),
    F_inlineImportantHeavy: data.inlineImportant > 50,
  };

  log({
    runId: 'css-audit',
    hypothesisId: 'A-F',
    location: `playwright:${pageId}:${viewport.width}`,
    message: 'css conflict snapshot',
    data: { pageId, viewport, hyp, ...data },
  });

  return { pageId, viewport: viewport.width, hyp, mainHeader: data.mainHeader, spillIntoNav: data.spillIntoNav, importantHits: data.importantOverflowHits?.length, inlineImportant: data.inlineImportant };
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

// clear log
fs.writeFileSync(LOG, '');

const summary = [];
for (const p of pages) {
  for (const vp of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    try {
      summary.push(await auditPage(page, p.id, vp));
      console.log('OK', p.id, vp.width, JSON.stringify(summary[summary.length - 1].hyp));
    } catch (e) {
      console.error('FAIL', p.id, vp.width, e.message);
      log({ runId: 'css-audit', hypothesisId: 'ERR', location: `playwright:${p.id}`, message: e.message, data: {} });
    }
  }
}

await browser.close();
console.log('SUMMARY', JSON.stringify(summary, null, 2));
