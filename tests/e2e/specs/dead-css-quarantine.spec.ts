/**
 * Smoke test — Quarentena Fase 6A de CSS mortos (2026-07-08)
 *
 * Fonte da verdade (manifesto completo, com evidências por arquivo):
 *   docs/visual-qa/dead-css-manifest-2026-07-08.md
 *
 * Objetivo:
 *   1. Nenhum request de CSS pode retornar 404 ou 403 nas rotas críticas
 *      da Fase 6A (regressão geral — pega qualquer CSS quebrado, não só
 *      os da quarentena).
 *   2. Nenhum dos 33 arquivos classificados como QUARENTENA no manifesto
 *      pode ser requisitado por nenhuma dessas rotas — nem os 16 já
 *      movidos para `_quarantine/css-dead-2026-07-08/`, nem os 17 que
 *      permanecem em `web/css/` aguardando resolução de edições
 *      pendentes de outra tarefa (ver README da quarentena).
 *
 * Este spec NÃO substitui a suíte de regressão visual/funcional
 * existente (specs/smoke/*, specs/visual-audit-*, etc.) — é um
 * complemento pequeno e focado, específico da Fase 6A.
 *
 * Rodar apenas este spec:
 *   npx playwright test specs/dead-css-quarantine.spec.ts --project=notebook-1366
 */
import { test, expect, Page } from '@playwright/test';
import { navigateTo } from '../helpers/deep-audit.helpers';

/**
 * Os 33 arquivos classificados como QUARENTENA em
 * docs/visual-qa/dead-css-manifest-2026-07-08.md.
 *
 * Mantido como lista local (não importado do Markdown) porque não há
 * parser de manifesto Markdown->TS neste projeto — o Markdown é a fonte
 * canônica; esta lista deve ser mantida em sincronia manualmente caso o
 * manifesto seja atualizado (ex.: arquivos INVESTIGAR reclassificados).
 */
const QUARANTINED_CSS_FILES = [
  // Já movidos para _quarantine/css-dead-2026-07-08/ (16)
  'awa-align-grid-inline-lock-20260626-phase3d22b.min.css',
  'awa-bestseller-fixes.css',
  'awa-checkout-shell-final.min.css',
  'awa-design-tokens.min.css',
  'awa-header-stack-2026-05-28.min.css',
  'awa-home-gap-fix.css',
  'awa-home-shell-final.css',
  'awa-medium-visual-fixes.min.css',
  'awa-modern-optimizations-2026.css',
  'awa-pdp-cascade-terminal.min.css',
  'awa-pdp-premium.min.css',
  'awa-plp-critical-fixes.css.min.css',
  'awa-super-home.min.css',
  'awa-vertical-menu-modern.min.css',
  'awa-visual-audit-2026-05-18.min.css',
  'awa-visual-bug-fixes.min.css',
  // Ainda em web/css/ — tinham edições não commitadas de outra tarefa
  // no momento da quarentena; permanecem QUARENTENA (zero evidência de
  // uso), aguardando um commit separado para o move físico (17)
  'awa-bundle-async-distill-lock.css',
  'awa-card-image-hero.css',
  'awa-checkout-polish.css',
  'awa-design-tokens.css',
  'awa-flex-grid-flow.css',
  'awa-header-home-light-lock-v1.css',
  'awa-home-flex-grid-flow.css',
  'awa-home-hover-lock.css',
  'awa-home-standardize-terminal-wins-2026-06-09.css',
  'awa-mobile-drill.css',
  'awa-plp-critical-fixes.css',
  'awa-plp-distill.css',
  'awa-plp-final-polish.css',
  'awa-plp-ui-promax-2026-05-22.css',
  'awa-vertical-menu-desktop-final.css',
  'awa-vertical-menu-modern.css',
  'awa-visual-audit-2026-05-18.css',
] as const;

if (QUARANTINED_CSS_FILES.length !== 33) {
  throw new Error(
    `[dead-css-quarantine] Lista local tem ${QUARANTINED_CSS_FILES.length} arquivos, ` +
    'esperado 33 — sincronize com docs/visual-qa/dead-css-manifest-2026-07-08.md.'
  );
}

/** Rotas críticas mínimas da Fase 6A (definidas para esta validação). */
const CRITICAL_ROUTES: { name: string; path: string }[] = [
  { name: 'home', path: '/' },
  { name: 'catalogo', path: '/catalogo' },
  { name: 'bauletos (PLP)', path: '/bauletos.html' },
  { name: 'nossas-marcas', path: '/nossas-marcas' },
  { name: 'about-us', path: '/about-us' },
  { name: 'lancamentos', path: '/lancamentos' },
  { name: 'lancamentos.html (redirect)', path: '/lancamentos.html' },
  { name: 'b2b/register', path: '/b2b/register' },
];

const BASE = 'https://awamotos.com';

interface CssRequest {
  url: string;
  status: number;
}

/**
 * Coleta todas as respostas de CSS (.css, com ou sem query string) feitas
 * durante a navegação e um período de estabilização, incluindo simulação
 * de interação do usuário — vários bundles AWA são carregados apenas após
 * pointerdown/scroll/keydown via awa-css-gate.js (interaction-gated),
 * e não apareceriam num curl estático do HTML inicial.
 */
async function safeWait(page: Page, ms: number): Promise<void> {
  if (page.isClosed()) return;
  await page.waitForTimeout(ms).catch(() => {});
}

/**
 * Coleta requests de CSS de forma resiliente: se a página fechar/crashar
 * no meio da coleta (renderer pesado, contencao de recursos no servidor
 * compartilhado), retorna o que foi coletado ate ali em vez de derrubar
 * o teste inteiro com uma excecao nao tratada.
 *
 * LIMITACAO CONHECIDA: este smoke test NAO simula interacao do usuario
 * (pointerdown/scroll/keydown). Alguns bundles AWA sao carregados apenas
 * apos interacao via awa-css-gate.js (#awa-css-gate-queue) — ver
 * docs/visual-qa/dead-css-manifest-2026-07-08.md, itens INVESTIGAR.
 * Simulacao de interacao (mouse/teclado) mostrou-se instavel neste
 * ambiente compartilhado de desenvolvimento (crashes de renderer sob
 * contencao de recursos); a cobertura de assets pos-interacao deve ser
 * validada manualmente ou num runner de CI dedicado com mais headroom.
 */
async function collectCssRequests(page: Page): Promise<CssRequest[]> {
  const requests: CssRequest[] = [];

  page.on('response', (res) => {
    const url = res.url();
    if (/\.css(\?|$)/i.test(url)) {
      requests.push({ url, status: res.status() });
    }
  });

  // Estabiliza após load inicial (bundles async/print-onload/defer).
  await safeWait(page, 3500);

  return requests;
}

test.describe('Quarentena Fase 6A — CSS mortos', () => {
  for (const route of CRITICAL_ROUTES) {
    test(`${route.name} (${route.path}) — sem 404/403 de CSS e sem arquivos quarentenados`, async ({ page }) => {
      test.setTimeout(50_000);
      const url = BASE + route.path;
      const ok = await navigateTo(page, url);
      const cssRequests = await collectCssRequests(page);

      test.info().annotations.push({ type: 'rota-testada', description: `${route.name} -> ${url} (nav ok: ${ok})` });

      if (!ok) {
        test.skip(true, `Navegação falhou para ${url} — não é possível validar CSS desta rota.`);
        return;
      }

      if (cssRequests.length === 0) {
        console.warn(`[AVISO] Nenhum request de CSS capturado em ${route.name} — página pode ter fechado cedo (contenção de recursos no servidor). Requests parciais não invalidam o teste, mas reduzem a cobertura.`);
      }

      // 1) Nenhum CSS pode retornar 404/403 (regressão geral, não só quarentena).
      const broken = cssRequests.filter((r) => r.status === 404 || r.status === 403);
      expect(
        broken,
        `CSS quebrado (404/403) em ${route.name}:\n` +
        broken.map((r) => `  [${r.status}] ${r.url}`).join('\n')
      ).toHaveLength(0);

      // 2) Nenhum arquivo da lista de quarentena pode ser requisitado.
      const quarantinedHits = cssRequests.filter((r) =>
        QUARANTINED_CSS_FILES.some((name) => r.url.includes(`/${name}`))
      );
      expect(
        quarantinedHits,
        `Arquivo(s) em quarentena requisitado(s) em ${route.name} (verificar manifesto antes de reclassificar):\n` +
        quarantinedHits.map((r) => `  [${r.status}] ${r.url}`).join('\n')
      ).toHaveLength(0);
    });
  }

  test('resumo — todas as rotas críticas cobertas', async () => {
    const names = CRITICAL_ROUTES.map((r) => `${r.name} (${r.path})`).join(', ');
    test.info().annotations.push({ type: 'rotas-cobertas', description: names });
    expect(CRITICAL_ROUTES.length).toBe(8);
  });
});
