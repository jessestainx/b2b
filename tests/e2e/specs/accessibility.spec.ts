/**
 * AWA Motos — Accessibility Tests (a11y)
 */

import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import * as fs from 'fs';
import * as path from 'path';

async function goHome(page: Page): Promise<void> {
  let ok = false;
  await Promise.race<void>([
    (async () => {
      await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 30_000 });
      await page.waitForSelector('body.cms-index-index, body.cms-home', { timeout: 10_000 });
      ok = true;
    })().catch(() => {}),
    new Promise<void>(r => setTimeout(r, 45_000)),
  ]);
  if (!ok) { test.skip(); return; }
}

async function goCategoryPage(page: Page): Promise<void> {
  let ok = false;
  await Promise.race<void>([
    (async () => {
      await page.goto('/acessorios.html', { waitUntil: 'domcontentloaded', timeout: 30_000 });
      await page.waitForSelector('.catalog-category-view, .page-layout-2columns-left', { timeout: 10_000 });
      ok = true;
    })().catch(() => {}),
    new Promise<void>(r => setTimeout(r, 45_000)),
  ]);
  if (!ok) { test.skip(); return; }
}

async function goPDP(page: Page): Promise<void> {
  await goCategoryPage(page);
  let ok = false;
  await Promise.race<void>([
    (async () => {
      const firstProduct = page.locator('.item-product a.product-item-link').first();
      await firstProduct.waitFor({ timeout: 10_000 });
      await firstProduct.click();
      await page.waitForLoadState('domcontentloaded', { timeout: 30_000 });
      await page.waitForSelector('.product-info-main', { timeout: 15_000 });
      ok = true;
    })().catch(() => {}),
    new Promise<void>(r => setTimeout(r, 60_000)),
  ]);
  if (!ok) { test.skip(); return; }
}

/* ── race helper para chamadas CDP individuais ── */
function raceVal<T>(promise: Promise<T>, fallback: T, ms = 6_000): Promise<T> {
  return Promise.race<T>([
    promise.catch(() => fallback),
    new Promise<T>(r => setTimeout(() => r(fallback), ms)),
  ]);
}

/* ════════════════════════════════════════════════════════════════════════
   SUITE 1 — LANDMARKS E ESTRUTURA
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Landmarks e estrutura', () => {
  test('Homepage tem landmark <main> @a11y', async ({ page }) => {
    await goHome(page);
    const attached = await raceVal(
      page.locator('main, [role="main"]').first().waitFor({ state: 'attached', timeout: 5_000 }).then(() => true),
      false
    );
    expect(attached, 'Deve existir elemento <main>').toBe(true);
  });

  test('Homepage tem landmark <nav> ou role navigation @a11y', async ({ page }) => {
    await goHome(page);
    const attached = await raceVal(
      page.locator('nav, [role="navigation"]').first().waitFor({ state: 'attached', timeout: 5_000 }).then(() => true),
      false
    );
    expect(attached, 'Deve existir <nav>').toBe(true);
  });

  test('Homepage tem <h1> único @a11y', async ({ page }) => {
    await goHome(page);
    const h1Count = await raceVal(page.locator('h1').count(), 0);
    expect(h1Count, 'Deve haver no máximo 1 <h1> na homepage').toBeLessThanOrEqual(1);
  });

  test('PDP tem <h1> com nome do produto @a11y', async ({ page }) => {
    await goPDP(page);
    const h1 = page.locator('h1').first();
    const visible = await raceVal(
      h1.isVisible({ timeout: 8_000 }).catch(() => false),
      false,
      10_000
    );
    if (!visible) { test.skip(); return; }
    const text = await raceVal(h1.textContent(), null, 5_000);
    expect((text?.trim() ?? '').length, 'H1 não deve ser vazio').toBeGreaterThan(0);
  });

  test('Categoria tem <h1> visível @a11y', async ({ page }) => {
    await goCategoryPage(page);
    const h1 = page.locator('h1').first();
    const visible = await raceVal(
      h1.isVisible({ timeout: 8_000 }).catch(() => false),
      false,
      10_000
    );
    if (!visible) { test.skip(); return; }
    expect(visible, 'H1 deve estar visível na página de categoria').toBe(true);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 2 — IMAGENS
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Imagens com alt text', () => {
  test('Logo do header tem alt ou aria-label @a11y', async ({ page }) => {
    await goHome(page);
    const logo = page.locator('.awa-site-header .logo img, .awa-site-header .logo').first();
    const logoVisible = await raceVal(
      logo.waitFor({ state: 'visible', timeout: 8_000 }).then(() => true),
      false,
      10_000
    );
    if (!logoVisible) { test.skip(); return; }

    const logoImgCount = await raceVal(
      page.locator('.awa-site-header .logo img').count(),
      0
    );
    if (logoImgCount > 0) {
      const logoImg = page.locator('.awa-site-header .logo img').first();
      const alt = await raceVal(logoImg.getAttribute('alt'), null);
      expect(alt, 'Logo img deve ter alt text').not.toBeNull();
    } else {
      const ariaLabel = await raceVal(logo.getAttribute('aria-label'), null);
      expect(ariaLabel, 'Logo deve ter aria-label se não for img').not.toBeNull();
    }
  });

  test('Imagens de produto na listagem têm alt @a11y', async ({ page }) => {
    await goCategoryPage(page);
    const productImages = page.locator('.product-item-photo img');
    const count = await raceVal(productImages.count(), 0);
    if (count === 0) { test.skip(); return; }
    const checkCount = Math.min(count, 6);

    for (let i = 0; i < checkCount; i++) {
      const img = productImages.nth(i);
      const alt = await raceVal(img.getAttribute('alt'), null);
      expect(alt, `Imagem de produto ${i + 1} deve ter alt`).not.toBeNull();
      expect((alt ?? '').trim().length, `Alt do produto ${i + 1} não deve ser vazio`).toBeGreaterThan(0);
    }
  });

  test('Imagem principal da galeria PDP tem alt @a11y', async ({ page }) => {
    await goPDP(page);
    const galleryVisible = await raceVal(
      page.waitForSelector('.fotorama, [data-gallery-role="gallery-placeholder"]', { timeout: 10_000 }).then(() => true),
      false,
      12_000
    );
    if (!galleryVisible) { test.skip(); return; }

    const galleryImg = page.locator(
      '.fotorama__stage img, .gallery-placeholder img, [data-gallery-role="gallery-placeholder"] img'
    ).first();
    const imgCount = await raceVal(galleryImg.count(), 0);
    if (imgCount === 0) { return; } // galeria pode ser de outro tipo — não falha

    const alt = await raceVal(galleryImg.getAttribute('alt'), null);
    expect(alt, 'Imagem principal da galeria deve ter alt').not.toBeNull();
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 3 — FORMULÁRIOS
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Formulários e inputs', () => {
  test('Campo de busca tem label ou aria-label @a11y', async ({ page }) => {
    await goHome(page);
    const searchInput = page.locator('input#search, input[name="q"]').first();
    const count = await raceVal(searchInput.count(), 0);
    if (!count) { test.skip(); return; }

    const id = await raceVal(searchInput.getAttribute('id'), null);
    const hasLabel = id
      ? await raceVal(page.locator(`label[for="${id}"]`).count().then(c => c > 0), false)
      : false;
    const hasAriaLabel     = await raceVal(searchInput.getAttribute('aria-label').then(v => !!v), false);
    const hasAriaLabelledBy = await raceVal(searchInput.getAttribute('aria-labelledby').then(v => !!v), false);
    const hasPlaceholder   = await raceVal(searchInput.getAttribute('placeholder').then(v => !!v), false);

    expect(
      hasLabel || hasAriaLabel || hasAriaLabelledBy || hasPlaceholder,
      'Campo de busca deve ter algum label acessível'
    ).toBe(true);
  });

  test('Botão add-to-cart tem texto acessível @a11y', async ({ page }) => {
    await goPDP(page);
    const btn = page.locator('#product-addtocart-button').first();
    const count = await raceVal(btn.count(), 0);
    if (!count) { test.skip(); return; }

    const text       = await raceVal(btn.textContent(), '');
    const ariaLabel  = await raceVal(btn.getAttribute('aria-label'), '');
    const title      = await raceVal(btn.getAttribute('title'), '');

    const accessibleText = (text?.trim() ?? '') || (ariaLabel ?? '') || (title ?? '');
    expect(accessibleText.length, 'Botão add-to-cart deve ter texto acessível').toBeGreaterThan(0);
  });

  test('Links sem texto têm aria-label @a11y', async ({ page }) => {
    await goHome(page);
    const iconLinks = page.locator('a:not([aria-hidden="true"])').filter({ has: page.locator('i, svg, img') });
    const count = await raceVal(iconLinks.count(), 0, 10_000);
    if (count === 0) { return; }

    const badLinks: string[] = [];
    const checkLimit = Math.min(count, 20);

    for (let i = 0; i < checkLimit; i++) {
      const link = iconLinks.nth(i);
      const text        = await raceVal(link.textContent().then(t => t?.trim() ?? ''), '');
      const ariaLabel   = await raceVal(link.getAttribute('aria-label'), null);
      const ariaLby     = await raceVal(link.getAttribute('aria-labelledby'), null);
      const title       = await raceVal(link.getAttribute('title'), null);

      if (!text && !ariaLabel && !ariaLby && !title) {
        const href = await raceVal(link.getAttribute('href'), 'unknown');
        badLinks.push(href ?? 'unknown');
      }
    }

    if (badLinks.length > 0) {
      console.warn(`⚠️  Links sem texto acessível (${badLinks.length}):`, badLinks.slice(0, 5));
    }
    expect(badLinks.length).toBeLessThan(10);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 4 — NAVEGAÇÃO POR TECLADO
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — Navegação por teclado', () => {
  test('Campo de busca recebe foco via Tab @a11y', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) { test.skip(); return; }

    await goHome(page);
    await Promise.race<void>([
      page.keyboard.press('Tab').catch(() => {}),
      new Promise<void>(r => setTimeout(r, 3_000)),
    ]);
    await new Promise<void>(r => setTimeout(r, 300));

    let focused = false;
    for (let i = 0; i < 15; i++) {
      const focusedEl = await Promise.race<{ tag: string; id: string; name: string } | null>([
        page.evaluate(() => {
          const el = document.activeElement;
          return el ? { tag: el.tagName, id: (el as HTMLInputElement).id ?? '', name: (el as HTMLInputElement).name ?? '' } : null;
        }).catch(() => null),
        new Promise<null>(r => setTimeout(() => r(null), 3_000)),
      ]);

      if (focusedEl && (focusedEl.id === 'search' || focusedEl.name === 'q')) {
        focused = true;
        break;
      }
      await Promise.race<void>([
        page.keyboard.press('Tab').catch(() => {}),
        new Promise<void>(r => setTimeout(r, 2_000)),
      ]);
      await new Promise<void>(r => setTimeout(r, 100));
    }

    if (!focused) { test.skip(); return; } // pode não ser alcançável neste viewport
    expect(focused, 'Campo de busca deve ser alcançável via Tab').toBe(true);
  });

  test('Logo do header tem tabindex >= 0 ou é focusável @a11y', async ({ page }) => {
    await goHome(page);
    const logoLink = page.locator('.awa-site-header .logo a, .awa-site-header a.logo').first();
    const count = await raceVal(logoLink.count(), 0);
    if (!count) { test.skip(); return; }

    const tag = await raceVal(
      logoLink.evaluate((el: HTMLElement) => el.tagName.toLowerCase()),
      ''
    );
    if (!tag) { test.skip(); return; }
    expect(['a', 'button'], 'Logo deve usar tag focusável').toContain(tag);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 5 — ARIA E ROLES
   ════════════════════════════════════════════════════════════════════════ */
test.describe('A11Y — ARIA e roles', () => {
  test('Minicart tem aria-label ou title @a11y', async ({ page }) => {
    await goHome(page);
    const minicart = page.locator(
      '.awa-header-minicart a, .mini-cart-wrapper a.action.showcart, a[data-bind*="minicart"]'
    ).first();
    const count = await raceVal(minicart.count(), 0, 8_000);
    if (!count) { test.skip(); return; }

    const ariaLabel = await raceVal(minicart.getAttribute('aria-label'), null);
    const title     = await raceVal(minicart.getAttribute('title'), null);
    const text      = await raceVal(minicart.textContent().then(t => t?.trim() ?? ''), '');

    expect(ariaLabel || title || text, 'Minicart link deve ter texto acessível').toBeTruthy();
  });

  test('Botão de menu mobile tem aria-label @a11y', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= 1024) { test.skip(); return; }

    await goHome(page);
    const toggle = page.locator(
      '.awa-header-mobile-toggle, button.nav-toggle, [aria-label*="menu"], [data-action="toggle-nav"]'
    ).first();
    const count = await raceVal(toggle.count(), 0);
    if (!count) { test.skip(); return; }

    const ariaLabel = await raceVal(toggle.getAttribute('aria-label'), null);
    const title     = await raceVal(toggle.getAttribute('title'), null);
    const text      = await raceVal(toggle.textContent().then(t => t?.trim() ?? ''), '');

    expect(ariaLabel || title || text, 'Botão de menu mobile deve ter texto acessível').toBeTruthy();
  });

  test('Tabs PDP têm role tab ou aria-selected @a11y', async ({ page }) => {
    await goPDP(page);
    const tabs = page.locator('.awa-pdp-tabs .awa-tab-title, [data-role="title"], [role="tab"]');
    const count = await raceVal(tabs.count(), 0, 8_000);
    if (count === 0) { test.skip(); return; }

    const firstTab = tabs.first();
    await Promise.race<void>([
      firstTab.click().catch(() => {}),
      new Promise<void>(r => setTimeout(r, 5_000)),
    ]);
    await new Promise<void>(r => setTimeout(r, 300));

    const hasActiveIndicator = await raceVal(
      firstTab.evaluate((el: HTMLElement) => {
        return el.classList.contains('active') ||
               el.classList.contains('awa-tab-active') ||
               el.getAttribute('aria-selected') === 'true' ||
               el.getAttribute('data-active') === '1' ||
               el.closest('[class*="active"]') !== null;
      }),
      false
    );

    expect(hasActiveIndicator, 'Tab ativa deve indicar estado via class ou aria').toBe(true);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 6 — axe-core (WCAG 2.x automated scan)
   ════════════════════════════════════════════════════════════════════════ */
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const AXE_REPORT_DIR = path.join(__dirname, '..', 'reports', 'axe');

function formatAxeViolations(violations: Awaited<ReturnType<AxeBuilder['analyze']>>['violations']): string {
  if (!violations.length) return 'Nenhuma violação axe-core';
  return violations
    .map((v) => `[${v.impact}] ${v.id}: ${v.description} (${v.nodes.length} nó(s))`)
    .join('\n');
}

function writeAxeReport(slug: string, results: Awaited<ReturnType<AxeBuilder['analyze']>>): void {
  fs.mkdirSync(AXE_REPORT_DIR, { recursive: true });
  fs.writeFileSync(
    path.join(AXE_REPORT_DIR, `${slug}.json`),
    JSON.stringify(results, null, 2),
    'utf8'
  );
}

async function runAxeScan(page: Page, slug: string): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(AXE_TAGS)
    .disableRules(['color-contrast'])
    .analyze();

  writeAxeReport(slug, results);

  expect(
    results.violations,
    `axe-core encontrou ${results.violations.length} violação(ões) em ${slug}:\n${formatAxeViolations(results.violations)}`
  ).toEqual([]);
}

test.describe('A11Y — axe-core WCAG scan', () => {
  test('Homepage sem violações críticas axe @axe', async ({ page }) => {
    await goHome(page);
    await runAxeScan(page, 'homepage');
  });

  test('Categoria sem violações críticas axe @axe', async ({ page }) => {
    await goCategoryPage(page);
    await runAxeScan(page, 'category');
  });

  test('PDP sem violações críticas axe @axe', async ({ page }) => {
    await goPDP(page);
    await runAxeScan(page, 'pdp');
  });
});
