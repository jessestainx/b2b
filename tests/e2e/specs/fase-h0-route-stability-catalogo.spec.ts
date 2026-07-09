/**
 * Fase H0.1 — Route Stability Investigation — /catalogo
 * ========================================================
 * Investiga BUG-H0-CRIT-01 (ver docs/visual-qa/fase-h0-header-bug-report-2026-07-08.md):
 * `/catalogo` "trava o renderer ou nunca dispara load".
 *
 * Regras desta subfase:
 *  - Read-only. Nenhuma correcao aplicada.
 *  - Nao testa componentes do header — apenas estabilidade da navegacao/rede.
 *  - Nao mistura com Fase 6A (dead CSS) nem altera tema/CSS/LESS/JS/templates/checkout.
 *  - Se um evento (commit/domcontentloaded/load) nao ocorrer, isso e registrado
 *    como evidencia de bug, nunca mascarado com retry silencioso ou try/catch vazio.
 *
 * Cada teste = 1 variacao de isolamento (Tarefa 4, A-E). Dentro de cada teste,
 * 3 tentativas de navegacao independentes (commit/domcontentloaded/load), cada
 * uma em contexto limpo (novo browser context) para nao carregar estado da
 * tentativa anterior — variacao A tambem cobre isso por definicao ("sem storage
 * anterior"), mas aplicamos a todas por consistencia e para isolar causa raiz.
 *
 * F (headed/headless) e G (viewport 1440) sao controlados pelo project do
 * Playwright (pw-fase-h0-route-stability.config.ts), nao por este arquivo.
 *
 * Execucao:
 *   cd tests/e2e
 *   ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
 *     npx playwright test --config=pw-fase-h0-route-stability.config.ts \
 *     specs/fase-h0-route-stability-catalogo.spec.ts --project=h0-route-stability-1440-headless
 */
import path from 'path';
import { test, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import {
  RouteStabilityRecorder,
  attemptNavigation,
  VARIATIONS,
  type WaitUntilAttemptResult,
  type WaitUntilEvent,
} from '../helpers/route-stability.helpers';

const ROUTE_PATH = '/catalogo';
const ROUTE_LABEL = 'Catalogo (BUG-H0-CRIT-01)';
const SPEC_SLUG = 'catalogo';

const STEPS: Array<{ waitUntil: WaitUntilEvent; timeoutMs: number }> = [
  { waitUntil: 'commit', timeoutMs: 10_000 },
  { waitUntil: 'domcontentloaded', timeoutMs: 30_000 },
  { waitUntil: 'load', timeoutMs: 60_000 },
];

test.describe(`H0.1 — Route Stability — ${ROUTE_LABEL}`, () => {
  for (const variation of VARIATIONS) {
    test(`Variacao ${variation.id} — ${variation.label}`, async ({ browser }, testInfo) => {
      test.setTimeout(170_000);

      const context = await browser.newContext({
        ignoreHTTPSErrors: true,
        locale: 'pt-BR',
        javaScriptEnabled: variation.javaScriptEnabled ?? true,
        serviceWorkers: variation.serviceWorkers ?? 'allow',
      });

      if (variation.applyRouting) {
        await variation.applyRouting(context);
      }

      const page: Page = await context.newPage();
      const recorder = new RouteStabilityRecorder(page);
      recorder.attachNetworkAndConsole();
      const cdpAvailable = await recorder.attachCdpLifecycle();

      const url = targetUrl(ROUTE_PATH, 'fase-h0-route-stability');
      const shotDir = path.join(testInfo.outputDir, 'screenshots');

      const attempts: WaitUntilAttemptResult[] = [];
      let rendererDied = false;

      for (const step of STEPS) {
        if (page.isClosed()) { rendererDied = true; break; }

        const shotPath = path.join(shotDir, `${SPEC_SLUG}-${variation.id}-${step.waitUntil}.png`);
        // eslint-disable-next-line no-await-in-loop
        const result = await attemptNavigation({
          page, recorder, url,
          waitUntil: step.waitUntil,
          timeoutMs: step.timeoutMs,
          screenshotPath: shotPath,
        });
        attempts.push(result);

        console.log(
          `[H0.1-catalogo][${variation.id}][${step.waitUntil}] fired=${result.fired} `
          + `elapsedMs=${result.elapsedMs} status=${result.httpStatus} error=${result.errorMessage ?? '-'} `
          + `readyState=${result.readyStateAtEnd}`,
        );

        if (page.isClosed()) { rendererDied = true; break; }
      }

      await recorder.detachCdp();

      const evidence = {
        route: ROUTE_PATH,
        routeLabel: ROUTE_LABEL,
        variation: {
          id: variation.id,
          label: variation.label,
          description: variation.description,
        },
        cdpAvailable,
        rendererDied,
        attempts,
        consoleEntries: recorder.consoleEntries,
        pageErrors: recorder.pageErrors,
        requestEventsTotal: recorder.requestEvents.length,
        requestEvents: recorder.requestEvents,
        cdpLifecycle: recorder.cdpLifecycle,
      };

      await testInfo.attach(`route-stability-${SPEC_SLUG}-${variation.id}.json`, {
        body: JSON.stringify(evidence, null, 2),
        contentType: 'application/json',
      });

      // Investigacao read-only: nao usamos expect() para nao "falhar" a suite por
      // um comportamento que e exatamente o que estamos documentando. O relatorio
      // (docs/visual-qa/fase-h0-route-stability-investigation-2026-07-08.md)
      // consolida o veredito de cada variacao/evento a partir do JSON anexado.
      await context.close().catch(() => {});
    });
  }
});
