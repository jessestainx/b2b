---
parent: 2026-06-08T21-15-00Z__carcacas-continue.md
status: verified-2026-06-08
url: https://awamotos.com/carcacas.html
command: optimize
---

# Optimize — `/carcacas.html`

## Baseline (pré-optimize)

| Métrica | Valor |
|---------|-------|
| HTML total | **575 KB** |
| CSS inline | **270 KB** (10 blocos) |
| Maior bloco | `awa-header-impeccable-cascade-lock-v19` **148 KB** |
| Toolbars completas | 2× (~7 KB cada) |
| Links grid-mode | 8 |
| Runtime patch JS | presente |

## Gargalo identificado

O **148 KB de CSS inline** (`HeaderImpeccableCascadeLockCss`) era reinjetado em **todas** as PLPs via `OptimizeHeadStylesPlugin` + `PatchHomeHeaderHtmlPlugin`, apesar do header terminal já estar compilado em `styles-l.css` (§43.02 `_extend.less`).

## Correções aplicadas

1. **Omitir cascade-lock inline** em `catalog_category_view`, `catalogsearch_result_index`, `catalog_product_view` — usar `stripLegacyFromHtml()` em vez de `injectBeforeBodyClose()`.
2. **Toolbar inferior slim** — só paginação + contagem (`toolbar-products--bottom-slim`); remove sorter, grid-mode e `data-mage-init` duplicado.
3. **Remover runtime patch v2** do layout PLP (regras já em `_awa-plp-consistency-pass2-2026-06.less`).
4. **`content-visibility: auto`** nos cards a partir do 5º item (below-fold paint skip).

## Resultado (pós-optimize)

| Métrica | Antes | Depois | Δ |
|---------|-------|--------|---|
| HTML total | 575 KB | **418 KB** | **-27%** |
| CSS inline | 270 KB | **121 KB** | **-55%** |
| cascade-lock v19 | ✓ | **✗** | -148 KB |
| grid-mode links | 8 | **4** | -50% |
| runtime patch | ✓ | **✗** | menos TBT |

## Performance score (audit dimension)

| Dimension | Antes (continue) | Depois |
|-----------|------------------|--------|
| Performance | 2 | **3** |
| **Total audit** | 16/20 | **17/20** |

## Arquivos alterados

- `GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`
- `GrupoAwamotos/Theme/Plugin/Response/PatchHomeHeaderHtmlPlugin.php`
- `Magento_Catalog/templates/product/list/toolbar.phtml`
- `Magento_Catalog/layout/catalog_category_view.xml`
- `_awa-plp-consistency-pass2-2026-06.less`

## Próximo passo sugerido

`/impeccable polish carcacas.html` — pass final visual após ganho de payload.
