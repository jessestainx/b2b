import { defineConfig, devices } from './tests/e2e/node_modules/@playwright/test';
import path from 'path';
import { resolveBaseUrl } from './tests/e2e/helpers/resolve-base-url';

const safeModeEnabled = process.env.PW_SAFE_MODE !== '0';
const defaultWorkers = safeModeEnabled ? 1 : 2;
const configuredWorkers = Number(process.env.PW_WORKERS ?? (process.env.CI ? 1 : defaultWorkers));

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './tests/e2e/specs',
  outputDir: './tests/e2e/test-results-root',
  /* Magento não deve receber suíte visual paralela por padrão. */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: configuredWorkers,
  timeout: process.env.CI ? 60_000 : 120_000,
  expect: {
    timeout: 8_000,
  },
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['list'],
    ['html', { outputFolder: path.join('tests', 'e2e', 'reports', 'root-html'), open: 'never' }],
  ],
  snapshotPathTemplate: '{testDir}/snapshots/{testFilePath}/{arg}{ext}',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    baseURL: resolveBaseUrl('root-playwright'),

    /* Salva evidências automáticas para depuração de falhas. */
    screenshot: 'only-on-failure',
    video: process.env.PW_VIDEO === '1' ? 'retain-on-failure' : 'off',
    trace: process.env.PW_TRACE === '1' ? 'on-first-retry' : 'off',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'desktop-1440',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 1000 },
      },
    },
    {
      name: 'notebook-1280',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
      },
    },
    {
      name: 'mobile-390',
      /*
       * PD6B (test/pd6b-mobile-search-ci-stabilization): devices['iPhone 14']
       * define defaultBrowserType: 'webkit' - este projeto SEMPRE rodou WebKit,
       * nao Chromium (confirmado via testInfo.project + navigator.userAgent em
       * runtime real). O crash do Chromium headless mobile+touch documentado
       * em PD5/PD6 (ver PD5_MOBILE_SEARCH_HEADLESS_CRASH_INVESTIGATION.md e
       * PD6_SEARCH_INTENT_BOOTSTRAP_PERFORMANCE_FIX.md) NUNCA afetou este
       * projeto - so foi reproduzido via scripts standalone que chamavam
       * chromium.launch() diretamente, fora do test runner.
       *
       * O problema real encontrado na PD6B foi outro: o teste "design QA -
       * home" (product-design-qa.spec.ts) roda ~5 screenshots full-page numa
       * pagina pesada (~5000px de altura renderizada) + varias coletas de
       * evidencia de DOM + um polling de ate 6s para o autocomplete - no
       * WebKit, isso mediu ~2.2min numa execucao limpa (sem crash, autocomplete
       * abriu corretamente: opened:true), excedendo o timeout global de 120s
       * (60s em CI) e falhando por TIMEOUT, nao por bug de produto/harness.
       * Timeout aumentado especificamente para este projeto para acomodar o
       * custo real medido, com margem de seguranca.
       */
      timeout: 240_000,
      use: {
        ...devices['iPhone 14'],
        viewport: { width: 390, height: 844 },
      },
    },
    {
      /**
       * PD6B: projeto explícito Chromium mobile-touch para observabilidade.
       *
       * Este projeto NÃO substitui o `mobile-390` (WebKit) como validação
       * estável/gating de autocomplete em CI. Ele existe para monitorar a
       * fragilidade histórica do Chromium headless em touch sob carga de JS
       * (investigado em PD5/PD6) sem bloquear entrega de PRs.
       */
      name: 'mobile-390-chromium',
      timeout: 240_000,
      use: {
        browserName: 'chromium',
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        deviceScaleFactor: 3,
      },
    },
  ],
});
