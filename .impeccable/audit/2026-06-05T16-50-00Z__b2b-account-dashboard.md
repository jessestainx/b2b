# Audit: B2B Account Dashboard

**URL:** `/b2b/account/dashboard/`  
**Audit date:** 2026-06-05  
**Method:** Source audit + live guest redirect/CSS verification (authenticated HTML requires B2B session)  
**Body (autenticado):** `account b2b-account-dashboard page-layout-2columns-left`  
**Terminal CSS:** `dashboard-impeccable-audit.css` + `awa-b2b-dashboard-late.css` via `b2b-dashboard-css.phtml` (`?v=20260605-impeccable-v8`)  
**Constraint:** Document-only; no fixes applied in this pass

---

## Audit Health Score

| # | Dimension | Score | Key Finding |
|---|-----------|-------|-------------|
| 1 | Accessibility | 3 | Focus-visible e tabelas com `aria-label` sólidos; gaps em hierarquia de headings, `caption` ausente em cotações, alguns alvos < 44px |
| 2 | Performance | 3 | Lazy panels + `content-visibility`; stack CSS pesado (~8.6k linhas, 1181× `!important` no terminal) |
| 3 | Responsive Design | 3 | Breakpoints 767/991px e scroll horizontal em tabelas; touch targets 36–40px em controles secundários |
| 4 | Theming | 3 | Tokens `--awa-*` dominam no terminal; hex legado persiste em camadas base e fallbacks |
| 5 | Anti-Patterns | 3 | Terminal neutraliza side-stripe e hero-metric; `suggestions-grid` e discount badge ainda ecoam padrões SaaS |
| **Total** | | **15/20** | **Good** |

**Rating band:** Good (14–17). Infraestrutura de layout/CSS corrigida desde auditoria 2026-05-30; débito técnico restante é consolidável.

---

## Anti-Patterns Verdict

**Pass with reservations.**

O painel não lê como landing SaaS genérica: sidebar flex, KPI strip plano, ERP em lista/flex, side-stripe removido no terminal (`border-left: 0 !important` em `.block-collapsible-nav .item.current` e afins). Paleta AWA vermelho/cinza, não cream AI-default.

**Tells remanescentes (subtis):**

1. `suggestions-grid` + `suggestion-card` — grid de cards icon+heading+text (mitigado por copy específica B2B, mas estrutura reconhecível).
2. `.b2b-discount-badge` — número grande + label pequeno (mini hero-metric; parcialmente achatado no terminal).
3. Múltiplos `text-transform: uppercase` + `letter-spacing` em labels de tabela/status (aceitável em badges curtos, mas frequente em 15+ seletores no terminal).
4. `dashboard-visual-upgrade.css:820` — `border-left: 3px solid #b73337` ainda na origem (terminal sobrescreve, mas dívida permanece).

---

## Executive Summary

- **Audit Health Score: 15/20 (Good)**
- **Issues:** P0: 0 · P1: 1 · P2: 7 · P3: 4
- **Top issues:**
  1. Stack CSS em 7 arquivos merge + 2 late links — custo de parse/cascade e 1181 `!important` no terminal
  2. Touch targets 36–40px em pills/toolbar/checklist (abaixo de 44px WCAG 2.5.5)
  3. Hierarquia de headings quebrada (`h1` → `h4` no banner ERP antes de `h2` onboarding)
  4. `quotes-table` sem `<caption class="sr-only">` (inconsistente com `orders-table`)
  5. Atualizações AJAX sem `aria-live` (só `aria-busy` / `role="alert"` em erro)
- **Guest flow:** 302 → `/b2b/account/login/` ✓ (corrigido via `GuestLoginRedirect`)
- **Live CSS terminal:** publicado em `pub/static/.../dashboard-impeccable-audit.css` ✓

---

## Detailed Findings by Severity

### P1

**[P1] CSS stack excessivo e guerra de especificidade**  
- **Location:** `b2b_account_index.xml` head (5 CSS) + `b2b-dashboard-css.phtml` (2 late) + tema global (~10 bundles async)  
- **Category:** Performance  
- **Impact:** First paint e recálculo de estilo mais lentos; manutenção frágil (terminal precisa de `!important` para vencer camadas anteriores).  
- **WCAG/Standard:** —  
- **Recommendation:** Consolidar camadas base num único bundle de origem; reduzir terminal a delta real; medir no DevTools Coverage autenticado.  
- **Suggested command:** `/impeccable optimize b2b-account-dashboard`

### P2

**[P2] Touch targets abaixo de 44×44px**  
- **Location:** `dashboard-impeccable-audit.css` (min-height 36–40px), `dashboard-visual-upgrade.css`, `awa-b2b-dashboard-late.css`  
- **Category:** Responsive / Accessibility  
- **Impact:** Dificuldade de toque em mobile para chips, pills da toolbar e itens de checklist.  
- **WCAG/Standard:** WCAG 2.5.5 Target Size (Level AAA; boa prática AA em B2B mobile)  
- **Recommendation:** Subir `min-height`/`min-width` para 44px nos controles interativos secundários sem inflar padding de células de tabela.  
- **Suggested command:** `/impeccable adapt b2b-account-dashboard`

**[P2] Hierarquia de headings inconsistente**  
- **Location:** `dashboard.phtml` L60 `h1`, L101 `h4` (ERP pending), L121 `h2`, L160+ múltiplos `h3`/`h4`  
- **Category:** Accessibility  
- **Impact:** Leitores de tela anunciam estrutura confusa; navegação por headings menos previsível.  
- **WCAG/Standard:** WCAG 1.3.1 Info and Relationships  
- **Recommendation:** Promover banner ERP para `h2`; reservar `h4` apenas dentro de seções com `h3` pai; ou usar classes visuais sem mudar nível semântico.  
- **Suggested command:** `/impeccable harden b2b-account-dashboard`

**[P2] Tabela de cotações sem caption sr-only**  
- **Location:** `dashboard.phtml` L537 `quotes-table`  
- **Category:** Accessibility  
- **Impact:** Inconsistência com `orders-table` (L450 tem caption); SR users perdem contexto redundante útil.  
- **WCAG/Standard:** WCAG 1.3.1  
- **Recommendation:** Adicionar `<caption class="sr-only">` espelhando padrão de pedidos.  
- **Suggested command:** `/impeccable polish b2b-account-dashboard`

**[P2] SVGs decorativos sem `aria-hidden="true"`**  
- **Location:** `dashboard.phtml` — ícones inline em onboarding, seções, ações rápidas  
- **Category:** Accessibility  
- **Impact:** Ruído em leitores de tela quando o texto adjacente já descreve a ação.  
- **WCAG/Standard:** WCAG 1.1.1 (decorative)  
- **Recommendation:** `aria-hidden="true"` + `focusable="false"` em SVGs ao lado de texto visível.  
- **Suggested command:** `/impeccable polish b2b-account-dashboard`

**[P2] Side-stripe legado na origem**  
- **Location:** `dashboard-visual-upgrade.css:820` `border-left: 3px solid #b73337`  
- **Category:** Anti-Pattern  
- **Impact:** Terminal corrige em runtime, mas regressão possível se ordem de CSS mudar.  
- **Recommendation:** Remover na origem; manter override terminal só como rede de segurança temporária.  
- **Suggested command:** `/impeccable quieter b2b-account-dashboard`

**[P2] Animação de propriedade de layout**  
- **Location:** `awa-b2b-dashboard-late.css:150` `transition: width` (detect.mjs `layout-transition`)  
- **Category:** Performance  
- **Impact:** Possível layout thrash em barras de progresso/crédito em dispositivos lentos.  
- **Recommendation:** Usar `transform: scaleX()` com `transform-origin: left` em `.progress-fill` / `.credit-bar-fill`.  
- **Suggested command:** `/impeccable optimize b2b-account-dashboard`

**[P2] Grid de sugestões tipo card-template**  
- **Location:** `dashboard.phtml` L587–602 `.suggestions-grid` / `.suggestion-card`  
- **Category:** Anti-Pattern  
- **Impact:** Visualmente próximo de “identical card grids” quando há 3+ sugestões.  
- **Recommendation:** Lista vertical com separadores (já parcialmente feito em ERP); alinhar sugestões Magento ao mesmo vocabulário.  
- **Suggested command:** `/impeccable distill b2b-account-dashboard`

### P3

**[P3] AJAX sem região `aria-live`**  
- **Location:** `dashboard-async.js` — `renderOrders`, `renderQuotes`  
- **Category:** Accessibility  
- **Impact:** Conteúdo dinâmico pode não ser anunciado após carregamento bem-sucedido.  
- **Recommendation:** `aria-live="polite"` em `[data-dashboard-section="orders"]` e contadores de cotações.  
- **Suggested command:** `/impeccable harden b2b-account-dashboard`

**[P3] Possível landmark duplicado**  
- **Location:** `dashboard.phtml` L51 `role="main"` dentro de layout `2columns-left`  
- **Category:** Accessibility  
- **Impact:** Dois landmarks `main` se o tema já expõe `<main>`.  
- **Recommendation:** Verificar HTML autenticado; trocar para `role="region"` + `aria-labelledby` no h1 se duplicado.  
- **Suggested command:** `/impeccable audit b2b-account-dashboard` (re-run autenticado)

**[P3] Hex hardcoded em fallbacks e PHTML**  
- **Location:** Terminal CSS fallbacks `#92400e`, `#fef3c7`; PHTML `--b2b-rfm-color` default `#6b7280`  
- **Category:** Theming  
- **Impact:** Tokens AWA não propagam em edge cases; RFM cinza fora da paleta.  
- **Recommendation:** Mapear RFM para tokens semânticos `--awa-warning-*` / `--awa-text-muted`.  
- **Suggested command:** `/impeccable colorize b2b-account-dashboard`

**[P3] Validação visual autenticada pendente**  
- **Location:** Live `/b2b/account/dashboard/`  
- **Category:** Responsive  
- **Impact:** Audit baseado em código; overflow/CLS em 390px não confirmados com screenshot logado.  
- **Recommendation:** Hard refresh logado; validar 390/768/1366/1920px.  
- **Suggested command:** `/impeccable audit b2b-account-dashboard` (pós-login)

---

## Patterns & Systemic Issues

1. **Camadas CSS paralelas** — `dashboard.css` → `distill` → `refinement` → `visual-upgrade` → `layout-reset` → `late` → `impeccable-audit`. Padrão “terminal vence” funciona mas escala mal.
2. **`!important` como ferramenta de merge** — 1181 ocorrências no terminal indicam conflito estrutural, não pontual.
3. **Uppercase tracking em status labels** — repetido em refinement + terminal; voz de produto aceitável mas frequente.
4. **Inconsistência tabela** — orders tem caption; quotes não; padrão a11y deveria ser uniforme.

---

## Positive Findings

- **Redirect guest correto:** `GuestLoginRedirect` → `/b2b/account/login/` com `beforeAuthUrl` (verificado live 2026-06-05).
- **Body class + layout inheritance:** `b2b-account-dashboard` via XML + controller; dashboard herda `b2b_account_index` (anti-drift).
- **Terminal bundle robusto:** contraste pending badge documentado e corrigido; focus-visible extensivo; side-stripe neutralizado.
- **`prefers-reduced-motion`** presente em distill, refinement, visual-upgrade e terminal.
- **`dashboard-async.js`:** empty states, `role="alert"` em erro, `aria-busy` em lazy panels, escape HTML em render AJAX.
- **Tabelas ERP:** `aria-label` + caption sr-only em pedidos; scroll horizontal em mobile.
- **Layout reset:** `awa-b2b-dashboard-layout-reset.css` impõe flex shell scoped.

---

## Recommended Actions

1. **[P1] `/impeccable optimize b2b-account-dashboard`:** Medir e reduzir stack CSS; trocar `transition: width` por transform.
2. **[P2] `/impeccable adapt b2b-account-dashboard`:** Touch targets 44px em toolbar, chips e checklist mobile.
3. **[P2] `/impeccable harden b2b-account-dashboard`:** Corrigir heading hierarchy; `caption` em quotes; `aria-live` no AJAX.
4. **[P2] `/impeccable distill b2b-account-dashboard`:** Converter `suggestions-grid` para lista; alinhar vocabulário visual ERP/Magento.
5. **[P2] `/impeccable quieter b2b-account-dashboard`:** Remover side-stripe e uppercase excessivo na origem (`visual-upgrade`, `refinement`).
6. **[P3] `/impeccable polish b2b-account-dashboard`:** `aria-hidden` em SVGs; landmarks; polish final pós-fixes.

Re-run `/impeccable audit b2b-account-dashboard` após fixes, preferencialmente com sessão B2B autenticada.

---

*Detector hits: 1× `layout-transition` em `awa-b2b-dashboard-late.css:150`.*
