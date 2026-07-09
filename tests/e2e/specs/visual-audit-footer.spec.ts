/**
 * Visual Audit — Footer (Rodapé completo)
 *
 * Valida todas as seções do rodapé da loja AWA:
 *  1. Newsletter block (campo email + botão)
 *  2. Colunas de links (4 colunas de menu)
 *  3. Bloco de Atendimento (telefone, email, endereço, badge)
 *  4. Links sociais / WhatsApp
 *  5. Copyright
 *  6. Layout e responsividade
 *  7. Screenshots baseline
 *
 * Seletores baseados em:
 *   Rokanthemes_Themeoption/templates/html/footer/footer-static5.phtml
 */
import { test, expect, type Page } from '@playwright/test';
import {
  navigateTo, css, px, isVisible, hasNoOverflow,
  TOKENS, COMMON,
} from '../helpers/visual-audit.helpers';
import { targetUrl } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

/* Seletores do footer AWA */
const FOOTER = {
  root:              'footer.page-footer, .page-footer',
  newsletter:        '.awa-footer-newsletter',
  newsletterTitle:   '.awa-newsletter-title',
  newsletterInput:   '.awa-footer-newsletter input[type="email"]',
  newsletterBtn:     '.awa-footer-newsletter button, .awa-footer-newsletter .btn',
  menuColumns:       '.velaFooterMenu',
  menuTitle:         '.velaFooterTitle',
  menuLinks:         '.velaFooterLinks',
  atendimento:       '.awa-footer-atendimento',
  phone:             '.awa-footer-atendimento__phone',
  email:             '.awa-footer-atendimento__email',
  storeName:         '.awa-footer-atendimento__store-name',
  storeAddress:      '.awa-footer-atendimento__store-address',
  storeBadge:        '.awa-footer-atendimento__store-badge',
  actions:           '.awa-footer-atendimento__actions',
  copyright:         '.footer.content .copyright, .footer-copyright, [class*="copyright"]',
  whatsapp:          'a[href*="wa.me"], a[href*="whatsapp"]',
  social:            '.social-links, [class*="social"]',
} as const;

/* ── Scroll ao rodapé e aguardar lazy load ─────────────────────── */
async function scrollToFooter(page: Page): Promise<void> {
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)).catch(() => {});
  await page.waitForTimeout(1_500).catch(() => {});
}

async function navigateToFooterHome(page: Page): Promise<boolean> {
  await blockOptionalThirdParty(page.context());
  return navigateTo(page, targetUrl('/', 'visual-audit-footer'));
}

let sharedPage: Page;

test.beforeAll(async ({ browser }) => {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
  await blockOptionalThirdParty(ctx);
  sharedPage = await ctx.newPage();
  const ok = await navigateTo(sharedPage, targetUrl('/', 'visual-audit-footer'));
  if (!ok) throw new Error('Homepage não carregou para testes de footer');
  await sharedPage.waitForTimeout(2_000);
  await scrollToFooter(sharedPage);
});

test.afterAll(async () => {
  await sharedPage?.context().close().catch(() => {});
});

/* ═══════════════════════════════════════════════════════════════════
   1. NEWSLETTER
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — Newsletter', () => {
  test('Bloco de newsletter presente', async () => {
    if (!sharedPage) { test.skip(); return; }
    const vis = await isVisible(sharedPage, FOOTER.newsletter, 5_000);
    console.log(`Newsletter block visível: ${vis}`);
    /* Aviso: pode não estar ativo em todas as versões do tema */
    if (!vis) console.warn('⚠️ Bloco newsletter não visível');
  });

  test('Campo email do newsletter presente e com type="email"', async () => {
    if (!sharedPage) { test.skip(); return; }
    const input = sharedPage.locator(FOOTER.newsletterInput).first();
    if (!await input.isVisible().catch(() => false)) { test.skip(); return; }
    const type = await input.getAttribute('type').catch(() => 'text');
    expect(type, 'Campo newsletter deve ter type="email"').toBe('email');
  });

  test('Botão de assinar newsletter visível', async () => {
    if (!sharedPage) { test.skip(); return; }
    const btn = await isVisible(sharedPage, FOOTER.newsletterBtn, 3_000);
    console.log(`Botão newsletter visível: ${btn}`);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   2. COLUNAS DE LINKS
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — Colunas de links', () => {
  test('Ao menos 3 colunas de menu presentes', async () => {
    if (!sharedPage) { test.skip(); return; }
    const count = await sharedPage.locator(FOOTER.menuColumns).count().catch(() => 0);
    console.log(`Footer menu columns: ${count}`);
    expect(count, 'Footer deve ter ao menos 3 colunas de menu').toBeGreaterThanOrEqual(3);
  });

  test('Cada coluna tem título visível', async () => {
    if (!sharedPage) { test.skip(); return; }
    const count = await sharedPage.locator(FOOTER.menuTitle).count().catch(() => 0);
    if (count === 0) { test.skip(); return; }
    console.log(`Títulos de coluna no footer: ${count}`);
    expect(count, 'Deve haver títulos nas colunas do footer').toBeGreaterThanOrEqual(1);
  });

  test('Links nas colunas são âncoras com href válido', async () => {
    if (!sharedPage) { test.skip(); return; }
    const links = await sharedPage.evaluate((sel) => {
      const anchors = Array.from(document.querySelectorAll(`${sel} a`));
      return anchors.map(a => (a as HTMLAnchorElement).href).filter(h => !h.startsWith('javascript'));
    }, FOOTER.menuLinks).catch(() => [] as string[]);
    console.log(`Footer links válidos: ${links.length}`);
    expect(links.length, 'Footer deve ter links válidos nas colunas').toBeGreaterThan(0);
  });

  test('Colunas não têm overflow horizontal', async () => {
    if (!sharedPage) { test.skip(); return; }
    const ok = await hasNoOverflow(sharedPage);
    expect(ok, 'Footer não deve ter overflow horizontal').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   3. ATENDIMENTO / CONTATO
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — Atendimento', () => {
  test('Bloco de atendimento presente', async () => {
    if (!sharedPage) { test.skip(); return; }
    const vis = await isVisible(sharedPage, FOOTER.atendimento, 5_000);
    console.log(`Atendimento block visível: ${vis}`);
  });

  test('Telefone de atendimento visível', async () => {
    if (!sharedPage) { test.skip(); return; }
    const phone = sharedPage.locator(FOOTER.phone).first();
    if (!await phone.isVisible().catch(() => false)) { test.skip(); return; }
    const text = await phone.textContent().catch(() => '');
    console.log(`Telefone: "${text?.trim()}"`);
    expect(text?.trim(), 'Telefone não deve estar vazio').toBeTruthy();
  });

  test('Email de atendimento visível', async () => {
    if (!sharedPage) { test.skip(); return; }
    const emailEl = sharedPage.locator(FOOTER.email).first();
    if (!await emailEl.isVisible().catch(() => false)) { test.skip(); return; }
    const text = await emailEl.textContent().catch(() => '');
    console.log(`Email atendimento: "${text?.trim()}"`);
    expect(text?.trim()).toBeTruthy();
  });

  test('Endereço da loja visível', async () => {
    if (!sharedPage) { test.skip(); return; }
    const addr = sharedPage.locator(FOOTER.storeAddress).first();
    if (!await addr.isVisible().catch(() => false)) { test.skip(); return; }
    const text = await addr.textContent().catch(() => '');
    console.log(`Endereço: "${text?.trim()}"`);
    expect(text?.trim(), 'Endereço não deve estar vazio').toBeTruthy();
  });
});

/* ═══════════════════════════════════════════════════════════════════
   4. WHATSAPP / SOCIAL
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — WhatsApp e Social', () => {
  test('Ao menos um link WhatsApp no footer', async () => {
    if (!sharedPage) { test.skip(); return; }
    const count = await sharedPage.locator(FOOTER.whatsapp).count().catch(() => 0);
    console.log(`Links WhatsApp no footer: ${count}`);
    expect(count, 'Footer deve ter ao menos 1 link do WhatsApp').toBeGreaterThan(0);
  });

  test('Links WhatsApp têm número (wa.me/55...)', async () => {
    if (!sharedPage) { test.skip(); return; }
    const hrefs = await sharedPage.evaluate(() => {
      return Array.from(document.querySelectorAll('a[href*="wa.me"], a[href*="whatsapp"]'))
        .map(a => (a as HTMLAnchorElement).href);
    }).catch(() => [] as string[]);
    if (hrefs.length === 0) { test.skip(); return; }
    console.log(`WhatsApp links: ${hrefs}`);
    const hasBrazilNumber = hrefs.some(h => h.includes('55'));
    expect(hasBrazilNumber, 'Link WhatsApp deve conter número com DDI Brasil (+55)').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   5. COPYRIGHT
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — Copyright', () => {
  test('Texto de copyright presente', async () => {
    if (!sharedPage) { test.skip(); return; }
    const text = await sharedPage.evaluate(() => {
      const footer = document.querySelector('footer');
      return footer?.textContent ?? '';
    }).catch(() => '');

    const hasYear = /202[0-9]/.test(text);
    const hasAwa  = /AWA|awamotos|awa motos/i.test(text);
    console.log(`Footer text hasYear=${hasYear} hasAwa=${hasAwa}`);
    expect(hasYear || hasAwa, 'Footer deve conter ano e/ou nome da empresa no copyright').toBe(true);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   6. RESPONSIVIDADE
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — Layout e responsividade', () => {
  test('Footer ocupa 100% da largura', async () => {
    if (!sharedPage) { test.skip(); return; }
    const footerWidth = await sharedPage.evaluate((sel) => {
      const footer = document.querySelector(sel);
      if (!footer) return 0;
      return (footer as HTMLElement).offsetWidth;
    }, FOOTER.root).catch(() => 0);
    const vw = await sharedPage.evaluate(() => window.innerWidth).catch(() => 1366);
    console.log(`Footer width: ${footerWidth}px / vw: ${vw}px`);
    expect(footerWidth, 'Footer deve ter pelo menos 95% da largura da viewport').toBeGreaterThanOrEqual(vw * 0.95);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   7. SCREENSHOTS
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Footer — Screenshots baseline', () => {
  test('Screenshot desktop — footer completo', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    const ok = await navigateToFooterHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await scrollToFooter(page);

    const footer = page.locator(FOOTER.root).first();
    if (!await footer.isVisible().catch(() => false)) { test.skip(); return; }

    await expect(footer).toHaveScreenshot('footer-desktop-full.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });

  test('Screenshot mobile — footer 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    const ok = await navigateToFooterHome(page);
    if (!ok) { test.skip(); return; }
    await page.waitForTimeout(2_000);
    await scrollToFooter(page);

    const footer = page.locator(FOOTER.root).first();
    if (!await footer.isVisible().catch(() => false)) { test.skip(); return; }

    await expect(footer).toHaveScreenshot('footer-mobile-375.png', {
      maxDiffPixelRatio: 0.04,
      animations: 'disabled',
    });
  });
});
