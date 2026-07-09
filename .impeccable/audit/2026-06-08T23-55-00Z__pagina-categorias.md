# Audit — Páginas de Categoria (PLP)

**Data:** 2026-06-08T23:55:00Z
**Escopo:** `catalog-category-view` (Magento PLP)
**Amostras:** `/carcacas.html`, `/retrovisores.html`
**Comando:** `/impeccable audit pagina de categorias`
**Baseline:** 18/20 Good (polish carcacas, rubrica UX)

---

## Audit Health Score

| # | Dimensão | Score | Key Finding |
|---|----------|-------|-------------|
| 1 | Accessibility | 3 | `id="paging-label"` duplicado (top + bottom pager) |
| 2 | Performance | 3 | HTML ~428 KB; ~130 KB CSS inline no `<head>` |
| 3 | Responsive Design | 3 | Sem scroll horizontal; touch 44px na maioria dos alvos mobile |
| 4 | Theming | 2 | `awa-plp-ui-promax` ainda com hex cru; 3 camadas CSS PLP |
| 5 | Anti-Patterns | 3 | Identidade AWA moto/B2B; sem eyebrow scaffold nem gradient text |
| **Total** | | **14/20** | **Good** |

**Rating band:** 14–17 Good — dimensões fracas: Theming e A11y residual.

---

## Anti-Patterns Verdict

**Pass (com ressalvas).** A PLP não parece “AI slop”: paleta vermelho AWA, grid de produtos legítimo para e-commerce, banner B2B único em vez de gate por card. Tells residuais: tokens glass em `awa-plp-final-polish.css` (não dominante na página) e gradientes suaves em toolbar/sidebar legado.

---

## Executive Summary

- **Score:** 14/20 (Good)
- **Issues:** P0: 0 · P1: 1 · P2: 6 · P3: 4
- **Top issues:**
  1. IDs duplicados no pager (`paging-label`)
  2. Stack CSS PLP redundante com hex hard-coded (`promax` + `final-polish`)
  3. HTML ainda pesado (~428 KB) apesar do ganho pós-optimize
  4. Nomes de produto sem acentuação/title case consistente
  5. Sem spec E2E versionada para regressão de categoria
- **O que já funciona bem:** contagem hero = toolbar (27), toolbar a11y com IDs por slot, banner B2B, bottom toolbar slim, lazy loading, `content-visibility` nos cards, sem `cascade-lock`.

---

## Detailed Findings

### P1 — Major

**[P1] ID duplicado `paging-label`**
- **Location:** Pager top + bottom em `list.phtml` / `toolbar.phtml` (core Magento `pager.phtml`)
- **Category:** Accessibility
- **Impact:** `aria-labelledby` e leitores de tela referenciam label ambígua; viola HTML único + WCAG 4.1.1 Parsing
- **WCAG:** 4.1.1 (Level A)
- **Recommendation:** Sufixo por slot (`paging-label-top` / `paging-label-bottom`) via override de `pager.phtml` ou plugin no toolbar slot (mesmo padrão de `modes-label-{slot}`)
- **Suggested command:** `/impeccable harden pagina de categorias`

### P2 — Minor

**[P2] Stack CSS PLP com hex legado**
- **Location:** `awa-plp-ui-promax-2026-05-22.min.css`, `awa-plp-final-polish.css` (carregados na PLP)
- **Category:** Theming
- **Impact:** Cores `#333`, `#666`, `#e5e5e5` competem com tokens `--awa-*` do pass2; drift visual entre categorias
- **Recommendation:** Consolidar em `_awa-plp-consistency-pass2-2026-06.less` e desregistrar bundles legados do layout
- **Suggested command:** `/impeccable colorize pagina de categorias`

**[P2] Payload HTML elevado**
- **Location:** `carcacas.html` ~428 KB; 9 blocos `<style>` ~133 KB
- **Category:** Performance
- **Impact:** TTFB + parse HTML em 3G; FCP depende de critical path longo
- **Recommendation:** Auditar blocos inline restantes no `awa-head-preload.phtml`; diferenciar critical vs deferred por rota
- **Suggested command:** `/impeccable optimize pagina de categorias`

**[P2] Alvos táteis do pager no desktop (36px)**
- **Location:** `.pages .item a` desktop 1366px
- **Category:** Responsive
- **Impact:** Abaixo de 44px; aceitável com mouse, falha WCAG 2.5.5 em touch híbridos
- **Recommendation:** `min-height: 44px` também no desktop ou `@media (pointer: coarse)`
- **Suggested command:** `/impeccable adapt pagina de categorias`

**[P2] Title case / acentuação inconsistente nos nomes**
- **Location:** `list.phtml` / `ProductNameTitleCasePlugin` — ex.: "Carcaca Inferior" (sem ç)
- **Category:** Accessibility + Copy
- **Impact:** Leitura pior; plugin só atua em ALL CAPS do ERP
- **Recommendation:** Estender plugin com dicionário PT (`Carcaca` → `Carcaça`) ou normalização NFC
- **Suggested command:** `/impeccable typeset pagina de categorias`

**[P2] Imagens com `alt=""`**
- **Location:** 2× `<img alt="">` na PLP (swatches/decoração)
- **Category:** Accessibility
- **Impact:** Conteúdo decorativo OK se `aria-hidden`; senão falha 1.1.1
- **Recommendation:** `alt=""` + `role="presentation"` ou texto descritivo em swatches de cor
- **Suggested command:** `/impeccable polish pagina de categorias`

**[P2] Ausência de spec E2E de categoria no CI**
- **Location:** `tests/e2e/` — scripts ad-hoc (`plp-validation.mjs`) sem spec Playwright versionada
- **Category:** Performance / Hardening
- **Impact:** Regressões de toolbar, gate B2B e hero não bloqueiam merge
- **Recommendation:** Spec `category-plp.spec.ts` com smoke hero/toolbar/banner/pager
- **Suggested command:** `/impeccable harden pagina de categorias`

### P3 — Polish

**[P3] `modes-label-bottom` ausente**
- **Location:** Bottom toolbar slim oculta `.modes`
- **Category:** Accessibility
- **Impact:** Nenhum em produção (intencional); documentar no contrato PLP
- **Suggested command:** `/impeccable document pagina de categorias`

**[P3] Tokens glass em `awa-plp-final-polish`**
- **Category:** Anti-Pattern
- **Impact:** Baixo; variáveis `--awa-glass-*` pouco visíveis
- **Suggested command:** `/impeccable quieter pagina de categorias`

**[P3] 94 tags `<script>` na PLP**
- **Category:** Performance
- **Impact:** Parse/bind JS; maioria Magento core
- **Suggested command:** `/impeccable optimize pagina de categorias`

**[P3] Hero compacto mobile (50px)**
- **Category:** Responsive
- **Impact:** Título/contagem renderizam abaixo do fold visual do hero; funcional, hierarquia estranha
- **Suggested command:** `/impeccable layout pagina de categorias`

---

## Patterns & Systemic Issues

1. **IDs únicos por slot** já resolvido para toolbar (`modes-label-top`, `toolbar-amount-*`); pager ainda usa template core sem slot.
2. **Três camadas CSS PLP** (`pass2` LESS + `promax` + `final-polish`) geram drift de tokens.
3. **Scripts E2E ad-hoc** em `tests/e2e/*.mjs` sem integração ao workflow de PR.

---

## Positive Findings

- Hero e toolbar sincronizados (27 produtos em carcaças).
- Banner B2B único com copy long-form correta.
- Gate por card eliminado (`awa-b2b-gate-card--compact`: 0).
- Bottom toolbar slim ativo.
- `cascade-lock` removido da PLP.
- Lazy loading em imagens de produto.
- `content-visibility: auto` a partir do 5º card.
- Sem scroll horizontal em 390px e 1366px.
- Touch 44px em grid-mode e CTAs do banner (mobile validado).

---

## Recommended Actions

1. **[P1] `/impeccable harden pagina de categorias`:** IDs únicos no pager + spec E2E smoke.
2. **[P2] `/impeccable colorize pagina de categorias`:** Retirar hex de `promax`/`final-polish`; unificar tokens.
3. **[P2] `/impeccable typeset pagina de categorias`:** Normalizar nomes ERP (acentos + title case).
4. **[P2] `/impeccable optimize pagina de categorias`:** Reduzir inline CSS residual no head.
5. **[P2] `/impeccable adapt pagina de categorias`:** Pager 44px em viewports touch/coarse.
6. **[P3] `/impeccable layout pagina de categorias`:** Hero mobile: contagem dentro do overlay ou bloco próprio.
7. **`/impeccable polish pagina de categorias`:** Pass final após correções acima.

---

## Validação executada

| Probe | carcacas | retrovisores |
|-------|----------|--------------|
| HTML KB | 427 | 429 |
| `paging-label` dupes | 2 | 2 |
| Bottom slim | ✓ | ✓ |
| B2B banner | ✓ | ✓ |
| Scroll horizontal mobile | ✗ | — |
| Playwright touch mobile | 44px grid/banner | — |
