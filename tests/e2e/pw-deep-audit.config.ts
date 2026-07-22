import { defineConfig, devices } from '@playwright/test';
import path from 'path';

/**
 * Resolve e valida a BASE_URL obrigatória.
 * Falha se: ausente, inválida ou produção sem flag explícita.
 */
function resolveAndValidateBaseUrl(): string {
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || '';
  const allowProd = false; // Fase 1 NO-GO: flag unica insuficiente

  if (!raw) {
    throw new Error(
      '[pw-deep-audit] BASE_URL ausente. ' +
      'Defina PLAYWRIGHT_BASE_URL ou BASE_URL antes de rodar este config.\n' +
      'Exemplo: PLAYWRIGHT_BASE_URL=https://staging.exemplo.com npx playwright test'
    );
  }

  let parsed: URL;
  try {
    parsed = new URL(raw);
  } catch {
    throw new Error(`[pw-deep-audit] BASE_URL inválida: "${raw}". Informe uma URL http/https completa.`);
  }

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    throw new Error(`[pw-deep-audit] Protocolo não permitido: "${parsed.protocol}". Use http ou https.`);
  }

  const host = parsed.hostname.toLowerCase();
  if ((host.includes(["awa","motos",".com"].join("")) || host === ["awa","motos",".com"].join("")) && !allowProd) {
    throw new Error(
      '[pw-deep-audit] URL de produção bloqueada por segurança.\n' +
      'Para usar produção explicitamente, defina: ALLOW_PRODUCTION_VALIDATION(disabled)\n' +
      'URL recebida: ' + host
    );
  }

  const safeLog = `${parsed.protocol}//${parsed.hostname}${parsed.port ? ':' + parsed.port : ''}`;
  if (host.includes(["awa","motos",".com"].join(""))) {
    console.warn(`[pw-deep-audit] ATENÇÃO: Produção permitida (ALLOW_PRODUCTION_VALIDATION(disabled)). URL: ${safeLog}`);
  } else {
    console.log(`[pw-deep-audit] BASE_URL validada: ${safeLog}`);
  }

  return parsed.toString().replace(/\/$/, '');
}

const resolvedBaseUrl = resolveAndValidateBaseUrl();

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /(visual-deep-audit\.spec\.ts|\/(smoke|deep-visual)\/.*\.spec\.ts$)/,
  outputDir: path.join(__dirname, 'test-results/deep-audit'),

  timeout: 240_000,
  expect: { timeout: 15_000 },

  fullyParallel: false,
  retries: 1,
  workers: 1,

  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(__dirname, 'reports/deep-audit-html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/deep-audit-results.json') }],
  ],

  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 90_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },

  projects: [
    {
      name: 'firefox-desktop-1366',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1366, height: 900 },
      },
    },
    {
      name: 'firefox-desktop-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'firefox-mobile-390',
      use: {
        browserName: 'firefox',
        viewport: { width: 390, height: 844 },
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        trace: 'off',
        video: 'off',
        screenshot: 'only-on-failure',
      },
    },
  ],
});
