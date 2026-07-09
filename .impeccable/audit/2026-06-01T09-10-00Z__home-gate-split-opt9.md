# Audit: Gate Split + Side-Stripe Purge (opt9)
**Data:** 2026-06-01  
**Sessão:** impeccable continue corrigindo home  

## O que foi feito

### 1. Split do postaudit bundle (P1 anterior)
`awa-home-gate-postaudit-bundle.css` (897KB/616KB min) → dividido em 2:

| Bundle | Linhas | Min | Conteúdo |
|--------|--------|-----|----------|
| `awa-home-gate-visual-bundle` | 1153 | 27KB | §1-§25: section rhythm, product cards, scroll reveal, mobile, category carousel, WCAG, lazy load, touch targets, typography §24-25 |
| `awa-home-gate-polish-bundle` | 24073 | 590KB | Card bugfixes v1, card visual, global resets, container, typography system, button standard, form inputs, card grid, passes 2-4, headings, etc. |

**Gate queue opt9:** visual-bundle posicionado em FIRST (após carousel/header), polish em LAST (antes de styles-m).

### 2. Side-stripe purge (Impeccable ban)
Encontradas e removidas 3 violações de `border-inline-start: 3px solid var(--awa-primary)` no polish bundle:

- `#html-body.cms-home .awa-section-header__title` — L19552
- `#html-body.cms-home .awa-category-carousel__header h2` — L19560
- `#html-body.cms-home :is(.awa-section-header, .rokan-product-heading) :is(h2, h3, .title)` — L19897

Substituído por `padding-inline-start: 0` (accent via eyebrow ou gradiente no header row).

### 3. Gate script: opt8 → opt9
`HeaderImpeccableCascadeLockCss::GATE_SCRIPT_QUERY = '20260601-home-opt9'`  
Todos os observadores/plugins atualizados para versionar opt7/opt8 como stale.

## Estado atual da fila gate (24 itens)
Ordenação pós-sort JS:
1. carousel-bundle → shelf-carousel
2. header-stack → vertical-menu → commerce-impeccable-refine
3. **home-gate-visual-bundle (27KB)** ← novo, posição FIRST
4. home-terminal-bundle → super-home → hover-lock
5. [outros: bundle-refinements, super-global, third-party, etc.]
6. **home-gate-audit-bundle** ← LAST group
7. **home-gate-polish-bundle (590KB)** ← LAST group, 590KB
8. styles-m.css ← muito último

## Promo bar: sem violações
Audit completo em visual + polish + audit bundles → 0 regras `awa-promo-bar` com fundo vermelho.

## Pendências remanescentes
- Lighthouse/PSI manual (headless timeout no servidor)
- Validação visual mobile com browser real (MCP indisponível nesta sessão)
