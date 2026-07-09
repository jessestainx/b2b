# Carousel Harden — Session opt11 (2026-06-01)

## Fixes

### §79 — BUG-HOME-02: gap header↔trilho (~64px)
- Root cause: `awa-layout-bundle.css` applies `margin: 64px 0 32px` on `.rokan-product-heading` on home
- Hidden duplicate headings inside Rokan widgets still reserved vertical space
- Fix: Terminal zero margin/block-size in `awa-home-gate-polish-bundle.css` §79
- Early paint: same rule in `awa-head-preload-critical-home.css` (cache `?v=20260601-carousel11`)

### §80 — BUG-HOME-03: setas Owl clipadas
- Root cause: `guardOverflow()` in `awa-shelf-carousel.js` set `overflow: hidden` inline on `.awa-shelf` / viewport, clipping absolutely positioned `.awa-owl-nav`
- Fix JS: only clip `.owl-wrapper-outer` / viewport with `overflow-x: clip`; force shelf mount `overflow: visible` when nav present
- Fix CSS §80: shelf overflow visible, stage outer hidden, desktop nav inset 0.5rem (no negative left)

### Harden — fallback estático Owl
- `applyStaticFallback()` + `applyFallbackForPendingTracks()` when:
  - `rescanUntilReady` timeout (15s)
  - `waitDeps` exhausted (18s)
  - RequireJS owl load error
  - Safety timer 16s after kickoff
- Class `awa-carousel-fallback`: horizontal scroll grid, all items visible, shimmer removed

## Deployed
- `awa-home-gate-polish-bundle.min.css` (596KB)
- `awa-head-preload-critical-home.min.css` (48KB)
- `awa-shelf-carousel.min.js` (20KB)
- pt_BR + en_US pub/static
- Redis DB1+DB2, php-fpm restart

## Still pending (P3)
- Super Ofertas dead CSS cleanup
- Hardcoded hex tokens in shelf CSS → `var(--awa-*)`
- P1: Owl init priority for first below-fold shelf (optimize)
