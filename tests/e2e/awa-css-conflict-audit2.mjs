import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-305635.log';
const pages = [
  { id: 'home', url: 'https://awamotos.com/?cssaudit=2' },
  { id: 'plp', url: 'https://awamotos.com/bauletos.html?cssaudit=2' },
  { id: 'pdp', url: 'https://awamotos.com/bauleto-awa-modelo-proos-34-litros-dourado-340-dr.html?cssaudit=2' },
];

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '305635', timestamp: Date.now(), ...payload }) + '\n');
}

async function run() {
  fs.writeFileSync(LOG, '');
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  for (const p of pages) {
    for (const vp of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
      await page.setViewportSize(vp);
      try {
        await page.goto(p.url, { waitUntil: 'networkidle', timeout: 90000 });
      } catch {
        await page.goto(p.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
      }
      await page.waitForTimeout(3000);

      const data = await page.evaluate(() => {
        const cs = el => el ? getComputedStyle(el) : null;
        const box = el => {
          if (!el) return null;
          const r = el.getBoundingClientRect();
          const s = cs(el);
          return {
            tag: el.tagName, cls: (el.className || '').toString().slice(0, 80),
            w: +r.width.toFixed(1), h: +r.height.toFixed(1), t: +r.top.toFixed(1), l: +r.left.toFixed(1),
            overflow: s.overflow, maxH: s.maxHeight, minH: s.minHeight, height: s.height,
            display: s.display, position: s.position, z: s.zIndex,
            paddingTop: s.paddingTop, paddingBottom: s.paddingBottom,
          };
        };

        function importantSources(el, props) {
          const out = {};
          if (!el) return out;
          for (const prop of props) {
            const hits = [];
            for (const sheet of document.styleSheets) {
              let rules; try { rules = sheet.cssRules; } catch { continue; }
              if (!rules) continue;
              const href = (sheet.href || 'inline').split('/').pop();
              const walk = (list) => {
                for (const rule of list) {
                  if (rule.cssRules) { try { walk(rule.cssRules); } catch {} continue; }
                  if (!rule.selectorText || !rule.style) continue;
                  try { if (!el.matches(rule.selectorText)) continue; } catch { continue; }
                  const val = rule.style.getPropertyValue(prop);
                  const pri = rule.style.getPropertyPriority(prop);
                  if (val) hits.push({ href, sel: rule.selectorText.slice(0, 100), val: val.trim(), important: pri === 'important' });
                }
              };
              try { walk(rules); } catch {}
            }
            // keep last 8 important + count conflicts
            const imp = hits.filter(h => h.important);
            const vals = [...new Set(imp.map(h => h.val))];
            out[prop] = { conflict: vals.length > 1, values: vals, samples: imp.slice(-8) };
          }
          return out;
        }

        const mainHeader = document.querySelector('.header.awa-main-header') || document.querySelector('.awa-main-header');
        const sticky = document.querySelector('.header-wrapper-sticky');
        const navDesktop = document.querySelector('.header-control.awa-nav-bar');
        const mobileNav = document.querySelector('.awa-mobile-nav, #awa-mobile-nav, nav.awa-mobile-nav');
        const search = document.querySelector('#search');
        const blockContent = document.querySelector('.awa-header-search-col .block-content, .block-search .block-content');
        const trigger = document.querySelector('[data-role="awa-vertical-menu-trigger"]');
        const thumb = document.querySelector('.product-image-container, .product-thumb img, .product-item-photo img');
        const gallery = document.querySelector('.product.media .gallery-placeholder, .product.media');
        const fotoramaStage = document.querySelector('.fotorama__stage');
        const grid = document.querySelector('.products-grid, ul.product-items, ul.products');
        const card = document.querySelector('li.item-product .product-info, .product-item-info');
        const pageMain = document.querySelector('#maincontent');
        const footer = document.querySelector('footer.page-footer');

        // Real overlap: mainHeader bottom vs next visible sibling/nav
        let overlapPx = null;
        if (mainHeader) {
          const mhBottom = mainHeader.getBoundingClientRect().bottom;
          const candidates = [navDesktop, mobileNav, pageMain].filter(Boolean);
          for (const c of candidates) {
            const r = c.getBoundingClientRect();
            if (r.height < 2 || r.width < 2) continue;
            overlapPx = Math.round(mhBottom - r.top);
            break;
          }
        }

        // Card text overflow
        let cardOverflow = null;
        if (card) {
          cardOverflow = {
            scrollH: card.scrollHeight,
            clientH: card.clientH || card.clientHeight,
            overflowY: cs(card).overflowY,
            clipped: card.scrollHeight > card.clientHeight + 2 && (cs(card).overflowY === 'hidden'),
          };
        }

        // Count !important in matched-ish sheets for header
        let importantInHeaderSheets = 0;
        // Global: elements with overflow hidden that clip interactive children
        const clipSuspects = [];
        document.querySelectorAll('.awa-main-header, .header-wrapper-sticky, .awa-nav-bar, .awa-nav-quick-links, .product.media, .fotorama__wrap, .products-grid, .column.main').forEach(el => {
          const s = cs(el);
          if (s.overflow === 'hidden' || s.overflowY === 'hidden') {
            clipSuspects.push({ cls: (el.className||'').toString().slice(0,60), overflow: s.overflow, h: Math.round(el.getBoundingClientRect().height), maxH: s.maxHeight });
          }
        });

        return {
          body: document.body.className.split(/\s+/).slice(0, 12),
          vw: innerWidth,
          mainHeader: box(mainHeader),
          sticky: box(sticky),
          navDesktop: box(navDesktop),
          mobileNav: box(mobileNav),
          search: box(search),
          blockContent: box(blockContent),
          trigger: box(trigger),
          thumb: box(thumb),
          gallery: box(gallery),
          fotoramaStage: box(fotoramaStage),
          grid: box(grid),
          card: box(card),
          pageMain: box(pageMain),
          footer: box(footer),
          overlapPx,
          cardOverflow,
          clipSuspects,
          cascade: importantSources(mainHeader, ['overflow', 'max-height', 'min-height', 'height']),
          triggerCascade: importantSources(trigger, ['font-size', 'color', 'max-width']),
          galleryCascade: importantSources(gallery, ['max-height', 'aspect-ratio', 'overflow']),
        };
      });

      const hyp = {
        A_headerOverflowHidden: !!(data.mainHeader && data.mainHeader.overflow === 'hidden'),
        B_overflowValueConflict: !!(data.cascade?.overflow?.conflict),
        C_maxHeightConflict: !!(data.cascade?.['max-height']?.conflict),
        D_realOverlap: typeof data.overlapPx === 'number' && data.overlapPx > 4,
        E_clipSuspects: (data.clipSuspects || []).length > 0,
        F_galleryShort: !!(data.gallery && data.gallery.h > 0 && data.gallery.h < 160),
        G_cardClipped: !!(data.cardOverflow && data.cardOverflow.clipped),
        H_triggerFontConflict: !!(data.triggerCascade?.['font-size']?.conflict),
      };

      log({ runId: 'css-audit-2', hypothesisId: 'A-H', location: `pw:${p.id}:${vp.width}`, message: 'deep css cascade audit', data: { pageId: p.id, vw: vp.width, hyp, ...data } });
      console.log(p.id, vp.width, JSON.stringify(hyp), 'overlap', data.overlapPx, 'clips', data.clipSuspects?.length, 'ovConflict', data.cascade?.overflow?.values);
    }
  }
  await browser.close();
}
await run();
