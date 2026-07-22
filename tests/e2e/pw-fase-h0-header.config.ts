/**
 * pw-fase-h0-header.config.ts — AWA Motos
 *
 * Config dedicado a Fase H0 — Header Functional QA.
 * Cobre os 6 breakpoints obrigatorios da fase (ver docs/visual-qa/fase-h0-header-inventario-2026-07-08.md).
 * Read-only: apenas inspeciona/reproduz comportamento, nenhuma alteracao de codigo/produto.
 *
 * Browser: Chromium headless com --no-sandbox/--disable-dev-shm-usage.
 * NOTA (2026-07-08): tentativa inicial com Firefox (padrao usado em pw-functional.config.ts)
 * apresentou crash fatal do PROCESSO do runner Playwright (nao apenas da pagina) durante
 * a Fase H0 nesta VPS — ver docs/visual-qa/fase-h0-header-bug-report-2026-07-08.md,
 * secao "Instabilidade de ambiente". Chromium com estas flags se mostrou mais resiliente
 * (falhas isoladas por teste, sem derrubar o worker inteiro).
 *
 * Uso:
 *   cd tests/e2e
 *     playwright test --config=pw-fase-h0-header.config.ts
 *
 *   # Um breakpoint especifico:
 *     playwright test --config=pw-fase-h0-header.config.ts --project=h0-1366
 */
import path from 'path';
import { defineConfig, devices } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-fase-h0-header');

const BREAKPOINTS: Array<{ name: string; width: number; height: number }> = [
  { name: 'h0-1440', width: 1440, height: 900 },
  { name: 'h0-1366', width: 1366, height: 768 },
  { name: 'h0-1024', width: 1024, height: 900 },
  { name: 'h0-768', width: 768, height: 1024 },
  { name: 'h0-430', width: 430, height: 932 },
  { name: 'h0-360', width: 360, height: 800 },
];

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /fase-h0-header-functional-qa\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results/fase-h0-header'),
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(__dirname, 'reports/fase-h0-header-html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/fase-h0-header-results.json') }],
  ],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
  },
  projects: BREAKPOINTS.map((bp) => ({
    name: bp.name,
    use: {
      browserName: 'chromium' as const,
      viewport: { width: bp.width, height: bp.height },
      launchOptions: {
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
      },
      ...(bp.width <= 430
        ? { userAgent: 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36' }
        : {}),
    },
  })),
});
