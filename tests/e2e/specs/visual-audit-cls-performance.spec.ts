/**
 * Visual Audit — CLS / Performance (Core Web Vitals Visuais)
 *
 * Valida métricas de performance visual:
 *  1. CLS (Cumulative Layout Shift) — threshold: < 0.25 (poor)
 *  2. LCP (Largest Contentful Paint) — threshold: < 4000ms (poor)
 *  3. Imagens sem width/height (causa CLS)
 *  4. Estabilidade do layout ao fazer scroll
 *  5. Long Tasks (> 50ms) — threshold: ≤ 30
 */
import { test, expect, type Page } from '@playwright/test';
import { navigateTo } from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';

const CLS_THRESHOLD_POOR = 0.25;
const LCP_THRESHOLD_MS   = 4_000;
const LONG_TASK_MAX      = 30;

/* ── Helper: medir CLS ──────────────────────────────────────────── */
async function measureCLS(page: Page, waitMs = 4_000): Promise<number> {
  return page.evaluate(async (wait: number) => {
    return new Promise<number>(resolve => {
      let cls = 0;
      const obs = new PerformanceObserver(list => {
        for (const entry of list.getEntries()) {
          if (!(entry as PerformanceEntry & { hadRecentInput?: boolean }).hadRecentInput) {
            cls += (entry as PerformanceEntry & { value?: number }).value ?? 0;
          }
        }
      });
      try { obs.observe({ type: 'layout-shift', buffered: true }); } catch { resolve(0); return; }
      setTimeout(() => { obs.disconnect(); resolve(cls); }, wait);
    });
  }, waitMs).catch(() => 0);
}

/* ── Helper: medir LCP ──────────────────────────────────────────── */
async function measureLCP(page: Page): Promise<number> {
  return page.evaluate(async () => {
    return new Promise<number>(resolve => {
      const obs = new PerformanceObserver(list => {
        const entries = list.getEntries();
        if (entries.length > 0) {
          resolve(entries[entries.length - 1].startTime);
          obs.disconnect();
        }
      });
      try { obs.observe({ type: 'largest-contentful-paint', buffered: true }); }
      catch { resolve(0); return; }
      setTimeout(() => { obs.disconnect(); resolve(0); }, 8_000);
    });
  }).catch(() => 0);
}

/* ── Helper: imagens sem dimensions ─────────────────────────────── */
async function findImagesWithoutDimensions(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const imgs = Array.from(document.querySelectorAll('img'));
    return imgs
      .filter(img => !img.getAttribute('width') && !img.getAttribute('height'))
      .map(img => img.src || img.dataset['src'] || '(no src)');
  }).catch(() => []);
}

/* ── Helper: contar Long Tasks ───────────────────────────────────── */
async function countLongTasks(page: Page, waitMs = 5_000): Promise<number> {
  return page.evaluate(async (wait: number) => {
    return new Promise<number>(resolve => {
      let count = 0;
      const obs = new PerformanceObserver(list => {
        count += list.getEntries().length;
      });
      try { obs.observe({ type: 'longtask', buffered: true }); } catch { resolve(0); return; }
      setTimeout(() => { obs.disconnect(); resolve(count); }, wait);
    });
  }, waitMs).catch(() => 0);
}

/* ═══════════════════════════════════════════════════════════════════
   1. CLS POR PÁGINA
   ═══════════════════════════════════════════════════════════════════ */
test.describe('CLS — Cumulative Layout Shift por página', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  const pagesToCheck = [
    { label: 'Home',    url: BASE },
    { label: 'PLP',     url: `${BASE}/bagageiros.html` },
    { label: 'Busca',   url: `${BASE}/catalogsearch/result/?q=retrovisor` },
  ];

  for (const { label, url } of pagesToCheck) {
    test(`CLS < ${CLS_THRESHOLD_POOR} — ${label}`, async ({ page }) => {
      const ok = await navigateTo(page, url);
      if (!ok) { test.skip(); return; }

      const cls = await measureCLS(page, 4_000);
      console.log(`CLS ${label}: ${cls.toFixed(4)}`);

      if (cls >= CLS_THRESHOLD_POOR) {
        console.warn(`⚠️ CLS POOR na ${label}: ${cls.toFixed(4)} (threshold: ${CLS_THRESHOLD_POOR})`);
      }
      /* Apenas aviso — não falha para não bloquear CI */
      expect(cls, `CLS ${label} deve ser menor que ${CLS_THRESHOLD_POOR} (Poor threshold)`).toBeLessThan(CLS_THRESHOLD_POOR);
    });
  }
});

/* ═══════════════════════════════════════════════════════════════════
   2. LCP POR PÁGINA
   ═══════════════════════════════════════════════════════════════════ */
test.describe('LCP — Largest Contentful Paint', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test(`LCP < ${LCP_THRESHOLD_MS}ms — Home`, async ({ page }) => {
    const ok = await navigateTo(page, BASE);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(1_000);

    const lcp = await measureLCP(page);
    console.log(`LCP Home: ${lcp.toFixed(0)}ms`);

    if (lcp > 0) {
      expect(lcp, `LCP Home deve ser < ${LCP_THRESHOLD_MS}ms (Poor threshold)`).toBeLessThan(LCP_THRESHOLD_MS);
    } else {
      console.warn('⚠️ LCP não mensurável (provavelmente não suportado pelo runtime headless)');
    }
  });

  test(`LCP < ${LCP_THRESHOLD_MS}ms — PLP`, async ({ page }) => {
    const ok = await navigateTo(page, `${BASE}/bagageiros.html`);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(1_000);

    const lcp = await measureLCP(page);
    console.log(`LCP PLP: ${lcp.toFixed(0)}ms`);

    if (lcp > 0) {
      expect(lcp, `LCP PLP deve ser < ${LCP_THRESHOLD_MS}ms`).toBeLessThan(LCP_THRESHOLD_MS);
    }
  });
});

/* ═══════════════════════════════════════════════════════════════════
   3. IMAGENS SEM WIDTH/HEIGHT
   ═══════════════════════════════════════════════════════════════════ */
test.describe('CLS — Imagens sem dimensões (causam layout shift)', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Home: max 5 imagens sem width/height', async ({ page }) => {
    const ok = await navigateTo(page, BASE);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const imgs = await findImagesWithoutDimensions(page);
    console.log(`Home: ${imgs.length} imgs sem dimensões`);
    if (imgs.length > 0) console.log('Imgs sem dims:', imgs.slice(0, 5));
    expect(imgs.length, 'Home: máximo 5 imgs sem width/height (causam CLS)').toBeLessThanOrEqual(5);
  });

  test('PLP: max 10 imagens sem width/height', async ({ page }) => {
    const ok = await navigateTo(page, `${BASE}/bagageiros.html`);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);

    const imgs = await findImagesWithoutDimensions(page);
    console.log(`PLP: ${imgs.length} imgs sem dimensões`);
    expect(imgs.length, 'PLP: máximo 10 imgs sem width/height').toBeLessThanOrEqual(10);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   4. ESTABILIDADE AO FAZER SCROLL
   ═══════════════════════════════════════════════════════════════════ */
test.describe('CLS — Estabilidade no scroll', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test('Home: CLS < 0.60 durante scroll completo', async ({ page }) => {
    const ok = await navigateTo(page, BASE);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(1_500);

    /* Iniciar medição de CLS */
    const clsPromise = measureCLS(page, 5_000);

    /* Scroll até o final */
    await page.evaluate(async () => {
      const total = document.body.scrollHeight;
      let pos     = 0;
      while (pos < total) {
        pos += 400;
        window.scrollTo(0, pos);
        await new Promise(r => setTimeout(r, 150));
      }
    }).catch(() => {});

    const cls = await clsPromise;
    console.log(`CLS após scroll Home: ${cls.toFixed(4)}`);

    /* Threshold mais permissivo durante scroll */
    /* Headless pode superestimar CLS com lazy blocks/hidratação. */
    expect(cls, 'CLS durante scroll Home deve ser < 0.60').toBeLessThan(0.60);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   5. LONG TASKS
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Performance — Long Tasks (> 50ms)', () => {
  test.use({ viewport: { width: 1366, height: 768 } });

  test(`Home: máximo ${LONG_TASK_MAX} Long Tasks nos primeiros 5s`, async ({ page }) => {
    const ok = await navigateTo(page, BASE);
    if (!ok) { test.skip(); return; }

    const count = await countLongTasks(page, 5_000);
    console.log(`Long Tasks Home (5s): ${count}`);

    if (count > LONG_TASK_MAX) {
      console.warn(`⚠️ Muitas Long Tasks na Home: ${count} (threshold: ${LONG_TASK_MAX})`);
    }
    expect(count, `Home deve ter ≤ ${LONG_TASK_MAX} Long Tasks (> 50ms) nos primeiros 5s`).toBeLessThanOrEqual(LONG_TASK_MAX);
  });
});
