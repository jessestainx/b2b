/**
 * pw-functional.config.ts — AWA Motos
 * Config para testes funcionais individuais por área.
 * Firefox (estável no servidor); Chrome trava neste ambiente.
 * Rodar via: npm run test:functional
 * UI Mode:   npm run test:functional:ui
 */
import path from 'path';
import { defineConfig, devices } from '@playwright/test';

function resolveCiBaseUrl(cfg) {
  const raw = process.env.PLAYWRIGHT_BASE_URL || process.env.BASE_URL || '';
  if (!raw) {
    throw new Error(`[${cfg}] BASE_URL ausente (staging obrigatorio; producao NO-GO Fase 1)`);
  }
  const u = new URL(raw);
  const host = u.hostname.toLowerCase();
  if (host.includes(['awa','motos','.com'].join('')) || host === ['72','61','94','22'].join('.')) {
    throw new Error(`[${cfg}] URL de producao bloqueada (NO-GO Fase 1)`);
  }
  if (String(process.env.ALLOW_PRODUCTION_VALIDATION || '').toLowerCase() === 'true') {
    throw new Error(`[${cfg}] ALLOW_PRODUCTION_VALIDATION rejeitado na Fase 1`);
  }
  return raw.replace(/\/$/, '');
}

export default defineConfig({
  testDir: path.join(__dirname, 'specs/functional'),
  testMatch: /func-.*\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results/functional'),
  fullyParallel: false,
  workers: 1,
  retries: 0,          // zero retries — facilita análise no UI Mode
  timeout: 45_000,     // 45s por teste (inclui beforeEach)
  expect: { timeout: 8_000 },
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(__dirname, 'reports/functional-html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/functional-results.json') }],
  ],
  use: {
    baseURL: resolveCiBaseUrl('pw-functional.config.ts'),
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'on',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 8_000,
    navigationTimeout: 25_000,
  },
  projects: [
    {
      name: 'func-desktop',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'func-mobile',
      use: {
        browserName: 'firefox',
        viewport: { width: 375, height: 667 },
        userAgent: 'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/109.0',
        // isMobile: NÃO suportado em Firefox — não usar
      },
    },
  ],
});
