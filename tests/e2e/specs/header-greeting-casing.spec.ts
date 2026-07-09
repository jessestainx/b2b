/**
 * REGRESSAO - Saudacao do header B2B nao deve exibir nome 100% em CAIXA ALTA
 *
 * Bug original: clientes importados do ERP com firstname/fullname/company
 * gravados em CAIXA ALTA (ex.: "FERNANDO", "JOSE PAVAO & CIA LTDA")
 * apareciam sem nenhuma normalizacao no header ("Ola, FERNANDO"), no
 * dropdown da conta ("FERNANDO JOSE PAVAO & CIA LTDA") e no dashboard B2B
 * ("Ola, FERNANDO!").
 *
 * Causa raiz: os tres pontos de renderizacao (StatusPanel.php,
 * B2bPanel.php + b2b-panel-hydrate.js, dashboard.phtml) usavam
 * getFirstname()/getFullname() puros, sem normalizacao de exibicao.
 *
 * Fix: GrupoAwamotos\B2B\Helper\Data::formatDisplayName() aplica title-case
 * apenas quando a string e 100% maiuscula (assinatura do import ERP),
 * preservando nomes ja digitados corretamente pelo cliente.
 *
 * Pre-requisitos (login real B2B - conta cujo nome esta gravado em CAIXA
 * ALTA no ERP; usa por padrao a conta reportada no bug):
 *   TEST_ALLCAPS_USER - CNPJ ou e-mail do cliente B2B com nome em CAIXA ALTA
 *   TEST_ALLCAPS_PASS - senha do cliente acima
 *
 * Execucao:
 *   cd tests/e2e
 *   ALLOW_PRODUCTION_VALIDATION=true playwright test specs/header-greeting-casing.spec.ts --project=notebook-1366
 *   ALLOW_PRODUCTION_VALIDATION=true playwright test specs/header-greeting-casing.spec.ts --project=mobile-390
 */
import { test, expect, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';

const TEST_EMAIL = process.env.TEST_ALLCAPS_USER ?? '66.618.406/0001-40';
const TEST_PASS = process.env.TEST_ALLCAPS_PASS ?? '123awa';

const LOGIN_URL = targetUrl('/b2b/account/login/', 'header-greeting-casing');
const HOME_URL = targetUrl('/', 'header-greeting-casing');
const DASHBOARD_URL = targetUrl('/b2b/account/dashboard/', 'header-greeting-casing');

/**
 * Uma string "grita" quando e inteiramente maiuscula e tem pelo menos uma
 * letra - e exatamente o artefato de import do ERP que o fix corrige.
 */
function isShouting(value: string): boolean {
  const letters = value.replace(/[^\p{L}]/gu, '');
  return letters.length > 0 && letters === letters.toUpperCase() && letters !== letters.toLowerCase();
}

async function loginB2B(page: Page): Promise<void> {
  await page.goto(LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
  await page.locator('#b2b-email').fill(TEST_EMAIL);
  await page.locator('#b2b-pass').fill(TEST_PASS);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {}),
    page.locator('#b2b-login-form button[type="submit"], .b2b-btn-entrar').first().click(),
  ]);
  await page.waitForTimeout(1500);

  const loggedIn = page.url().includes('/b2b/account/');
  expect(loggedIn, `Login B2B falhou com a conta de teste (${TEST_EMAIL}) - verifique TEST_ALLCAPS_USER/TEST_ALLCAPS_PASS`).toBe(true);
}

test.describe('Header B2B - capitalizacao da saudacao (anti-regressao "Ola, FERNANDO")', () => {
  test.describe.configure({ timeout: 60_000 });

  test('saudacao do header na home nao fica em CAIXA ALTA apos login', async ({ page }) => {
    await loginB2B(page);

    await page.goto(HOME_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    // Usamos 'attached' (nao 'visible') porque em mobile o gatilho do painel B2B
    // pode ficar oculto no viewport principal ate abrir o menu de conta — o que
    // importa aqui e o texto ja renderizado pelo servidor/JS, nao a visibilidade
    // responsiva (fora do escopo deste bug de capitalizacao).
    await page.waitForSelector('.b2b-status-trigger__line1', { state: 'attached', timeout: 15_000 });
    await page.waitForTimeout(1000);

    const greeting = await page.locator('.b2b-status-trigger__line1').first().textContent();
    expect(greeting, 'Saudacao do header deve existir').toBeTruthy();

    const name = (greeting ?? '').replace(/^Olá,\s*/i, '').trim();
    expect(
      isShouting(name),
      `Nome exibido no header nao deve estar 100% em CAIXA ALTA (recebido: "${greeting}")`,
    ).toBe(false);
  });

  test('nome no dropdown da conta nao fica em CAIXA ALTA', async ({ page }) => {
    await loginB2B(page);

    await page.goto(HOME_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    const trigger = page.locator('.b2b-status-trigger').first();
    await trigger.waitFor({ state: 'attached', timeout: 15_000 });
    // force:true — em mobile um badge do minicart pode sobrepor o gatilho;
    // o teste valida apenas o texto renderizado, nao a interacao de clique.
    await trigger.click({ force: true }).catch(() => {});

    const userName = page.locator('.b2b-status-dropdown .user-name').first();
    await userName.waitFor({ state: 'attached', timeout: 10_000 });

    const text = (await userName.textContent()) ?? '';
    expect(
      isShouting(text),
      `Nome no dropdown da conta nao deve estar 100% em CAIXA ALTA (recebido: "${text}")`,
    ).toBe(false);
  });

  test('saudacao do Painel B2B (dashboard) nao fica em CAIXA ALTA', async ({ page }) => {
    await loginB2B(page);

    await page.goto(DASHBOARD_URL, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    const welcome = page.locator('.b2b-welcome-sub').first();
    await expect(welcome).toBeVisible({ timeout: 15_000 });

    const text = (await welcome.textContent()) ?? '';
    const name = text.replace(/^Olá,\s*/i, '').replace(/!.*/s, '').trim();
    expect(
      isShouting(name),
      `Saudacao do Painel B2B nao deve estar 100% em CAIXA ALTA (recebido: "${text}")`,
    ).toBe(false);
  });
});
