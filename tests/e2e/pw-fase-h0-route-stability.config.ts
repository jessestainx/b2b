/**
 * pw-fase-h0-route-stability.config.ts — AWA Motos
 *
 * Config dedicado a Fase H0.1 — Route Stability Investigation.
 * Investiga BUG-H0-CRIT-01 (/catalogo nunca atinge 'load') e BUG-H0-CRIT-02
 * (PDP nunca atinge 'domcontentloaded') documentados em
 * docs/visual-qa/fase-h0-header-bug-report-2026-07-08.md.
 *
 * Read-only. Nenhuma alteracao de tema/produto. Apenas instrumentacao de teste.
 *
 * Desktop 1440 apenas nesta subfase (Tarefa 4.G) — nao rodar outros breakpoints aqui.
 *
 * Trace SEMPRE ligado (nao apenas em falha) — a Tarefa 3 exige trace.zip como
 * evidencia de runtime mesmo quando um teste "passa" tecnicamente (ex.: quando
 * commit/domcontentloaded funcionam mas load nunca dispara e isso e esperado
 * e documentado, nao necessariamente uma falha do `expect`).
 *
 * Uso (headless, padrao):
 *   cd tests/e2e
 *     npx playwright test --config=pw-fase-h0-route-stability.config.ts --project=h0-route-stability-1440-headless
 *
 * Uso (headed via Xvfb — Tarefa 4.F):
 *   cd tests/e2e
 *     xvfb-run -a npx playwright test --config=pw-fase-h0-route-stability.config.ts --project=h0-route-stability-1440-headed
 */
import path from 'path';
import { defineConfig } from '@playwright/test';
import { resolveBaseUrl } from './helpers/resolve-base-url';

const resolvedBaseUrl = resolveBaseUrl('pw-fase-h0-route-stability');

const CHROMIUM_STABILITY_ARGS = ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'];

export default defineConfig({
  testDir: path.join(__dirname, 'specs'),
  testMatch: /fase-h0-route-stability-(catalogo|pdp)\.spec\.ts/,
  outputDir: path.join(__dirname, 'test-results/fase-h0-route-stability'),
  fullyParallel: false,
  workers: 1,
  retries: 0, // investigacao: queremos o comportamento real, nao uma retentativa mascarando instabilidade
  timeout: 180_000, // cada teste roda 3 tentativas (commit 10s + domcontentloaded 30s + load 60s) + overhead
  expect: { timeout: 10_000 },
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join(__dirname, 'reports/fase-h0-route-stability-html'), open: 'never' }],
    ['json', { outputFile: path.join(__dirname, 'reports/fase-h0-route-stability-results.json') }],
  ],
  use: {
    baseURL: resolvedBaseUrl,
    ignoreHTTPSErrors: true,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    viewport: { width: 1440, height: 900 },
    trace: 'on',
    screenshot: 'off', // specs capturam screenshots manualmente no momento exato do timeout
    video: 'off',
  },
  projects: [
    {
      name: 'h0-route-stability-1440-headless',
      use: {
        browserName: 'chromium',
        headless: true,
        launchOptions: { args: CHROMIUM_STABILITY_ARGS },
      },
    },
    {
      name: 'h0-route-stability-1440-headed',
      use: {
        browserName: 'chromium',
        headless: false,
        launchOptions: { args: CHROMIUM_STABILITY_ARGS },
      },
    },
  ],
});
