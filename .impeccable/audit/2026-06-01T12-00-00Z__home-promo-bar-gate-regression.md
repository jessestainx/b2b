---
target: home (cms-index-index)
bug: promo bar vermelha após CSS gate
status: fixed-2026-06-01
severity: P1
---

# Investigação — Promo bar vermelha após interação (CSS gate)

## Sintoma reportado

Barra B2B no topo da home aparece correta no primeiro paint (fundo claro, texto escuro), mas **vira vermelha** com texto branco após ~1–2s ou após scroll/interação, quando os bundles da fila `awa-css-gate` carregam.

## Componente afetado

| Elemento | Seletor DOM | Classes |
|----------|-------------|---------|
| Promo bar B2B | `#awa-b2b-promo-bar` | `.top-header.awa-utility-bar.awa-b2b-promo-bar` |

O mesmo nó combina classes de “utility bar” legado e promo B2B quiet.

## Reprodução consistente

1. Abrir `https://awamotos.com/` em viewport 375px ou 1280px.
2. **Estado A (0–500ms):** critical inline + `awa-header-impeccable-critical-global` → `background: var(--awa-bg-subtle)` (oklch 97.5%).
3. Disparar gate: scroll, `wheel`, ou aguardar fallback 1200ms.
4. **Estado B (pós-gate):** carrega `awa-home-gate-postaudit-bundle.min.css` → regra P1-1 aplicava `#b73337 !important` em `.awa-b2b-promo-bar`.

Evidência estática no source antes do fix:

```css
/* awa-home-gate-postaudit-bundle.css ~6920 */
.top-header.awa-utility-bar,
.awa-utility-bar,
.awa-b2b-promo-bar,
.top-header.awa-b2b-promo-bar {
  background: #b73337 !important;
}
```

Comentário no arquivo pedia slate `#0f172a`, mas o valor CSS permanecia vermelho.

## Cadeia CSS (cascade)

| Ordem | Fonte | Promo background | Especificidade |
|-------|--------|------------------|----------------|
| 1 | Critical inline / `HeaderImpeccableCascadeLockCss` | 44px, bg-subtle | Alta (`#html-body`) |
| 2 | `awa-commerce-impeccable-refine.css` (gate) | 44px, bg-subtle | Alta |
| 3 | **`awa-home-gate-postaudit-bundle` (gate)** | **`#b73337`** | Média, `!important` **vencia cor** |

## JavaScript relacionado

- `awa-css-gate.js`: fila inclui `awa-home-gate-postaudit-bundle`; prioridade após interação.
- Sem JS alterando estilos inline da promo bar; regressão é **puramente CSS**.

## Compatibilidade / viewports

| Viewport | Reproduz? | Notas |
|----------|-----------|-------|
| 375 mobile | Sim | Gate fallback 1200ms |
| 768 tablet | Sim | Mesma fila gate |
| 1280 desktop | Sim | Mesma fila gate |

Navegadores: qualquer engine (regra global `!important`, não depende de vendor prefix).

## Correção implementada

**Arquivo:** `awa-home-gate-postaudit-bundle.css`

1. Utility legado: seletores com `:not(.awa-b2b-promo-bar)` + fundo slate (tokens AWA).
2. Removido `.awa-b2b-promo-bar` do bloco vermelho P1-1.
3. Bloco reforço pós-gate com `html body#html-body .page-wrapper :is(#awa-b2b-promo-bar, …)` → bg-subtle, 44px, cores de texto/CTA/close alinhadas ao Impeccable quiet.

Deploy: `awa-home-gate-postaudit-bundle.min.css` → `pub/static` pt_BR/en_US + cache flush.

## Validação

- [ ] `rg '#b73337.*promo-bar' pub/static/.../awa-home-gate-postaudit-bundle.min.css` → sem match na regra da barra
- [ ] Script `tests/e2e/scripts/verify-promo-after-gate.mjs` → computed bg-subtle após `__awaApplyGatedCSS`
- [ ] Regressão visual: header/nav/hero inalterados (promo apenas)

## Riscos de regressão

- Páginas com `.awa-utility-bar` **sem** `.awa-b2b-promo-bar` passam a usar slate (intenção original do comentário P1-1).
- Promo bar em checkout/outras rotas: reforço usa seletores de promo; não altera nav vermelha.

## Comandos sugeridos

- `/impeccable polish home` — hierarquia H2 e shelves
- `/impeccable audit home` — revalidar touch targets pós-gate
