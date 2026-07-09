# Fase H0 — Header Functional QA — Tarefa 1: Inventário

**Data:** 2026-07-08
**Escopo:** Mapeamento read-only de todos os arquivos responsáveis pelos 14 componentes obrigatórios do header. Nenhuma alteração de código de produção foi feita neste documento.
**Tema ativo:** `AWA_Custom/ayo_home5_child` (pai: `ayo/ayo_home5`)

> Este inventário é insumo para a suíte de QA funcional (Tarefa 2/3). Não contém propostas de correção — apenas mapeamento e achados de estrutura.

---

## 0. Entry point do header

| Camada | Arquivo |
|---|---|
| Layout que monta o header | `app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/layout/default.xml` |
| Bloco raiz | `Rokanthemes\Themeoption\Block\Themeoption` (`name="toki_header"`) |
| Template raiz | `app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/header.phtml` (325 linhas) |
| View Models | `GrupoAwamotos\Theme\ViewModel\HeaderData`, `GrupoAwamotos\Theme\ViewModel\ContactInfo` |

O `header.phtml` renderiza **3 variantes** conforme `$headerMode`, calculado a partir da rota (`getFullActionName()`/`pathInfo`):

- `auth` — páginas de recuperação/criação de senha (`customer_account_forgotpassword`, `b2b_account_forgotpassword`, etc.) -> header minimalista, **sem** topbar, busca, menu ou minicart.
- `checkout` — `onepagecheckout_index_index`, `checkout_index_index` -> header minimalista com apenas logo + "Editar carrinho" + suporte.
- `default` — todas as demais rotas -> header completo (topbar B2B, busca, menu, minicart, etc.).

**Achado (H0):** `/b2b/register` e `/customer/account/login` (que redireciona 302 -> `/b2b/account/login/`) usam variantes reduzidas de header. É esperado que topbar, busca, menu vertical, menu principal e minicart **não apareçam** nessas rotas — isso deve ser tratado como comportamento intencional na suíte de QA, não como bug, a menos que o objetivo de produto seja diferente.

---

## 1. Topbar B2B (`awa-b2b-promo-bar`)

| Tipo | Caminho |
|---|---|
| PHTML (inline no header) | `Rokanthemes_Themeoption/templates/html/header.phtml` linhas 132-182 |
| Seletor raiz | `#awa-b2b-promo-bar` / `.awa-b2b-promo-bar` |
| CTA | `.awa-b2b-promo-bar__cta` -> `b2b/register` |
| Persistência de estado | `localStorage.awa_b2b_promo_dismissed` / `sessionStorage.awa_b2b_promo_dismissed_session` |
| JS de bootstrap/click | `web/js/awa-header-runtime-bootstrap.js` (defer, listener delegado) |
| CSS | `awa-bundle-core.css` (shell) + `web/css/source/_awa-header-*.less` (múltiplos, ver secao 15) |

## 2. Botão fechar da topbar

| Tipo | Caminho |
|---|---|
| Elemento | `#awa-b2b-promo-close` (`.awa-b2b-promo-close`) — `header.phtml:166` |
| Handler de click | Delegado via `awa-header-runtime-bootstrap.js` (script inline em `header.phtml:169-181` só faz o "hide instantâneo" se já dismissado, para evitar CLS) |

## 3. Logo

| Tipo | Caminho |
|---|---|
| Bloco | `Magento\Theme\Block\Html\Header\Logo` (`referenceBlock name="logo"` em `Rokanthemes_Themeoption/layout/default.xml:93`) |
| Template | `Rokanthemes_Themeoption/templates/html/header/logo.phtml` |
| Slot desktop | `.awa-header-brand-cell` -> `header.phtml:194-196` (`getChildHtml('logo')`) |
| Slot mobile/auth/checkout | mesmo child block reaproveitado nos 3 modos |

## 4. Busca

| Tipo | Caminho |
|---|---|
| Bloco | `Magento_Search::form.mini.phtml` (`name="awa.top.search" as="topSearch"`) — `Rokanthemes_Themeoption/layout/default.xml:50-55` |
| Slot no header | `.awa-header-search-col` -> `header.phtml:199-201` |
| Form | `#search_mini_form` |
| Input | `#search` / `input[data-awa-search-input="true"]` |
| Clear button | `web/js/awa-search-clear-init.js` |

## 5. Autocomplete da busca

| Tipo | Caminho |
|---|---|
| Compat/harden JS | `web/js/awa-search-autocomplete-compat.js`, `web/js/mixin/quicksearch-panel-harden.js` |
| Integração Mirasvit | `web/js/awa-mirasvit-autocomplete-init.js` |
| Toggle de estado (painel) | `web/js/awa-header-minicart-ui-v2.js` (apesar do nome, também trata `ctx.form.classList.toggle('is-open'/'has-results'/'is-empty')` e `aria-expanded` do autocomplete — linhas ~440-470) |
| Classe de estado global | `body.searchautocomplete__active` |

**Achado (H0):** o nome do arquivo `awa-header-minicart-ui-v2.js` sugere responsabilidade exclusiva de minicart, mas o arquivo também controla estado do autocomplete de busca — nomenclatura enganosa (relevante para uma futura reorganização, não é bug funcional).

## 6. Menu Departamentos (menu vertical)

| Tipo | Caminho |
|---|---|
| Bloco | `Rokanthemes\VerticalMenu\Block\Verticalmenu` (`name="menu.vertical"`) — `Rokanthemes_Themeoption/layout/default.xml:61-65` |
| Template | `Rokanthemes_VerticalMenu::sidemenu.phtml` (módulo vendor `Rokanthemes_VerticalMenu` — **não editar**) |
| Slot no header | `.awa-header-categories.menu_left_home1` -> `header.phtml:247-257` |
| Trigger | `[data-role="awa-vertical-menu-trigger"]` (`aria-expanded`) |
| Painel | `[data-role="awa-vertical-menu-panel"]` |
| Classes de estado (abrir) | `.menu-open`, `.vmm-open`, `.open` |
| JS runtime | `web/js/awa-header-nav-runtime.js`, `web/js/awa-menu-controller.js`, `web/js/awa-custom-menu-compat.js` |
| Fallback de render | `header.phtml:249-256` — se `top.navigation.sections` vier vazio, renderiza `menu.vertical` direto |

## 7. Menu principal (nav superior)

| Tipo | Caminho |
|---|---|
| Bloco/slot | `custom.topnav` (child block, populado por `Rokanthemes_CustomMenu`) |
| Slot no header | `.awa-header-primary-nav.menu_primary#awa-primary-navigation` -> `header.phtml:259-265` |
| Nav | `.top-menu.top-menu-sticky` |
| Layout | `Rokanthemes_CustomMenu/layout/default.xml` |

## 8. Link Lançamentos

| Tipo | Caminho |
|---|---|
| Origem do link | Item de menu (`custom.topnav` / categoria "Lançamentos") — conteúdo dinâmico, não hardcoded no header.phtml |
| Quick links | `awaNavQuickLinks` -> `Magento_Theme::html/awa-nav-quick-links.phtml` (linha 266) pode conter atalho para Lançamentos |

**Achado (H0):** `/lancamentos.html` responde **301** -> `/lancamentos` (redirect permanente, canônico — comportamento correto, não é bug). Confirmado via `curl -I` em produção.

## 9. Painel B2B / status do cliente

| Tipo | Caminho |
|---|---|
| Bloco (site-wide) | `GrupoAwamotos\B2B\Block\Header\StatusPanel` como `awaB2bModeBadge`, template `Magento_Theme::html/b2b-mode-badge.phtml` — definido em **`app/design/frontend/ayo/ayo_home5/GrupoAwamotos_B2B/layout/default.xml`** (tema PAI) |
| Bloco (customer_account) | Mesmo bloco, template sobrescrito para `GrupoAwamotos_B2B::header/status-panel.phtml` em `AWA_Custom/ayo_home5_child/GrupoAwamotos_B2B/layout/customer_account.xml` |
| Template completo (painel expandido) | `app/code/GrupoAwamotos/B2B/view/frontend/templates/header/status-panel.phtml` (dropdown com crédito, grupo, empresa) — cópia também em `app/design/frontend/AWA_Custom/ayo_home5_child/GrupoAwamotos_B2B/templates/header/status-panel.phtml` |
| Slot no header | `header.phtml:204` -> `getChildHtml('awaB2bModeBadge')` |
| JS | `app/code/GrupoAwamotos/B2B/view/frontend/web/js/header-status-panel.js`, `b2b-panel-hydrate.js` |
| CSS | `app/code/GrupoAwamotos/B2B/view/frontend/web/css/header/status-panel.css` |
| Seletores | `.b2b-status-panel`, trigger `.b2b-status-trigger[aria-expanded]` `aria-controls="b2b-status-dropdown"`, ícone `user`, discount badge `.b2b-status-trigger__discount` |

**Achado (H0 — arquitetura, NAO é bug):** a definição site-wide do badge B2B vive no **tema pai** (`app/design/frontend/ayo/ayo_home5/...`), não no tema filho `AWA_Custom`. Isso diverge da convenção "toda alteração no tema filho" documentada na skill AWA — qualquer ajuste futuro nesse bloco fora de `customer_account` exigiria tocar no tema pai (proibido pelas regras do projeto) ou criar um override equivalente no filho. Registrado como pendência arquitetural para fase futura. Nenhuma alteração feita.

## 10. Dropdown de conta

| Tipo | Caminho |
|---|---|
| Slot no header | `nav.top-account.awa-header-account-nav` (`hidden` por padrão até hidratar) -> `header.phtml:205-210` |
| Conteúdo | `getChildHtml('top.links')` (bloco core `Magento_Customer` — link My Account/Logout + itens injetados) |
| Normalização de nome | `GrupoAwamotos\B2B\Helper\Data::formatDisplayName()` (ver `header-greeting-casing.spec.ts`) |
| JS runtime | `web/js/awa-header-customer-runtime.js`, `web/js/awa-header-account-prompt.js` |
| Seletor | `.top-account.awa-header-account-nav`, `.awa-header-account-prompt` |

## 11. Minicart

| Tipo | Caminho |
|---|---|
| Bloco core | `Magento_Checkout::cart/minicart.phtml` (override em `AWA_Custom/ayo_home5_child/Magento_Checkout/templates/cart/minicart.phtml`) |
| Wrapper no header | `.awa-header-cart.mini-cart-wrapper.shadowcart.awa-header-minicart` -> `header.phtml:211-232` |
| Fallback (antes de KO hidratar) | `.awa-header-cart-fallback` (`aria-expanded`, `aria-controls="awa-minicart-panel"`) |
| Painel | `#awa-minicart-panel` (`data-awa-header-minicart-content`) |
| Ícone raiz do header (mobile shortcut) | `.awa-header-cart-link` (linha 197) + badge `.awa-cart-link-badge` |
| Estado inicial (antes de hidratar) | `data-awa-minicart-ready="0"`, `data-awa-minicart-expanded="0"` |
| JS | `web/js/awa-header-minicart-ui-v2.js`, `web/js/awa-minicart-defer-init.js`, `web/js/awa-minicart-ui-bootstrap.js`, `web/js/cart-simplified-header.js` (checkout) |
| Progresso de pedido mínimo B2B | `GrupoAwamotos_B2B/templates/cart/min-order-progress-minicart.phtml` |
| Contador | `[data-awa-header-minicart-shell="true"] .counter.qty` |

## 12. Modal/drawer mobile

| Tipo | Caminho |
|---|---|
| Toggle (hambúrguer) | `.awa-header-mobile-toggle[data-awa-nav-toggle="true"]` -> `header.phtml:193` |
| JS principal | `web/js/awa-mobile-nav-v2.js` (ativo) — **existe também** `web/js/awa-mobile-nav.js` (v1, possível legado/dead code — confirmar antes de remover em fase futura) |
| Classes de estado (body) | `body.nav-open`, `body.nav-before-open`, `body.awa-mobile-drawer-open` |
| Fechar | `.awa-nav-close`, `.toggle-nav-footer` |
| Accordion interno (categorias no drawer) | `web/js/awa-mobile-accordion.js` |
| CSS | `web/css/source/components/_mobile-header.less`, `web/css/awa-header-mobile-grid-critical.css` |

## 13. Sticky header

| Tipo | Caminho |
|---|---|
| Wrapper | `.header-wrapper-sticky` (`data-awa-sticky-container="true"`) -> `header.phtml:186` |
| JS | `web/js/awa-header-sticky.js` / `web/js/awa-sticky-header.js` (**dois arquivos com propósito aparentemente sobreposto** — achado H0, confirmar redundância antes de qualquer merge futuro) |
| Classes de estado | `body.awa-header-is-sticky`, `.header-wrapper-sticky.is-sticky`, `.awa-header-condensed`, `.awa-scroll-down`/`.awa-scroll-up` |
| CLS placeholder | `#awa-header-cls-placeholder` -> `header.phtml:325` (reserva altura durante transição para `position:fixed`) |
| Guardas (não ativar sticky) | `body.awa-account-operational`, `body.b2b-account-shell` |

## 14. PWA install modal

| Tipo | Caminho |
|---|---|
| Template | `Magento_Theme::html/awa-pwa-install.phtml` (referenciado em `app/code/AWA/VisualFixes/view/frontend/layout/default.xml:30`) |
| Seletor | `[data-awa-pwa-install]` (`role="dialog"`, inicia `hidden`) |
| Trigger real | `window.addEventListener('beforeinstallprompt', ...)` **global**, gate por `visitType === 'returning'` + `sessionStorage` + 45s de sessão mínima; também dispara via detecção de standalone no iOS |
| Fechar/aceitar | `[data-awa-pwa-install-close]`, `[data-awa-pwa-install-accept]` |

**Achado (H0 — importante para a suíte):** o modal **não é disparado por nenhum elemento do header** (não há botão "Instalar app" no header). É um modal global agendado por `window.__awaRequestHomeModal`, condicionado ao evento `beforeinstallprompt` (Chromium/Android; ausente em Firefox e no Chromium do Playwright sem simulação manual) e a "visitante retornante" (`localStorage.awa-home-visited-before`). Compete com outro modal (`type: 'notification'`) pelo mesmo agendador. **Escopo para H0:** validar apenas que, quando simulado via evento sintético, o modal abre/fecha corretamente e não quebra o header — não há dependência funcional real do header.

---

## 15. Bundles CSS/LESS críticos (não exaustivo — cruzar com `docs/visual-qa/dead-css-manifest-2026-07-08.md` para status morto/vivo)

Fora de escopo do H0 (NÃO tocar agora — só registro de volume/contexto):

- 50+ arquivos `_awa-header-*.less` em `web/css/source/` com sufixos de data (`-2026-05`, `-2026-06`, `-refine`, `-hotfix`, `-polish`, `-terminal`, `-clean`, `-professional`, `-vtex-*`) — forte indício de iterações acumuladas sem consolidação.
- `web/css/source/_deprecated/_awa-bundle-header.less` e `_awa-header-overlay-state-fix.less` já isolados como deprecated.
- Bundles publicados relevantes: `awa-bundle-core.css` (topbar/shell), `awa-header-contract-grid-20260626.css`, `awa-header-mobile-grid-critical.css`, `awa-header-home-light-lock-v1.css`, `awa-ui-ux-pro-max-header-2026-05-19.css`.
- Fragmentos PHP injetados no head/body para lock crítico: `Magento_Theme/templates/html/awa-header-*-critical-fragment.phtml`, `awa-header-early-sticky-sync.phtml`, `awa-header-impeccable-critical-global.phtml`, `awa-header-home-hotfix-home.phtml`.

## 16. CMS blocks referenciados no layout do header

| Identifier | Título (CMS) | Ativo | Renderizado no header.phtml? |
|---|---|---|---|
| `top-contact` | Top Contact | Sim | **Não** — bloco declarado em `default.xml:26-30` mas sem `getChildHtml('top-contact')` correspondente no template atual |
| `top-left-static` | "HEADER - Barra superior - CORRIGIRRRRRRRRRRRRRR" | Sim | **Não** — idem acima; conteúdo (telefone/horário) confirmado ausente no HTML renderizado de produção via `curl` |
| `hotline_header` | Hotline Header | Sim | Sim, mas **apenas no modo `checkout`** (`$checkoutSupportHtml`) — não aparece no header `default` |

**Achado (H0):** `top-contact` e `top-left-static` parecem ser blocos CMS órfãos (registrados no layout, sem output no template atual). O título do segundo bloco (`CORRIGIRRRRRRRRRRRRRR`) sinaliza um item de conteúdo administrativo pendente de revisão — fora do escopo de código. Reportar ao time de conteúdo/produto. Nenhuma alteração feita.

## 17. Testes E2E já existentes que tocam o header (para evitar duplicação)

| Spec | Foco atual |
|---|---|
| `tests/e2e/specs/functional/func-header.spec.ts` | Estrutura básica, só home |
| `tests/e2e/specs/smoke/header.spec.ts` | Smoke básico, só home |
| `tests/e2e/specs/header-layout.spec.ts` (604 linhas) | Layout/CSS profundo |
| `tests/e2e/specs/header-all-pages-audit.spec.ts` | Métricas de bounding box por página/viewport (9 rotas x 3 viewports) — **não cobre interação** (clique, teclado, aria) |
| `tests/e2e/specs/header-home-layout.spec.ts` | Layout específico da home |
| `tests/e2e/specs/header-greeting-casing.spec.ts` | Regressão de casing do nome no painel B2B |
| `tests/e2e/specs/deep-visual/header.visual.spec.ts` | Screenshot diff |
| `tests/e2e/specs/vertical-menu.spec.ts`, `functional/func-menu-vertical.spec.ts` (753 linhas) | Menu vertical, já bem coberto |
| `tests/e2e/helpers/header.helpers.ts` | Seletores/utilitários compartilhados (reaproveitados na nova suíte H0) |

**Conclusão do inventário:** a lacuna real está em QA funcional/interação cross-breakpoint x cross-rota (clique, teclado, `aria-expanded`, foco, fechamento) dos 14 componentes — é isso que a suíte `fase-h0-header-functional-qa.spec.ts` (Tarefa 2/3) cobre, sem duplicar os specs de layout/visual já existentes.
