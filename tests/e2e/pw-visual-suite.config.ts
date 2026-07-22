/**
 * pw-visual-suite.config.ts — AWA Motos
 * Config para suíte visual de regressão das páginas-chave.
 *
 * Rodar:              npm run test:visual-suite
 * Gerar baseline:     npm run test:visual-suite:update-baseline
 *
 * NOTA: Chrome trava neste servidor — usar Firefox exclusivamente.
 * Snapshots co-localizados em specs/visual/visual-suite.spec.ts-snapshots/
 * (fora do gitignore que cobre apenas snapshots/).
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
  if (host.includes(['awa','motos','.com'].join('')) || host === '72.61.94.22') {
    throw new Error(`[${cfg}] URL de producao bloqueada (NO-GO Fase 1)`);
  }
  if (String(process.env.ALLOW_PRODUCTION_VALIDATION || '').toLowerCase() === 'true') {
    throw new Error(`[${cfg}] ALLOW_PRODUCTION_VALIDATION rejeitado na Fase 1`);
  }
  return raw.replace(/\/$/, '');
}

export default defineConfig({
  testDir:   path.join(__dirname, 'specs/visual'),
  testMatch: /visual-suite\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results/visual-suite'),
  fullyParallel: false,
  workers:  1,
  retries:  1,
  timeout:  60_000,
  expect: {
    timeout:            10_000,
    toHaveScreenshot: {
      maxDiffPixelRatio: 0.04,
      animations:       'disabled',
    },
  },
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(__dirname, 'reports/visual-suite-html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/visual-suite-results.json') }],
  ],
  use: {
    baseURL: resolveCiBaseUrl('pw-visual-suite.config.ts'),
    ignoreHTTPSErrors:  true,
    locale:             'pt-BR',
    timezoneId:         'America/Sao_Paulo',
    screenshot:         'on',
    video:              'retain-on-failure',
    trace:              'retain-on-failure',
    actionTimeout:      10_000,
    navigationTimeout:  30_000,
  },
  projects: [
    {
      name: 'visual-desktop-1280',
      use: {
        ...devices['Desktop Firefox'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'visual-mobile-375',
      use: {
        browserName:  'firefox',
        viewport:     { width: 375, height: 667 },
        userAgent:    'Mozilla/5.0 (Android 11; Mobile; rv:109.0) Gecko/109.0 Firefox/109.0',
      },
    },
  ],
});
