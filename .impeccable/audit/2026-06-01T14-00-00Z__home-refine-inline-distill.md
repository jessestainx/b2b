---
target: home (cms_index_index)
command: /impeccable continue refinando
timestamp: 2026-06-01T14:00:00Z
---

# Refine — home inline CSS distill + benefits 1st paint

## Objetivo

Reduzir HTML inline duplicado, unificar benefits bar no 1º paint (sem flash vermelho → cinza), manter CLS/LCP críticos.

## Alterações

### `awa-head-preload-critical-home.css` (`?v=20260601-refine`)

- Benefits bar: fundo quiet (3% primary mix) em vez de `--head-preload-c4` vermelho
- Grid benefits desktop 4 colunas @768px
- Category carousel flex horizontal + gutters mobile (ex-`cat-align-inline`)
- Hover lock transform:none no 1º paint (ex-`hover-lock-inline`)

### `awa-head-preload.phtml`

- Removidos blocos inline: `awa-shelf-b2b-terminal`, `awa-home-hover-lock`, `awa-home-cat-align` (~5 KB HTML)
- Corrigido PHP (`?>` antes dos `<style>` restantes)

### `awa-home-benefits-final-inline.phtml`

- Esvaziado (regras migradas para critical-home)

### `awa-css-gate.js` (`opt8`)

- Post-gate: removida duplicação do category carousel (já em critical-home)

## Métricas live (curl)

| Métrica | Antes (opt7) | Depois (refine) |
|---|---|---|
| HTML | ~559 KB | ~551 KB |
| `<style>` blocks | 17 | 9 |
| Inline CSS | ~50 KB | ~43 KB |
| Gate JS | opt7 | opt8 |

## Validação manual sugerida

1. Benefits bar: faixa clara desde o 1º paint (não vermelha)
2. Category carousel: scroll horizontal mobile, sem stack vertical
3. Cards: sem “pulo” no hover antes do gate
4. B2B price nos carrosséis após gate/body-end

## Pendências

- Split `awa-home-gate-postaudit-bundle` (616 KB)
- Lighthouse mobile (timeout no servidor headless)
- E2E carrosséis
