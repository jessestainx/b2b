/**
 * Playwright Config — AWA Motos Visual Audit Tests (8 Fases)
 * Desktop (1280px) + Mobile (375px)
 */
import { defineConfig, devices } from '@playwright/test';
import path from 'path';

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
  testDir: path.join(__dirname, 'specs'),
  testMatch: /(?:visual-audit-.*|layout-container-grid)\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results'),

  timeout: 120_000,
  expect: { timeout: 10_000 },

  fullyParallel: false,
  retries: 1,
  workers: 1,

  globalSetup: path.join(__dirname, 'helpers/global-setup.ts'),
  globalTeardown: path.join(__dirname, 'helpers/global-teardown.ts'),

  reporter: [
    ['list'],
    ['json', { outputFile: path.join(__dirname, 'reports/visual-audit-results.json') }],
  ],

  use: {
    baseURL: resolveCiBaseUrl('pw-visual-audit.config.ts'),
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    launchOptions: {
      args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-setuid-sandbox', '--disable-gpu'],
    },
  },

  projects: [
    {
      name: 'desktop-1280',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'mobile-375',
      use: {
        ...devices['Pixel 5'],
        viewport: { width: 375, height: 667 },
      },
    },
  ],
});
