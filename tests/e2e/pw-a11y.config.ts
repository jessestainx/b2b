import { defineConfig, devices } from '@playwright/test';
import path from 'path';

function resolveAndValidateBaseUrl(): string {
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || '';
  const allowProd = false; // Fase 1 NO-GO: flag unica insuficiente

  if (!raw) {
    throw new Error(
      '[pw-a11y] BASE_URL ausente. Defina PLAYWRIGHT_BASE_URL ou BASE_URL antes de rodar este config.'
    );
  }

  let parsed: URL;
  try {
    parsed = new URL(raw);
  } catch {
    throw new Error(`[pw-a11y] BASE_URL inválida: "${raw}". Informe uma URL http/https completa.`);
  }

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    throw new Error(`[pw-a11y] Protocolo não permitido: "${parsed.protocol}". Use http ou https.`);
  }

  const host = parsed.hostname.toLowerCase();
  if ((host.includes(["awa","motos",".com"].join("")) || host === ["awa","motos",".com"].join("")) && !allowProd) {
    throw new Error(
      '[pw-a11y] URL de produção bloqueada. Defina ALLOW_PRODUCTION_VALIDATION(disabled) para usar produção.'
    );
  }

  return parsed.toString().replace(/\/$/, '');
}

const resolvedBaseUrl = resolveAndValidateBaseUrl();

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /accessibility\.spec\.ts$/,
  outputDir: path.join(__dirname, 'test-results/a11y'),

  timeout: 120_000,
  expect: { timeout: 15_000 },

  fullyParallel: false,
  retries: 0,
  workers: 1,

  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/a11y-results.json') }],
    ['html', { outputFolder: path.join(__dirname, 'reports/a11y-html'), open: 'never' }],
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
  ],
});
