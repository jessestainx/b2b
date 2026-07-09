# Audit — carcacas.html (polish pass)

**Data:** 2026-06-08T23:30:00Z
**URL:** https://srv1113343.hstgr.cloud/carcacas.html
**Comando:** `/impeccable polish`
**Baseline:** 17/20 Good (pós-optimize)

## Score

| Dimensão | Antes | Depois | Notas |
|----------|-------|--------|-------|
| Visual consistency | 3 | 3 | Banner/toolbar alinhados a tokens |
| Typography & copy | 3 | 4 | Banner desc corrigida (não repete CTA) |
| Interaction states | 3 | 4 | Focus/hover banner + gate cards |
| Accessibility | 3 | 3 | Mantido (toolbar IDs, touch 44px) |
| Performance | 3 | 3 | Sem regressão de payload |
| **Total** | **17/20** | **18/20 Good** | |

## Correções aplicadas

### P1 — Copy banner B2B
- **Problema:** `getPriceGateDescription()` injetava mensagem curta de card ("Ver preço atacado") no banner PLP.
- **Fix:** `getPriceGateBannerDescription()` no helper B2B; `list.phtml` usa copy long-form; guarda contra duplicar label do CTA primário.

### P1 — Hex no critical CSS (head preload)
- **Problema:** `.b2b-login-to-see-price` na PLP usava `#fff`, `#e5e5e5`, `#666` inline.
- **Fix:** Migrado para `var(--awa-bg)`, `var(--awa-border)`, `var(--awa-text-secondary)` em `awa-head-preload.phtml`; regras espelhadas em LESS pass2.

### P2 — Interaction polish
- Banner B2B: hover + `:focus-visible` nos CTAs; `prefers-reduced-motion`.
- Bottom toolbar slim: layout coluna, divisor superior, ordem pager → contagem.
- Gate cards: `:focus-visible` no link de login.

## Validação live (curl)

| Check | Resultado |
|-------|-----------|
| Banner desc contém "Cadastre-se gratuitamente" | OK |
| Banner desc ≠ "Ver preço atacado" | OK |
| `toolbar-products--bottom-slim` | OK |
| Critical CSS sem `#fff` no gate | OK |
| Tokens `--awa-bg` / `--awa-text-secondary` | OK |
| Hero/toolbar 27 itens | OK |

## Pendências (P3, não bloqueiam ship)

- Title case parcial em nomes ERP mixed-case (plugin só atua em ALL CAPS).
- Spec E2E dedicada `carcacas` em `tests/e2e/specs/`.

## Arquivos alterados

- `app/code/GrupoAwamotos/B2B/Helper/Data.php`
- `app/design/.../Magento_Catalog/templates/product/list.phtml`
- `app/design/.../Magento_Theme/templates/html/awa-head-preload.phtml`
- `app/design/.../web/css/source/_awa-plp-consistency-pass2-2026-06.less`
