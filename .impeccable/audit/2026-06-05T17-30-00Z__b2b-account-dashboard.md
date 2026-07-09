# Audit — B2B Account Dashboard
**Date:** 2026-06-05T17:30:00Z
**Target:** `/b2b/account/dashboard/` — `GrupoAwamotos/B2B`
**Post-passes:** audit → optimize → adapt → harden → polish

---

## Audit Health Score

| # | Dimension | Score | Key Finding |
|---|-----------|-------|-------------|
| 1 | Accessibility | 3 | `h4` "Limite de Crédito" skips h3 inside h2 section; `h3` Dados da Empresa before h2 |
| 2 | Performance | 3 | `animation-fill-mode: both` gates opacity:0 on hidden tabs; 126KB terminal CSS bundle |
| 3 | Responsive Design | 3 | Touch targets 44px enforced; one `min-width: 150px` fixed-width remaining |
| 4 | Theming | 2 | Token names `c1–c42` semantic-less; dark mode `card-content h4` doesn't match new h3.card-title |
| 5 | Anti-Patterns | 3 | Uniform stagger reflex on 11 sections + `fill-mode: both` gates visibility |
| **Total** | | **14/20** | **Good (address weak dimensions)** |

---

## Anti-Patterns Verdict

**Does this look AI-generated?** Mostly no — the design serves the task, uses contextual color (brand red), avoids cream/sand tropes, no gradient text, no hero-metrics, no side-stripe borders in scope.

**Two active tells:**
1. `animation-fill-mode: both` on all entrance animations — gates content at `opacity: 0`. When a user has the tab in background and switches to it, sections appear at 0 opacity until the animation resumes. Not a slop tell per se but a craftsmanship failure.
2. Every major section has an identical `b2b-fade-up` entrance — `b2b-dashboard-header`, `b2b-company-info`, `b2b-summary-cards`, `b2b-onboarding-section`, `b2b-suggestions-section`, `b2b-quick-actions`, `b2b-section`, `b2b-rexis-section`, `erp-suggestions-section`, `erp-reorder-section`, `categories-section` (11 targets, plus 3 summary cards, plus 8 action items). This is the "uniform reflex" the impeccable guide specifically names.

---

## Executive Summary

- **Audit Health Score: 14/20** (Good)
- **P0:** 0 | **P1:** 3 | **P2:** 5 | **P3:** 4
- **Top issues:**
  1. `h4` "Limite de Crédito" creates h2→h4 skip (WCAG 1.3.1)
  2. `h3` "Dados da Empresa" may appear before any h2 when conditional sections absent (h1→h3 skip)
  3. Dark mode `card-content h4` no longer matches `h3.card-title` after heading hierarchy fix
  4. Entrance `animation-fill-mode: both` gates all sections at opacity:0 on load
  5. Token names `--b2b-acct-dash-c1` through `c42` — unmaintainable, no semantic meaning

---

## Detailed Findings by Severity

### P1 — Major

---

**[P1-A11y] Credit card h4 skips heading level**
- **Location:** `dashboard.phtml` line 216 — `<h4><?= __('Limite de Crédito') ?></h4>`
- **Category:** Accessibility
- **Impact:** Screen readers following the heading outline encounter h2 ("Resumo da conta") → h4 (skipping h3 entirely). The polish pass fixed `h4` → `h3` for Compras and Cotações but missed "Limite de Crédito." The outline is now inconsistent within the same `.b2b-summary-cards` container.
- **WCAG:** 1.3.1 Info and Relationships (Level A)
- **Recommendation:** Change to `<h3 class="card-title">` to match the other cards in the same section.
- **Suggested command:** `/impeccable polish`

---

**[P1-A11y] Company info h3 precedes the h2 summary section**
- **Location:** `dashboard.phtml` line 164 — `<h3><?= __('Dados da Empresa') ?></h3>` inside `.b2b-company-info`
- **Category:** Accessibility
- **Impact:** When neither the ERP pending banner (`h2.b2b-banner-title`) nor the onboarding section (`h2.onboarding-title`) is shown, the heading outline becomes h1 → h3 (skipping h2). The h2 sr-only "Resumo da conta" appears 40 lines later in the DOM. Screen readers will flag the skip.
- **WCAG:** 1.3.1 Info and Relationships (Level A)
- **Recommendation:** Change `.b2b-company-info` heading from `h3` to `h2`. It represents a top-level data section parallel to the onboarding and ERP banners, not a sub-heading.
- **Suggested command:** `/impeccable polish`

---

**[P1-Theming] Dark mode `card-content h4` broken by heading fix**
- **Location:** `dashboard-visual-upgrade.css` lines 541–549
- **Category:** Theming
- **Impact:** After the polish pass changed card titles from `h4` to `h3.card-title`, the dark mode selector `.card-content h4 { color: var(--b2b-dark-text-muted) }` no longer matches anything. Card title text will not receive the muted dark treatment in dark mode — it will inherit the default color (likely white on dark background, possibly same weight as values, reducing visual hierarchy).
- **Recommendation:** Update line 541 to `.card-content :is(h3, h4),` to cover both legacy and current heading levels.
- **Suggested command:** `/impeccable polish`

---

### P2 — Minor

---

**[P2-Perf] Entrance animations gate content at opacity:0**
- **Location:** `dashboard-visual-upgrade.css` lines 47–95 — `animation: b2b-fade-up 0.4s ease-out both;`
- **Category:** Performance, Anti-Patterns
- **Impact:** `fill-mode: both` applies the animation's first keyframe (`opacity: 0; transform: translateY(12px)`) as the element's initial state. On a background (hidden) tab, CSS animations pause — when the user switches to the tab, they may see the dashboard momentarily blank before the animation resumes. On low-end devices with animation-timeline jank, sections can be invisible. Headless/screenshot rendering always yields invisible sections.
- **Impeccable rule:** "Reveal animations must enhance an already-visible default. Don't gate content visibility on a class-triggered transition; transitions pause on hidden tabs."
- **Recommendation:** Change `both` to `backwards` — `forwards` is unnecessary since the end state is the default (`opacity: 1`), and `backwards` only applies the from-keyframe before the delay, not after completion. Or better: set the base element CSS to `opacity: 1` and use `@starting-style` (modern browsers) to animate from 0. Simplest safe fix: remove `both` and add a `@starting-style` block per element.
- **Suggested command:** `/impeccable animate`

---

**[P2-Anti] Uniform entrance animation on every section**
- **Location:** `dashboard-visual-upgrade.css` lines 47–95 — 11 section targets + 3 summary cards + 8 action items
- **Category:** Anti-Patterns
- **Impact:** The same `b2b-fade-up` applied to every section creates the "stagger reflex" — identical animation structure regardless of what the section contains. The onboarding checklist, product suggestions, orders table, and attendant info all animate the same way. This is pattern-matching at the training-data level.
- **Impeccable rule:** "Staggering the items within one list is legitimate. The tell is the uniform reflex (one identical entrance applied to every section)."
- **Recommendation:** Reserve `b2b-fade-up` for content that arrives from off-screen (modals, panels). For sections that are already present in the DOM on load, use `b2b-scale-in` for cards and no animation for structural sections. The attendant section and company info don't need entrance choreography.
- **Suggested command:** `/impeccable animate`

---

**[P2-Theming] Token names `c1–c42` have no semantic meaning**
- **Location:** `dashboard.css` lines 1–51 — `:root { --b2b-acct-dash-c1: #2d7a3a; ... --b2b-acct-dash-c42: #b2b; }`
- **Category:** Theming
- **Impact:** `c1` through `c42` communicate no color role, context, or use case. Changing one value (e.g., updating the brand green) requires tracing all 42 uses across the file to understand what's affected. Impossible to maintain from a design system perspective. The existing `--awa-primary`, `--awa-text-primary`, `--awa-border` tokens are semantically correct; the c-series tokens are not.
- **Recommendation:** Gradually alias to semantic tokens. Map each color to the nearest `--awa-*` equivalent or create named aliases like `--b2b-color-success`, `--b2b-color-warning-bg`. This is a refactoring task, not a one-liner.
- **Suggested command:** `/impeccable typeset` (for typography tokens) or a dedicated `/impeccable extract`

---

**[P2-A11y] `aria-controls` + `aria-expanded` on tour button is a semantic mismatch**
- **Location:** `dashboard.phtml` lines 64–69 — `aria-expanded="false" aria-controls="b2b-onboarding-tour"`
- **Category:** Accessibility
- **Impact:** `aria-controls` points to `id="b2b-onboarding-tour"` (the onboarding div on the page). But clicking the button launches Shepherd.js, which creates its own overlay DOM outside that div. Screen readers using `aria-controls` will jump to the onboarding div but find it unchanged — the tour happens elsewhere. The correct pattern for launching a tour/dialog is `aria-haspopup="dialog"`, no `aria-controls`.
- **WCAG:** 4.1.2 Name, Role, Value (Level A) — the role announcement is inaccurate
- **Recommendation:** Replace `aria-controls="b2b-onboarding-tour"` with `aria-haspopup="dialog"`. Keep `aria-expanded` — it's updated by `setTourExpanded()` in `onboarding.js`, which provides useful state feedback.
- **Suggested command:** `/impeccable harden`

---

**[P2-A11y] `b2b-rfm-badge` color is dynamic and untested for contrast**
- **Location:** `dashboard.phtml` line 79 — `style="--b2b-rfm-color:#..."`
- **Category:** Accessibility
- **Impact:** The RFM segment color comes from the ERP. If the ERP returns a mid-tone color (e.g., `#aabbcc`, a light blue), the badge text inherits or uses `var(--b2b-rfm-color)` as foreground or background. A mid-luminance color as background with white or dark text can fail WCAG AA (4.5:1). The fallback `#6b7280` (gray) also produces only ~2.7:1 contrast against white text.
- **WCAG:** 1.4.3 Contrast (Minimum) (Level AA)
- **Recommendation:** In the badge's CSS, ensure text color is set relative to the background luminance using `color-contrast()` or fallback to a known safe combination (dark text on any badge background).
- **Suggested command:** `/impeccable harden`

---

### P3 — Polish

---

**[P3-Perf] `dashboard-impeccable-audit.css` has grown to 126KB**
- **Location:** `pub/static/.../dashboard-impeccable-audit.css`
- **Category:** Performance
- **Impact:** The terminal bundle accumulates every iterative pass (audit, optimize, adapt, harden, polish) with verbose documentation comments. At 126KB uncompressed, it's large for a single-component CSS file. It's loaded async via PHTML (not in the Magento head merge), so it doesn't block initial render, but it delays above-the-fold polish.
- **Recommendation:** Consolidate overlapping rules from multiple `§` passes into single rules where possible. Strip inline documentation comments to a separate `.notes.css` file. Consider a build step to produce a minified version.
- **Suggested command:** `/impeccable optimize`

---

**[P3-Theming] Three coexisting CSS namespaces**
- **Location:** All dashboard CSS files
- **Category:** Theming
- **Impact:** `--b2b-acct-dash-c*`, `--b2b-*`, and `--awa-*` coexist without a clear hierarchy. New rules sometimes use `--awa-primary`, sometimes `var(--b2b-ayo-primary)`, sometimes hardcoded hex fallbacks. Makes theming changes unpredictable.
- **Recommendation:** Establish a clear token cascade: `--awa-*` as the source of truth, `--b2b-*` for module-specific overrides, retire `--b2b-acct-dash-c*`.
- **Suggested command:** `/impeccable extract`

---

**[P3-Responsive] `.attendant-info` has `min-width: 150px`**
- **Location:** `dashboard.css` line 572 — `.attendant-info { min-width: 150px; }`
- **Category:** Responsive Design
- **Impact:** At very narrow viewports (below 375px or with large font scaling), a fixed `min-width` can cause `.attendant-card` to overflow its container. The flex wrapping on the card partially mitigates this.
- **Recommendation:** Replace with `min-width: min(150px, 100%)` or remove entirely (flex `flex: 1` already handles sizing).
- **Suggested command:** `/impeccable adapt`

---

**[P3-A11y] Dark mode heading selectors are now stale**
- **Location:** `dashboard-visual-upgrade.css` lines 527–550 — dark mode uses `.b2b-welcome h1`, `.section-header h3`, `.suggestion-content h4`
- **Category:** Theming, Accessibility
- **Impact:** `.section-header h3` would need to include h2 now that some section headers may use h2 (after the company-info fix). `.suggestion-content h4` still matches (not changed). Minor visual inconsistency in dark mode.
- **Recommendation:** Update dark mode selectors to use `:is(h2, h3)` in section-header contexts.
- **Suggested command:** `/impeccable polish`

---

## Patterns & Systemic Issues

1. **Heading hierarchy managed reactively** — each iterative pass (harden, polish) partially fixed heading levels but the system has no canonical mapping of `h1→h2→h3` to section types. Result: three h4→h3 changes done, one missed (Limite de Crédito), one new h3 created that should be h2 (Dados da Empresa). A one-time structural audit of the full heading outline is needed.

2. **Animation layer untouched** — the `dashboard-visual-upgrade.css` entrance animations have not been touched across any pass. Four passes later, `fill-mode: both` still gates visibility and the stagger reflex still applies to all 22 elements. Motion is the one dimension where no fixes landed.

3. **Terminal CSS bundle growing** — `dashboard-impeccable-audit.css` absorbs every fix as an `!important` layer. This is architecturally sound for fast iteration but produces CSS debt: rules from different passes may conflict, be duplicated, or become orphaned as the PHTML changes.

---

## Positive Findings

- **`aria-live` region** (`#b2b-dashboard-live`) correctly implemented with `aria-atomic="true"` — screen reader announcements for async data updates work properly
- **SVG `aria-hidden`** — all 29 decorative icons now have `aria-hidden="true" focusable="false"`
- **Table captions** — both ERP orders and Magento fallback orders/quotes tables have `<caption class="sr-only">`
- **Touch targets** — 44px enforced via `--b2b-touch-target` CSS variable across all interactive elements
- **AJAX resilience** — error messages are scoped per-section, typed by HTTP status, with "Tentar novamente" retry pattern and inflight-guard preventing duplicate requests
- **Focus rings** — 2px solid `--awa-primary` on all interactive elements including table links (added in polish)
- **`transition: all` overridden** — targeted properties on 7 selectors prevent accidental layout-property animation
- **Overflow protection** — `overflow-wrap: anywhere` + `min-width: 0` on dynamic text nodes
- **Guest redirect** — correctly sends to B2B login, not generic Magento login

---

## Recommended Actions

1. **[P1] `/impeccable polish`** — Fix `h4` → `h3.card-title` for "Limite de Crédito"; change `.b2b-company-info` heading from `h3` → `h2`; update dark mode `card-content h4` selector to `:is(h3, h4)`
2. **[P1] `/impeccable polish`** — Correct `aria-controls`/`aria-haspopup` on tour button
3. **[P2] `/impeccable animate`** — Change `animation-fill-mode: both` → `backwards` across visual-upgrade.css; thin out the uniform stagger reflex
4. **[P2] `/impeccable harden`** — Add contrast guard for dynamic RFM badge color
5. **[P3] `/impeccable extract`** — Map `--b2b-acct-dash-c*` tokens to semantic aliases; consolidate to `--awa-*` namespace
6. **[P3] `/impeccable optimize`** — Minify or split `dashboard-impeccable-audit.css`; strip documentation comments to separate file

> Re-run `/impeccable audit` after fixes to see your score improve.
