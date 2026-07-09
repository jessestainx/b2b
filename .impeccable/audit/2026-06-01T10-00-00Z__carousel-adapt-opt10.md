# Carousel Adapt — Session opt10 (2026-06-01)

## Fixes aplicados (todos deployados para pt_BR + en_US)

### §76 — Dots tap target 44px (WCAG 2.5.5) — `awa-home-gate-polish-bundle.css`
- Problema: `.awa-category-carousel__dot` tinha `width/height: 8px !important` (L23617) — violação flagrante WCAG 2.5.5
- Fix: Terminal override no final do polish bundle
  - Botão: `display:inline-flex; width:2.75rem; height:2.75rem` (44px touch area)
  - Visual pip via `::before` 8px (mantém tamanho visual original)
  - Active dot: `::before { width: 1.375rem }` (pill expandida)
  - `prefers-reduced-motion` respeitado

### §77 — Compact shelves: fundo sutil `oklch(97.8% 0.005 25)` — `awa-home-gate-polish-bundle.css`
- Problema: 4 seções --compact (Guidões/Bauletos/Retrovisores/Bagageiros) idênticas visualmente à --featured
- Fix: `background-color: oklch(97.8% 0.005 25)` para todos `.awa-carousel-section--compact`
  - L=97.8%, C=0.005, H=25 = branco quase puro com leve toque quente (não vira "off-white barato")
  - Agrupa semanticamente as vitrines de "reposição rápida" sem criar contraste agressivo

### §78 — Compact tiers density + eyebrow — `awa-bundle-refinements.css`
- `awa-section-header` nos compact: margem inferior reduzida via `clamp(0.75rem, 0.65rem + 0.4vw, 1rem)`
- `.awa-section-header__eyebrow` nas compact: `display:none` (redundante com h2 + subtítulo)

### scrollPerPage: false — `awa-shelf-carousel.js`
- Problema: `scrollPerPage: true` causava saltos de página (3 itens desktop, 2 tablet) em vez de 1 item por click
- Fix: `scrollPerPage: false` — cada click avança exatamente 1 item, comportamento esperado
- JS minificado: `scrollPerPage:!1` confirmado no arquivo ao vivo

### Nav/progresso Owl mobile — já coberto pelo critical CSS
- `awa-head-preload-critical-home.css` L562-565 já força `opacity:1!important; visibility:visible!important`
- CSS usa especificidade máxima + `!important` vence carousel bundle's `opacity:0`
- Nenhuma mudança necessária

## Files alterados
- `web/css/awa-home-gate-polish-bundle.css` → `awa-home-gate-polish-bundle.min.css` (592KB)
- `web/css/awa-bundle-refinements.css` → `awa-bundle-refinements.min.css` (114KB)
- `web/js/awa-shelf-carousel.js` → `awa-shelf-carousel.min.js` (19KB)

## Cache
- Redis DB1 + DB2 flushed
- php-fpm restarted

## Pendente (próxima sessão)
- P2: Gap BUG-HOME-02 header↔trilho — validação visual necessária
- P2: Setas Owl clipadas BUG-HOME-03 — validação desktop hover/focus-within
- P2: Sem fallback estático se Owl falhar (`/impeccable harden carrosséis`)
- P3: Super Ofertas: reativar ou remover CSS morto (~30 regras `--super-offers`)
- P3: Tokens hardcoded no shelf CSS (`#b73337`, `#ffffff` → `var(--awa-*)`)
