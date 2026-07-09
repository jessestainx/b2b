---
parent: audit_plp_carcaças_0c7f0105.plan.md
status: verified-2026-06-08
url: https://awamotos.com/carcacas.html
---

# Re-audit pós-correção — `/carcacas.html`

**Baseline:** 12/20 (Acceptable) — plano Impeccable 2026-06-08
**Pós-fix:** **14/20 (Good)**

## Audit Health Score

| # | Dimension | Antes | Depois | Key Finding |
|---|-----------|-------|--------|-------------|
| 1 | Accessibility | 2 | **3** | `modes-label-top/bottom` únicos; grid com `aria-label`; `toolbar-amount` ainda duplicado |
| 2 | Performance | 2 | **2** | HTML ~579 KB; toolbar duplicada mantida |
| 3 | Responsive Design | 3 | **4** | Touch targets grid 44px via LESS (`@awa-touch-target-min`) |
| 4 | Theming | 2 | **2** | Hex em `awa-impeccable-category-critical-fixes-v4.phtml` (P2 pendente) |
| 5 | Anti-Patterns | 3 | **3** | Gate B2B repetido por card (operacional, não estético) |
| **Total** | | **12/20** | **14/20** | **Good — dimensões fracas restantes são P2** |

## Correções aplicadas (plan todos)

| Todo | Status | Evidência live |
|------|--------|----------------|
| P0 catálogo | ✅ | 0× `RET.` na página 1; 22 SKUs diretos em cat. 38; 1º produto = Carcaça Inferior CG Titan |
| P1 contagem hero | ✅ | Hero **27 produtos** = toolbar **27 resultados** |
| P1 toolbar a11y | ✅ | 1× `modes-label-top`, 1× `modes-label-bottom`; 4× `aria-label="Exibir N colunas"` por toolbar |
| P1 runtime patches | ✅ | `documentElement font-size 12px` removido do runtime patch v2 |
| P1 SEO meta | ✅ | Meta: *"Carcaças e carenagens para motos: painel, farol e internas…"* (flat table sincronizada) |

## Issues remanescentes (P2/P3)

- **[P2] `id="toolbar-amount"` duplicado** — topo + rodapé (mesmo padrão Rokan em todas PLPs)
- **[P2] Loader "Carregando…" no filtro Valor** — Mirasvit/LayeredAjax
- **[P2] Gate B2B repetido em 12 cards** — guest PLP
- **[P2] Hex hardcoded em critical-fixes v4** — migrar para `_category-page.less`
- **[P3] HTML ~579 KB** — toolbar duplicada + patches head

## Arquivos alterados nesta sessão

- `GrupoAwamotos/Theme/ViewModel/CategoryVisibleProductCount.php`
- `GrupoAwamotos/Theme/Plugin/.../ToolbarSlotCacheKeyPlugin.php`
- `Magento_Catalog/templates/product/list/toolbar/*.phtml`
- `Magento_Catalog/templates/category/image.phtml`
- `catalog_category_view.xml`
- `_awa-plp-consistency-pass2-2026-06.less`
- `awa-impeccable-category-runtime-patch-v2.phtml`
- Dados: 7 SKUs reassignados; `meta_description` + flat category sync

## Comandos pós-deploy

```bash
sudo -u www-data php bin/magento setup:di:compile
sudo -u www-data php bin/magento cache:flush
redis-cli -h ::1 -a '***' -n 1 FLUSHDB
redis-cli -h ::1 -a '***' -n 2 FLUSHDB
```
