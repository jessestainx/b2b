---
parent: 2026-06-08T20-30-00Z__carcacas-post-fix.md
status: verified-2026-06-08-continue
url: https://awamotos.com/carcacas.html
---

# Re-audit final — `/carcacas.html` (continue P2/P3)

**Baseline audit:** 12/20 (Acceptable)
**Pós P0/P1:** 14/20 (Good)
**Pós P2/P3:** **16/20 (Good)**

## Audit Health Score

| # | Dimension | Antes (continue) | Depois | Key Finding |
|---|-----------|------------------|--------|-------------|
| 1 | Accessibility | 3 | **4** | `toolbar-amount-top/bottom` únicos; overlay sem alt "Carregando"; gate banner sem `aria-hidden` conflito |
| 2 | Performance | 2 | **2** | HTML ~581 KB (toolbar duplicada mantida) |
| 3 | Responsive Design | 4 | **4** | Touch 44px mantido |
| 4 | Theming | 2 | **3** | Hex removidos de critical-fixes v4; logistics sem inline styles |
| 5 | Anti-Patterns | 3 | **4** | Gate B2B único no topo; cards só com link "Ver preço atacado" |
| **Total** | | **14/20** | **16/20** | **Good — polish restante é P3 performance** |

## Correções desta rodada

| Item | Status | Evidência live |
|------|--------|----------------|
| P2 `toolbar-amount` duplicado | ✅ | 1× `toolbar-amount-top`, 1× `toolbar-amount-bottom` |
| P2 loader filtro Valor | ✅ | Fallback `Faixa: R$ …`; overlay `aria-hidden="true"`, sem alt "Carregando" |
| P2 gate B2B repetido | ✅ | 1× `.awa-plp-b2b-gate-banner`; 0× `.awa-b2b-gate-card--compact` |
| P2 hex critical-fixes | ✅ | Tokens `var(--awa-*)` no v4 |
| P3 list.phtml polish | ✅ | Removido `<h2 class="hidden">`; inline styles → LESS |

## Issues remanescentes (P3)

- HTML ~581 KB (toolbar duplicada + critical CSS head)
- Nomes produto CAPS (ERP) — `ProductNameTitleCasePlugin` na listagem
- Spec E2E dedicada `carcacas` ausente

## Arquivos alterados

- `Magento_Catalog/templates/product/list/toolbar/amount.phtml` (novo)
- `Magento_Catalog/templates/product/list.phtml`
- `Rokanthemes_LayeredAjax/templates/layer/filter.phtml` (novo)
- `Rokanthemes_LayeredAjax/templates/layer/view.phtml`
- `awa-impeccable-category-critical-fixes-v4.phtml`
- `_awa-plp-consistency-pass2-2026-06.less`
- `awa-custom-home-category-compat.js`

## Ops note

Após editar PHTML em developer mode, recarregar PHP-FPM se FPC servir HTML stale:

```bash
sudo systemctl reload php8.4-fpm
sudo -u www-data php bin/magento cache:flush full_page block_html
```
