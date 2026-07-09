# Carousel continue — carousel13

## Scene
B2B buyer on phone in bright shop, scrolling product shelves one card at a time.

## Changes

1. **JS `itemsTablet: [768, 1]`** — fixes 2 squeezed cards on 480–767px (prior session).
2. **`awa:css-gate-applied` event** — `awa-css-gate.js` dispatches after polish queue; shelf reloads Owl + equalize.
3. **Eager init** — first 2 below-fold carousel sections (Lançamentos + next category).
4. **Critical CSS** — shelf gap token + wrapper-outer margin on first paint (aligned with §81).
5. **Cache bust** — `carousel13` on shelf loader, gate script, critical home CSS.
6. **E2E** — test 13: mobile ~1 visible Owl card per slide.

## Verify

- Hard refresh home mobile; swipe Mais Vendidos / Lançamentos.
- `npx playwright test tests/e2e/specs/functional/func-home-carousels.spec.ts -g "13 — mobile"`
