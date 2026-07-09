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
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-visual-suite');

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
    baseURL:            resolvedBaseUrl,
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
