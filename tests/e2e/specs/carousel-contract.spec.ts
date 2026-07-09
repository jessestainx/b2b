/**
 * Regressão do contrato global de carrossel `awa-carousel`.
 *
 * Cobre o contrato validado na auditoria de 2026-07-03 (Home + PDP):
 *   - Botão de navegação 44x44px
 *   - `position: absolute` ancorado ao viewport do carrossel (`.awa-carousel`), nunca ao header
 *   - Centralização vertical do botão em relação ao carrossel
 *   - Ausência de scroll horizontal na página (overflow-x)
 *   - Estado disabled correto no primeiro slide
 *   - Foco visível (`:focus-visible`) nos botões
 *
 * Estrutura DOM esperada (ver awa-scroll-carousel.js):
 *   .awa-carousel > .awa-carousel__viewport > .awa-carousel__track > .awa-carousel__slide
 *   .awa-carousel > .awa-carousel__nav[data-awa-nav-anchor="viewport"] > .awa-carousel__button
 *
 * IMPORTANTE — lição da investigação de 2026-07-03: medir carrosséis fora do viewport
 * (lazy-loaded) SEM simular scroll real produz falso positivo de "drift" de alinhamento,
 * porque o IntersectionObserver (rootMargin: 480px) ainda não teve chance de resincronizar.
 * Por isso este spec sempre rola a página em incrementos antes de medir.
 *
 * Rodar (produção, opt-in explícito):
 *   BASE_URL=https://awamotos.com npx playwright test specs/carousel-contract.spec.ts \
 *     --project=mobile-375 --project=tablet-768 --project=notebook-1280 --project=desktop-1440
 *
 * Ou direto, sem depender do config padrão (baseURL):
 *   BASE_URL=https://awamotos.com npx playwright test specs/carousel-contract.spec.ts --project=chromium
 */
import { test, expect, type Page } from '@playwright/test';

// awa-scroll-carousel.js desliga autoplay e forca scroll instantaneo
// (scroll-behavior:auto, scrollTo behavior:'auto') quando prefers-reduced-motion
// esta ativo. Emulamos isso globalmente para tornar a medicao do contrato
// deterministica: sem essa emulacao, o autoplay da Home avanca o slide durante
// os varios segundos da simulacao de scroll, e o scroll suave faz uma simples
// atribuicao de scrollLeft animar de forma assincrona — nenhum dos dois efeitos
// tem relacao com o contrato de posicionamento/tamanho que este spec valida.
test.beforeEach(async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
});

const BASE = (process.env.BASE_URL || 'https://awamotos.com').replace(/\/$/, '');

const FALLBACK_PDP_URL =
  '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';

const BUTTON_SIZE = 44;
const BUTTON_SIZE_TOLERANCE = 1.5;
const CENTER_TOLERANCE_PX = 3;
const OVERFLOW_TOLERANCE_PX = 1;

const BREAKPOINTS: Array<{ name: string; width: number; height: number }> = [
  { name: '375', width: 375, height: 812 },
  { name: '768', width: 768, height: 1024 },
  { name: '992', width: 992, height: 900 },
  { name: '1440', width: 1440, height: 900 },
  { name: '1920', width: 1920, height: 1080 },
];

const PAGES: Array<{ name: string; path: string; expectCarousels: boolean }> = [
  { name: 'Home', path: '/', expectCarousels: true },
  { name: 'PDP', path: FALLBACK_PDP_URL, expectCarousels: true },
  { name: 'PLP', path: '/carcacas.html', expectCarousels: false },
];

const CAROUSEL_SCOPE_SELECTOR = [
  '.awa-shelf--carousel',
  '.block.related',
  '.block.upsell',
  '.awa-pdp-related',
  '.rokan-mostviewed',
  '.categorytab-container',
  '.hot-deal-tab-slider',
  '.awa-super-offers-carousel',
].join(', ');

interface CarouselMetrics {
  index: number;
  carouselBox: { top: number; left: number; width: number; height: number };
  navBox: { top: number; left: number; width: number; height: number } | null;
  navPosition: string | null;
  buttons: Array<{
    role: 'prev' | 'next' | 'other';
    width: number;
    height: number;
    position: string;
    centerY: number;
    disabled: boolean;
  }>;
}

async function simulateRealUserScroll(page: Page): Promise<void> {
  const scrollHeight = await page.evaluate(() => document.documentElement.scrollHeight);
  const step = 400;

  for (let y = 0; y < scrollHeight; y += step) {
    await page.evaluate((offset) => window.scrollTo(0, offset), y);
    await page.waitForTimeout(120);
  }

  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
  await page.waitForTimeout(200);
  await page.evaluate(() => window.scrollTo(0, 0));

  // awa-scroll-carousel.js agenda resyncs finais em ate 3200ms apos mudancas
  // de layout (queueAnchoredNavViewportResync). Medir antes disso produz
  // falso positivo de "drift" (ja investigado e documentado em 2026-07-03).
  await page.waitForTimeout(3600);
}

async function resetCarouselsToStart(page: Page): Promise<void> {
  // Carrosseis da Home tem autoplay; apos a simulacao de scroll (que dwell-a
  // segundos suficientes para o autoplay avancar o slide), scrollLeft nao
  // esta mais em 0 por design, nao por bug. Para validar deterministicamente
  // o estado "disabled no primeiro slide" do contrato, forcamos scrollLeft=0
  // e aguardamos o update() (ligado ao evento scroll) recalcular o estado.
  //
  // reducedMotion desliga o autoplay, mas um timer de autoplay ja agendado
  // antes da emulacao de media surtir efeito pode disparar uma unica vez a
  // mais e mover o scrollLeft de volta logo apos o reset. Por isso repetimos
  // o reset ate estabilizar em vez de confiar numa unica tentativa.
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await page.evaluate((scopeSelector) => {
      document.querySelectorAll(scopeSelector).forEach((scope) => {
        const viewport = scope.querySelector('.awa-carousel__viewport');
        if (viewport) {
          viewport.scrollLeft = 0;
        }
      });
    }, CAROUSEL_SCOPE_SELECTOR);
    await page.waitForTimeout(400);

    const stillDrifted = await page.evaluate((scopeSelector) => {
      return Array.from(document.querySelectorAll(scopeSelector)).some((scope) => {
        const viewport = scope.querySelector('.awa-carousel__viewport');
        return !!viewport && viewport.scrollLeft > 2;
      });
    }, CAROUSEL_SCOPE_SELECTOR);

    if (!stillDrifted) {
      break;
    }
  }
}

async function collectCarouselMetrics(page: Page): Promise<CarouselMetrics[]> {
  return page.evaluate((scopeSelector) => {
    const scopes = Array.from(document.querySelectorAll(scopeSelector));
    const results: CarouselMetrics[] = [];
    let index = 0;

    scopes.forEach((scope) => {
      const carousel = scope.querySelector(':scope > .awa-carousel, .awa-carousel');
      if (!carousel) {
        return;
      }

      const nav = carousel.querySelector(
        ':scope > .awa-carousel__nav[data-awa-nav-anchor="viewport"]'
      );
      const viewportEl = carousel.querySelector(':scope > .awa-carousel__viewport');
      // O nav usa inset:0 relativo a caixa de padding de .awa-carousel, que
      // coincide com .awa-carousel__viewport (ver padding: 6px/16px em .awa-carousel
      // nos temas Home/PDP). Comparar contra .awa-carousel (border-box) geraria
      // falso positivo, pois inclui o padding que o viewport real nao tem.
      const carouselRect = (viewportEl || carousel).getBoundingClientRect();

      // Alguns blocos publicam um chrome SSR duplicado (nao hidratado, sem a
      // classe *-mounted) com dimensao zero, invisivel ao usuario. Nao faz
      // sentido validar o contrato contra algo que nunca aparece na tela.
      if (carouselRect.width === 0 && carouselRect.height === 0) {
        return;
      }

      let navBox: CarouselMetrics['navBox'] = null;
      let navPosition: string | null = null;
      const buttons: CarouselMetrics['buttons'] = [];

      if (nav) {
        const navRect = nav.getBoundingClientRect();
        navBox = {
          top: navRect.top,
          left: navRect.left,
          width: navRect.width,
          height: navRect.height,
        };
        navPosition = getComputedStyle(nav).position;

        nav
          .querySelectorAll('.awa-owl-nav__btn, .awa-carousel__button')
          .forEach((btn) => {
            const rect = btn.getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0) {
              return;
            }
            const style = getComputedStyle(btn);
            let role: 'prev' | 'next' | 'other' = 'other';
            if (btn.classList.contains('awa-owl-nav__btn--prev') || btn.classList.contains('awa-carousel__button--prev')) {
              role = 'prev';
            } else if (btn.classList.contains('awa-owl-nav__btn--next') || btn.classList.contains('awa-carousel__button--next')) {
              role = 'next';
            } else {
              return;
            }

            buttons.push({
              role,
              width: rect.width,
              height: rect.height,
              position: style.position,
              centerY: rect.top + rect.height / 2,
              disabled:
                (btn as HTMLButtonElement).disabled === true ||
                btn.classList.contains('is-disabled') ||
                btn.getAttribute('aria-disabled') === 'true',
            });
          });
      }

      results.push({
        index: index++,
        carouselBox: {
          top: carouselRect.top,
          left: carouselRect.left,
          width: carouselRect.width,
          height: carouselRect.height,
        },
        navBox,
        navPosition,
        buttons,
      });
    });

    return results;
  }, CAROUSEL_SCOPE_SELECTOR);
}

for (const pageDef of PAGES) {
  for (const bp of BREAKPOINTS) {
    test(`Contrato de carrossel — ${pageDef.name} @ ${bp.name}px`, async ({ page }) => {
      await page.setViewportSize({ width: bp.width, height: bp.height });
      await page.goto(BASE + pageDef.path, { waitUntil: 'networkidle', timeout: 90_000 });

      await simulateRealUserScroll(page);

      // Overflow horizontal do documento e responsabilidade do contrato de
      // carrossel apenas nas paginas onde ele atua (Home/PDP). Bugs de layout
      // fora desse escopo (ex.: PLP) nao devem reprovar esta suite.
      if (pageDef.expectCarousels) {
        const overflowX = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth
        );
        expect(
          overflowX,
          `Scroll horizontal indevido na página (${pageDef.name} @ ${bp.name}px)`
        ).toBeLessThanOrEqual(OVERFLOW_TOLERANCE_PX);
      }

      await resetCarouselsToStart(page);
      const metrics = await collectCarouselMetrics(page);

      if (pageDef.expectCarousels) {
        expect(
          metrics.length,
          `Nenhum carrossel compartilhado encontrado em ${pageDef.name} @ ${bp.name}px`
        ).toBeGreaterThan(0);
      }

      for (const carousel of metrics) {
        const label = `${pageDef.name} @ ${bp.name}px, carrossel #${carousel.index}`;

        expect(carousel.navBox, `Nav ausente — ${label}`).not.toBeNull();
        if (!carousel.navBox) {
          continue;
        }

        // Nav deve estar ancorado ao viewport do PRÓPRIO carrossel, não ao header/wrapper externo.
        expect(
          Math.abs(carousel.navBox.top - carousel.carouselBox.top),
          `Nav não ancorado ao topo do carrossel — ${label}`
        ).toBeLessThanOrEqual(CENTER_TOLERANCE_PX);
        expect(
          Math.abs(carousel.navBox.height - carousel.carouselBox.height),
          `Altura do nav diverge da altura do carrossel — ${label}`
        ).toBeLessThanOrEqual(CENTER_TOLERANCE_PX);
        expect(carousel.navPosition, `Nav não é position:absolute — ${label}`).toBe('absolute');

        const carouselCenterY = carousel.carouselBox.top + carousel.carouselBox.height / 2;

        expect(
          carousel.buttons.length,
          `Nenhum botão prev/next encontrado — ${label}`
        ).toBeGreaterThan(0);

        for (const button of carousel.buttons) {
          const buttonLabel = `${label}, botão ${button.role}`;

          expect(button.width, `Largura do botão fora de 44px — ${buttonLabel}`).toBeGreaterThanOrEqual(
            BUTTON_SIZE - BUTTON_SIZE_TOLERANCE
          );
          expect(button.height, `Altura do botão fora de 44px — ${buttonLabel}`).toBeGreaterThanOrEqual(
            BUTTON_SIZE - BUTTON_SIZE_TOLERANCE
          );
          expect(button.position, `Botão não é position:absolute — ${buttonLabel}`).toBe('absolute');
          expect(
            Math.abs(button.centerY - carouselCenterY),
            `Botão não centralizado verticalmente — ${buttonLabel}`
          ).toBeLessThanOrEqual(CENTER_TOLERANCE_PX);

          if (button.role === 'prev') {
            expect(
              button.disabled,
              `Botão "prev" deveria estar desabilitado no primeiro slide — ${buttonLabel}`
            ).toBe(true);
          }
        }
      }
    });
  }
}

test('Contrato de carrossel — foco visível (:focus-visible) no botão next', async ({ page }) => {
  // A cadeia de elementos focaveis antes do carrossel (header, topbar, busca,
  // conta, menu) e mais longa que o timeout padrao de teste comporta ao somar
  // uma rodada de Tab + evaluate por elemento.
  test.setTimeout(180_000);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(BASE + '/', { waitUntil: 'networkidle', timeout: 90_000 });
  await simulateRealUserScroll(page);

  // CAROUSEL_SCOPE_SELECTOR e uma lista separada por virgulas — precisa ser
  // envolvida em :is(...) antes de encadear um combinador descendente, senao
  // cada alternativa da lista vira um seletor independente (bug ja corrigido
  // aqui: sem :is(), o .first() combinado casava a propria DIV de escopo).
  const nextButton = page
    .locator(
      `:is(${CAROUSEL_SCOPE_SELECTOR}) .awa-carousel > .awa-carousel__nav[data-awa-nav-anchor="viewport"] .awa-carousel__button--next, ` +
        `:is(${CAROUSEL_SCOPE_SELECTOR}) .awa-carousel > .awa-carousel__nav[data-awa-nav-anchor="viewport"] .awa-owl-nav__btn--next`
    )
    .first();

  await expect(nextButton).toBeVisible();

  // .focus() programatico NAO aciona :focus-visible no Chromium (so navegacao
  // real por teclado aciona). Simula um usuario navegando com Tab ate o botao.
  await page.keyboard.press('Tab');
  let guard = 0;
  // Limite generoso: o numero de elementos focaveis antes do carrossel (header,
  // topbar, busca, conta, menu) cresce com o tempo e nao e uma constante do
  // contrato de carrossel em si -- o que importa aqui e testar o estado de foco
  // do botao, nao o tamanho da cadeia de tabs ate ele.
  while (guard < 100) {
    const isTarget = await nextButton.evaluate((el) => el === document.activeElement);
    if (isTarget) {
      break;
    }
    await page.keyboard.press('Tab');
    guard += 1;
  }

  const outline = await nextButton.evaluate((el) => {
    const style = getComputedStyle(el);
    return { style: style.outlineStyle, width: style.outlineWidth };
  });

  expect(outline.style, 'Botão em foco deve exibir outline visível (:focus-visible)').not.toBe('none');
  expect(parseFloat(outline.width), 'Outline do foco deve ter largura > 0').toBeGreaterThan(0);
});

test('Contrato de carrossel — Owl Carousel legado não renderiza markup ativo', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(BASE + '/', { waitUntil: 'networkidle', timeout: 90_000 });

  const legacyOwlActive = await page.evaluate(() => {
    const nodes = Array.from(document.querySelectorAll('.owl-carousel, .owl-loaded, .owl-stage'));
    return nodes.some((el) => {
      const rect = el.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    });
  });

  expect(
    legacyOwlActive,
    'Markup ativo do Owl Carousel foi encontrado — regressão da neutralização do legado'
  ).toBe(false);
});
