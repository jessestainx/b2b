# Carousel Optimize + Polish — opt12 (2026-06-01)

## Optimize — Owl init antecipado (Lançamentos)

**Problema P1:** primeira vitrine abaixo da dobra (`Lançamentos`, `.awa-carousel-section--standard`) só iniciava após IntersectionObserver + scroll.

**Fix `awa-shelf-carousel.js`:**
- `firstBelowFoldCarouselSection()` — seleciona 1ª seção `.awa-home-section--below-fold` que não é featured nem super-offers
- `boot()`: após featured, `scanShelf(priorityBelowFold)` imediato (eager)
- Demais seções: IO com margens ajustadas (below-fold 120px, default 180px; priority usa 400px se cair no IO)
- Priority section excluída do loop `whenVisible` (evita dupla init)

## Polish — tokens no `awa-shelf-carousel.css`

- `:root` shelf vars apontam para `var(--awa-primary)`, `--awa-text`, `--awa-border`, `--awa-bg-surface`
- Hex inline substituídos por `var(--awa-shelf-*)` e `color-mix`
- Shimmer pending usa `--awa-shelf-shimmer-mid`

## Deploy

- `awa-shelf-carousel.min.js` + `awa-shelf-carousel.min.css` → pt_BR, en_US
- Redis DB1+2, php-fpm

## Pendente

- Super Ofertas: 49 regras CSS mortas no polish bundle (seção HTML desativada) — `/impeccable distill` dedicado
