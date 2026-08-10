# CSS_INVENTORY.md — Inventário de Arquivos CSS
**AWA Motos · ayo_home5_child · web/css/**  |  **Última atualização:** 2026-08-10 (reescrevido — versão 2026-04-22 estava obsoleta)

**Legenda:** `SYNC` carregamento síncrono | `ASYNC` via fila `__awaCssQ` (media=print→all) | `GATE` retido pelo paint gate até interação/timeout | `XML` via layout XML | `LOADER` via `awa-align-grid-terminal-loader.phtml`

---

## Cascata vigente (ordem de prioridade, última ganha)

| # | Arquivo | Via | Modo |
|---|---|---|---|
| 1 | `styles-m.css` / `styles-l.css` | merge Magento | SYNC |
| 2 | `themes.css` (merge herdado do tema pai) | merge Magento | SYNC |
| 3 | `awa-super-global-20260611m.min.css` | `awa-head-preload.phtml` (`SUPER_GLOBAL_CSS_FILE`) | SYNC |
| 4 | `awa-defer-global-bundle.min.css` | `awa-head-preload.phtml` | ASYNC |
| 5 | `awa-third-party-bundle.min.css` | `awa-head-preload.phtml` | GATE |
| 6 | `awa-layout-bundle-20260611m.min.css` | `awa-head-preload.phtml` | ASYNC |
| 7 | `awa-commerce-impeccable-refine.min.css` | `awa-head-preload.phtml` (`REFINE_CSS_FILE`) | ASYNC |
| 8 | `awa-carousel-bundle.min.css`, `awa-head-tail-bundle.min.css` | `awa-head-preload.phtml` | ASYNC |
| 9 | `awa-align-grid-terminal-2026-06-11.min.css` | LOADER (7 layouts) + head-preload (PDP/checkout) | SYNC |
| 10 | `awa-m2-visual-ssot.min.css` | LOADER — SSOT final (rating/shelf/carousel/cards/badge) | SYNC |
| 11 | `css/awa-design-system.css` | `default_head_blocks.xml:210` — **deve permanecer o último `<css>`** | SYNC |

> Os antigos bundles `awa-bundle-core/category/phases/refinements` **não existem mais** (fundidos/migrados). `awa-bundle-site.css` é um shim vazio proposital (437 bytes) — **não referenciar** em layout XML/PHTML novo.

## Via `default_head_blocks.xml` (globais)

| Linha | Arquivo |
|---|---|
| 181 | `css/awa-visual-fixes-2026-06-29-final.css` (desde 2026-08-10 inclui ex-`visual-noise` + ex-`cookie-fab-collision` — seções MERGED) |
| 210 | `css/awa-design-system.css` (último) |

> Consolidação P2 (2026-08-10): `awa-visual-noise-2026-07-15-r2.css` e `awa-cookie-fab-collision-fix-2026-07-08.min.css` fundidos em `awa-visual-fixes-2026-06-29-final.css` e removidos deste XML. Fontes preservadas em `web/css/` (referência do `build-awa-home-deferred-stack.sh`, que mantém cópias inline no stack da home).

O mesmo XML remove ~20 CSS legados (`themes5.css`, `styles-m/l` originais, `awa-core.css`, `awa-grid-unified.css`, etc.).

## Condicionais por rota (via `awa-head-preload.phtml`)

| Rota | Folhas extras |
|---|---|
| Home | `awa-head-preload-critical-home.min.css`, `awa-home-polish-critical.min.css`, `awa-home-launches-toggle-fix.min.css`, `awa-super-home.min.css` + CSS crítico inline anti-FOUC |
| PLP/busca | `awa-plp-critical-fixes.min.css` (media=all) |
| PDP/checkout | `awa-align-grid-terminal-2026-06-11.min.css` (também via LOADER) |
| Checkout/cart/B2B-auth | `$__skipLegacyFlowCss` — pulam CSS legado de fluxo |

O loader `awa-align-grid-terminal-loader.phtml` é referenciado em: `cms_page_view.xml`, `onepagecheckout_index_index.xml`, `catalog_product_view.xml`, `customer_account.xml` (B2B), `checkout_index_index.xml`, `checkout_onepage_success.xml`, `checkout_cart_index.xml`.

## Dependências de nome (⚠️ não renomear sem grep prévio)

`OptimizeHeadStylesPlugin.php` (`app/code/GrupoAwamotos/Theme/Plugin/Response/`), `HeaderImpeccableCascadeLockCss.php` e `HomeCssGateParity.php` fazem match por **nome de arquivo via regex** para injeção/dedup/paint-gate (~15 pontos hardcoded). `awa-align-grid-terminal-2026-06-11` é o nome mais acoplado. O helper `__emitDeferredCss` do head-preload **omite silenciosamente** links de arquivos ausentes em disco — rename/movimentação falha sem erro.

## Tokens

- Fonte canônica: `source/_awa-variables.less` (vars LESS `@awa-*`, ~80 tokens de cor)
- Custom properties `--awa-*`: emitidas por `source/_tokens.less` (`--awa-primary`, `--awa-red` etc.)
- ⚠️ Tokens `--awa-*` estão **duplicados** em `:root` de vários bundles compilados (`awa-super-global`, `awa-head-tail-bundle`, `awa-head-preload-critical-home`, `awa-impeccable-layout-2026-06-16`) — pendência de consolidação

## LESS sources

- `source/_extend.less`: 133 linhas `@import`, só **31 ativas** (resto comentado = histórico do pipeline)
- ~200 arquivos `.less` em `source/` **órfãos** (não importados por nenhum `_extend.less` ativo) — candidatos a `_disabled/`
- `source/_awa-flex-grid-flow.less` é fonte órfã cujo compilado `awa-flex-grid-flow.min.css` é carregado separadamente (mesma base em 2 lugares)

## Estado do diretório (2026-08-10)

- `web/css/`: **139 `.css` + 68 `.min.css`** (inclui pares fonte/min nem sempre sincronizados — ex.: `awa-align-grid-terminal-2026-06-11.min.css` esteve 1 dia/205KB atrás do fonte)
- Subdirs: `b2b/`, `layers/`, `source/`, `_deprecated/`, `pub/`
- Regeneração de pares min: `scripts/build-awa-css-min-pair.sh`; sidecars: `scripts/check-awa-static-sidecars.sh`
- Há `.bak` solto (`awa-vfix-cms-b2b.css.bak-20260719_223913`) e anotações `__*.md` de 2026-06 no diretório

## Dívida conhecida (ordem de ataque)

1. ~10 bundles datados concentram ~44% dos ~100k `!important` do tema (`awa-align-grid-terminal`, `awa-defer-global-bundle`, `awa-home-deferred-stack`, `awa-layout-bundle-20260611m`, `awa-commerce-impeccable-refine`…)
2. ~~4 pares `.css`+`.min.css` referenciados simultaneamente~~ **verificado 2026-08-10**: home = padrão intencional `media="print"`+`<noscript>` fallback; achado real = `styles-l.min.css` duplicado na PLP/PDP (um com `media="(min-width:768px)"`, outro **sem media query** → CSS desktop aplicado no mobile; origem provável: `OptimizeHeadStylesPlugin.php` — corrigir na P2 junto com o plugin)
3. 130 blocos `<style>` injetados via PHP + 89 em PHTML (`awa-head-preload.phtml` tem 16)
4. ~8,8k hex fora de tokens; ~200 LESS órfãos
5. Risco operacional: `bin/deploy-static` faz `rm -rf pub/static/frontend` antes de redeployar → janela de minutos com HTML cacheado apontando para `version{N}` apagado (site sem CSS para novos acessos até o flush final). Melhoria: deploy em novo version dir + troca atômica do symlink
6. Flaky conhecidos na suíte visual-core: Menu (clip de largura variável) e Cards (offset vertical ~20px oscilante por altura dinâmica acima do clip) — estabilizar clip/máscara na P1; PDP tem contador "N pessoas visualizaram" dinâmico dentro do clip (mascarar)
