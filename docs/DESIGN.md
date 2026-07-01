---
name: AWA Motos Design System
description: Magento 2 B2B commerce UI — catálogo confiável, tokens LESS-first, escala 8px, zero CSS standalone
colors:
  primary: "#b73337"
  primary-hover: "#8e2629"
  primary-tint-50: "#fef2f2"
  primary-tint-100: "#fde8e8"
  ink: "#1a1a1a"
  text-primary: "#333333"
  text-muted: "#666666"
  text-light: "#999999"
  bg-surface: "#ffffff"
  bg-soft: "#f7f7f7"
  border: "#e5e5e5"
  border-subtle: "#eeeeee"
  success: "#16a34a"
  warning: "#d97706"
  info: "#0ea5e9"
typography:
  display:
    fontFamily: "inherit, -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif"
    fontSize: "clamp(28px, calc(25.33px + 0.833vw), 36px)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "normal"
  headline:
    fontFamily: "inherit, -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif"
    fontSize: "clamp(24px, calc(21.33px + 0.833vw), 32px)"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "normal"
  title:
    fontFamily: "inherit, -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif"
    fontSize: "clamp(18px, calc(17.33px + 0.833vw), 20px)"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "normal"
  body:
    fontFamily: "inherit, -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif"
    fontSize: "clamp(14px, calc(13.33px + 0.208vw), 16px)"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "inherit, -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif"
    fontSize: "clamp(12px, calc(11.33px + 0.208vw), 14px)"
    fontWeight: 600
    lineHeight: 1.45
    letterSpacing: "0.04em"
rounded:
  sm: "8px"
  md: "10px"
  lg: "16px"
  pill: "9999px"
spacing:
  s-0: "0"
  s-1: "8px"
  s-2: "16px"
  s-3: "24px"
  s-4: "32px"
  s-5: "40px"
  s-6: "48px"
  s-7: "64px"
  s-8: "80px"
  s-9: "96px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.bg-surface}"
    rounded: "{rounded.sm}"
    padding: "0 24px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.bg-surface}"
    rounded: "{rounded.sm}"
    padding: "0 24px"
    height: "44px"
  shelf-nav-btn:
    backgroundColor: "{colors.bg-surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.pill}"
    padding: "0"
    size: "44px"
  shelf-nav-btn-hover:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.bg-surface}"
    rounded: "{rounded.pill}"
    padding: "0"
    size: "44px"
  hero-trust-item:
    backgroundColor: "{colors.bg-surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.sm}"
    padding: "24px 16px"
    size: "48px"
---

# Design System: AWA Motos

## Overview

**Creative North Star: "The Reliable Parts Counter"**

AWA Motos is a B2B motorcycle parts storefront on Magento 2, child theme `AWA_Custom/ayo_home5_child`. The interface should feel like a fast, trustworthy counter: stock, SKU, price, freight, and account status are always easier to read than decoration. Buyers work under time pressure in bright shops or on phones between deliveries; the UI stays light, high-contrast, and predictable.

**Key Characteristics:**

- **Restrained color:** AWA red (`#b73337`) is the single accent; it marks primary actions, links, focus, and progress, not large decorative fields.
- **8px spacing rhythm:** Structural gaps use `@awa-s-*` / `var(--awa-s-*)` (multiples of 8px). Legacy `@awa-space-*` (4px base) exists only for backward compatibility.
- **Fluid type:** Sizes scale with `clamp()` between 320px and 1280px viewport (`--awa-fs-*` in `_design-system.less`).
- **Breakpoints 576 / 768 / 992 / 1200:** Standard media-query gates (`@awa-bp-*`, `var(--awa-bp-*)`).
- **Catalog axis 1440px:** PLP, PDP, search, and cart use `@awa-page-catalog` (`1440px`) with `@awa-page-pad-catalog` (`20px`) via `_awa-page-containers.less`. Runtime: `var(--awa-page-catalog)`, `var(--awa-layout-max)`.
- **LESS-first pipeline:** New visual rules belong in `web/css/source/_extend.less` and imported partials. Compiled output is `styles-l.css` / `styles-m.css`.
- **Legacy cascade debt:** Performance bundles (`awa-bundle-refinements`, preload critical, `awa-ui-simplify-terminal`) still load async for final-wins overrides. Edit legacy CSS only to retire duplicates or fix production blockers; migrate durable rules into LESS.
- **Final-wins partials:** After `_awa-consolidated.less`, partials such as `_awa-pdp-shell-unify-2026-06.less`, `_awa-account-layout.less`, and `_awa-home-hero-trust-layout.less` win inside `styles-l.css`. PDP also uses `awa-pdp-ultra-hardfix.phtml` (last inline `<style>` on product pages).

The system explicitly rejects generic SaaS landing aesthetics, glassmorphism, gradient hero metrics, neon accents, and nested card stacks that hide catalog density.

### Implementation pipeline

```
_extend.less
  → _awa-tokens.less → _design-system.less
  → _awa-page-containers.less, _awa-typography-system.less, _awa-color-system.less, _awa-effects-system.less
  → _tokens.less, _awa-forms.less, _awa-cards.less, _awa-buttons.less
  → @import (inline) '_awa-consolidated.less'
  → _awa-account-layout.less
  → _awa-home-hero-trust-layout.less   (final-wins)
  → _awa-pdp-shell-unify-2026-06.less  (PDP 1440px + flex 54/46)
  → _awa-pdp-polish-2026-06.less / _awa-pdp-distill-2026-06.less
```

Deploy: `setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child` + `cache:flush` + Redis FPC DB2 flush when layout-critical.

**The LESS-First Law.** Express durable styling in LESS partials imported from `_extend.less`. Legacy async bundles remain for cascade final-wins and anti-FOUC; when they conflict with LESS, migrate the rule into LESS and remove the duplicate from legacy (or reinforce in `awa-pdp-ultra-hardfix.phtml` / terminal bundles until migration completes). Do not add new standalone `.css` files for routine visual work.

## Colors

Palette character: warm commercial red on cool neutrals tuned for long catalog sessions.

### Primary

- **AWA Counter Red** (`#b73337`): Primary buttons, links, shelf progress bars, focus rings, B2B CTAs. LESS: `@awa-color-primary`, `@awa-primary`. Runtime: `var(--awa-primary)`, `var(--awa-red)`.
- **Deep Shelf Red** (`#8e2629`): Hover and pressed states for primary controls. LESS: `@awa-color-primary-dark`. Runtime: `var(--awa-primary-hover)`.

### Neutral

- **Ink** (`#1a1a1a`): Strong headings and high-emphasis labels (`--awa-ink`).
- **Catalog Text** (`#333333`): Body copy and product names (`@awa-color-text-primary`).
- **Muted Spec** (`#666666`): Secondary labels, metadata (`@awa-color-text-muted`).
- **Light Hint** (`#999999`): Tertiary hints only (`@awa-color-text-light`).
- **Shelf White** (`#ffffff`): Page and card surfaces (`@awa-color-white`, `--awa-bg`).
- **Parts Gray** (`#f7f7f7`): Section backgrounds, soft panels (`@awa-color-bg-soft`, `--awa-bg-soft`).
- **Divider** (`#e5e5e5` / `#eeeeee`): Borders and separators (`@awa-color-border`, `@awa-color-border-subtle`).

### Semantic

- **Stock OK** (`#16a34a`): Success states.
- **Lead Time Warning** (`#d97706`): Warnings.
- **Counter Error** (`#b73337` on `#fef2f2`): Errors reuse brand red with tint backgrounds.

**The One Accent Rule.** AWA red appears on primary actions, active navigation, carousel progress, and focus. It must not flood backgrounds or marketing bands; rarity preserves trust.

**The No-Hex-in-Theme Rule.** In LESS, use `@awa-*` variables or `var(--awa-*)` custom properties. Never introduce new hardcoded hex in partials.

## Typography

**Display / Body Font:** System UI stack (Magento theme inheritance). No display serif; hierarchy is weight and scale, not font switching.

**Character:** Compact, legible, slightly industrial. Product shelves use semibold labels; marketing titles use bold 700. Uppercase is reserved for buttons and micro-labels (0.04em tracking), not body paragraphs.

### Hierarchy

- **Display** (700, `clamp(28px..36px)`, line-height 1.2): Homepage section titles, hero shelf headers. Mixin: `.font-size-h1()` / `var(--awa-fs-3xl)`.
- **Headline** (700, `clamp(24px..32px)`, line-height 1.25): PLP category titles, account page headers. `var(--awa-fs-2xl)`.
- **Title** (600, `clamp(18px..20px)`, line-height 1.3): Card titles, shelf names, block headings. `var(--awa-fs-xl)`.
- **Body** (400, `clamp(14px..16px)`, line-height 1.5): Descriptions, prices, forms. Max line length 65–75ch in prose blocks. `var(--awa-fs-md)`.
- **Label** (600, `clamp(12px..14px)`, letter-spacing 0.04em): Buttons (uppercase), filters, badges. `var(--awa-fs-sm)`.

**The Fluid Type Rule.** Prefer `var(--awa-fs-*)` or mixins from `_design-system.less` over fixed `px` font sizes. Fixed sizes are legacy only.

## Elevation

Depth is **tonal first, shadow second**. Most surfaces are flat white or `#f7f7f7` with 1px borders. Shadows signal hover on commerce controls, not ambient decoration.

### Shadow Vocabulary

- **Shelf lift** (`0 1px 3px rgba(0,0,0,0.08)`): Resting cards, subtle separation (`--awa-shadow-sm`).
- **Control hover** (`0 4px 12px rgba(0,0,0,0.10)`): Dropdowns, elevated panels (`--awa-shadow-md`).
- **Primary button hover** (`0 6px 18px` with brand fade): Primary CTA only (`_awa-buttons.less`).

**The Flat Catalog Rule.** Product grids and shelves do not use stacked card shadows at rest. Elevation appears on interaction (button hover, open menus), not on every tile.

## Components

### Buttons

- **Shape:** Gently rounded (8px, `@awa-radius-sm`).
- **Primary:** AWA red fill, white text, 44px min-height, uppercase label, horizontal padding 24px (`@awa-s-3`). Hover: deep red + slight lift.
- **Focus:** 2px outline on `@awa-color-primary-dark` plus soft focus ring (`@awa-focus-ring`).
- **Secondary / Ghost:** Outlined or neutral fill from `_awa-buttons.less`; same 44px touch target.

### B2B hero trust strip (diferenciais)

- **DOM:** `.awa-hero-b2b-cta` > `.awa-hero-trust-strip` > `.awa-hero-trust-strip__item` (escudo, casa, pessoa SVGs).
- **Source:** `_awa-home-hero-trust-layout.less` (import §43.2 in `_extend.less`).
- **Layout:** CSS Grid. Mobile (&lt;768px): 1 coluna empilhada. Desktop (≥768px): `repeat(3, minmax(0, 1fr))`.
- **Item:** Column flex, centered; gap `@awa-s-2`; padding `@awa-s-3 @awa-s-2`; border 1px `@awa-color-border`; radius `@awa-radius-sm`.
- **Icon:** 48px (`@awa-s-6`), `display: block`, brand red stroke; prevents intrinsic SVG overflow.
- **Label:** `var(--awa-fs-sm)`, semibold, max ~22ch, normal wrap.

### Product shelf carousel (scroll-snap)

- **Track:** Horizontal flex, `scroll-snap-type: x proximity`, slides at 87% / 50% / 33% / 25% / 20% width by breakpoint (peek on mobile).
- **Navigation:** Circular 44px prev/next (`.awa-owl-nav__btn`), pill on mobile below track, overlay on desktop (≥768px).
- **Progress:** 4px bar, brand red fill via `--awa-progress` transform.
- **Motion:** `scroll-behavior: smooth` unless `prefers-reduced-motion: reduce`.

### Cards / product tiles

- **Corner Style:** 8–10px radius on inner product cards.
- **Background:** White on soft gray sections.
- **Border:** 1px `#e5e5e5` when separation is needed; no colored side stripes.
- **Internal Padding:** Multiples of 8px (`@awa-s-2` / `@awa-s-3`).

### Inputs / Fields

- **Style:** 1px border, 8px radius, 44px touch height where applicable (`_awa-forms.less`).
- **Focus:** Brand-tinted ring, never default browser blue alone.
- **Error:** Tint background `@awa-color-primary-50`, dark red text.

### Navigation

- **Desktop (≥992px):** Horizontal bar 48px, vertical category menu separate.
- **Mobile (<992px):** Bottom nav + drawer; 44px targets.
- **Typography:** Label size for menu items; semibold for active state.

### PDP product detail shell

- **Source:** `_awa-pdp-shell-unify-2026-06.less` (LESS), mirrored in `awa-head-preload.phtml` (critical), `awa-bundle-refinements.css` (async), `awa-pdp-ultra-hardfix.phtml` (final inline).
- **Page axis:** `page-main` and `nav-breadcrumbs` centered at `max-width: 1440px`, `margin-inline: auto`, `padding-inline: 20px` (catalog tier).
- **Desktop layout (≥992px):** `.main-detail > .row` is **flex**, not grid. Columns: gallery **54%**, buy-box **46%**, gap `clamp(24px, 2.5vw, 40px)`.
- **Bootstrap pseudos:** `::before` / `::after` on `.row` are `display: none` (clearfix must not become grid items).
- **Mobile (<992px):** Single column stack; gallery may full-bleed with negative margin using `var(--awa-container-pdp-pad)`.
- **Buy box:** `.product-info-main` fills 100% of the info column; nested panels stay flat (no stacked card shadows).

**The PDP Flex Rule.** Never set `display: grid` on `.main-detail > .row`. Grid breaks the 54/46 flex split and leaves empty space inside the 1440px shell.

## Do's and Don'ts

### Do:

- **Do** import tokens via `_extend.less` → `_awa-tokens.less` → `_design-system.less` for any new theme styling.
- **Do** add layout/component overrides as new partials imported **after** `_awa-consolidated.less` when you need final-wins inside `styles-l.css`.
- **Do** use breakpoints `@awa-bp-576`, `@awa-bp-768`, `@awa-bp-992`, `@awa-bp-1200` (or `var(--awa-bp-*)`) in media queries.
- **Do** use `@awa-s-1` (8px) through `@awa-s-9` for new structural spacing.
- **Do** use `clamp()` typography tokens (`--awa-fs-md`, etc.) for responsive text.
- **Do** keep touch targets at least 44×44px on mobile for commerce actions.
- **Do** validate changed pages after `setup:static-content:deploy` for `AWA_Custom/ayo_home5_child`.
- **Do** use `@awa-page-catalog` (1440px) for PDP/PLP shell alignment; keep breadcrumbs and `page-main` on the same axis.
- **Do** enforce PDP flex 54/46 in LESS (`_awa-pdp-shell-unify`) and verify with layout probe or browser metrics after cascade changes.

### Don't:

- **Don't** use `display: grid` on `.main-detail > .row` (Bootstrap clearfix pseudos break the two-column PDP).
- **Don't** create new standalone `.css` files in `web/css/` for routine visual work. Legacy bundle edits are for final-wins retirement or production hotfixes only.
- **Don't** edit HTML, PHP, or XML for visual-token-only tasks; use LESS partials and existing Magento overrides.
- **Don't** edit `app/code/Rokanthemes/*` or the parent theme; child theme overrides only.
- **Don't** use hardcoded hex in LESS partials; use `@awa-color-*` or `var(--awa-*)`.
- **Don't** use `border-left` or `border-right` greater than 1px as a colored accent on cards or alerts.
- **Don't** apply glassmorphism, gradient text, or hero-metric templates (big number + small label blocks).
- **Don't** add bounce or elastic easing; use `@awa-ease` (`cubic-bezier(0.4, 0, 0.2, 1)`) and 120–350ms durations.
- **Don't** introduce generic SaaS visual clichés called out in PRODUCT.md: decorative landing patterns that weaken B2B catalog scanning.
