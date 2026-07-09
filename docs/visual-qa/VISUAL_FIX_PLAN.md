# Plano de Correção Visual — AWA Motos (multi-página)

Última atualização: 2026-07-09
Responsável: agente/humano
Ambiente principal: staging → produção
Tema: `AWA_Custom/ayo_home5_child`
Escopo: componentes globais (Header, Footer) + Home (`/`) + Categoria/PLP — Bagageiros (`/bagageiros.html`).

Este é o **documento mestre multi-página**. Substitui `HOME_VISUAL_FIX_PLAN.md` (v1, Home-only), que foi
descontinuado nesta migração — nenhum conteúdo foi perdido, apenas reorganizado por componente/página (ver
seção [Migração v1 → v2](#migração-v1--v2) abaixo). O arquivo companheiro
[`visual-fix-status.yml`](./visual-fix-status.yml) espelha os mesmos itens em formato estruturado. Critérios
de fechamento em [`visual-fix-definition-of-done.md`](./visual-fix-definition-of-done.md). Regras de
governança para o agente em [`visual-fix-agent-instructions.md`](./visual-fix-agent-instructions.md),
reforçadas por `.cursor/rules/awa-visual-governance.mdc`.

## Migração v1 → v2

| Antes (v1, Home-only) | Agora (v2) | Motivo |
|---|---|---|
| `HOME-P0-003` — Header/minicart/conta | `HEADER-P0-001` | Header é componente global, não exclusivo da Home |
| `HOME-P0-004` — Menu Departamentos | `HEADER-P0-002` | idem |
| `HOME-P0-005` — Footer contraste baixo | Absorvido por `FOOTER-P0-001` | Mesmo componente global, mesma causa raiz a investigar |
| `HOME-P0-006` — Carrosséis quebrados | `HOME-P0-003` (renumerado) | Item Home-específico, apenas renumerado após remoção dos 3 itens acima |

Nenhum critério de aceite foi removido — apenas consolidado sob o componente correto, para evitar rastrear o
mesmo bug duas vezes (um em "Home" e outro em "PLP") quando a causa raiz é o mesmo Header ou Footer global.

## Regras obrigatórias

1. Não alterar `vendor/`.
2. Não alterar `app/code/Rokanthemes/*` nem Luma/Blank diretamente.
3. Não usar `pub/static` ou `var/view_preprocessed` como fonte canônica de edição — apenas destino de deploy.
4. Toda correção permanente deve estar em `app/code`, `app/design`, layout XML, template `.phtml`, LESS/CSS/JS fonte (`web/css/source/`, `web/js/`) ou CMS block documentado.
5. Mudança **estrutural** (remoção/realocação de blocos como sidebar, widgets, toolbar, header, footer) → layout XML/block/template. Mudança **visual** (cor, espaçamento, radius, tipografia) → LESS/CSS do tema customizado. Nunca misturar as duas coisas no mesmo commit sem necessidade.
6. Não misturar correção visual com a limpeza de CSS morto (`.cursor/rules/awa-css-governance.mdc`, Fase 6A).
7. Não criar arquivo CSS/LESS com data ou sufixo de versão no nome — editar o bundle/arquivo já responsável pelo domínio.
8. Não usar `!important` sem comentário explicando o motivo.
9. Não marcar item como corrigido sem evidência (screenshots antes/depois).
10. Não marcar item como testado sem Playwright ou validação manual documentada.
11. Não marcar item como fechado se houver erro novo em console, rede, `exception.log` ou `system.log`.
12. **Tokens de design já existem** em `web/css/source/_tokens.less` (`--awa-radius-2xs/xs/sm/md/lg/pill/full`, `@awa-space-1..8`). Auditar e aplicar — não criar um terceiro sistema de tokens.
13. Um bug em componente **global** (Header, Footer) só tem um ID — mesmo que apareça em várias páginas. Não recriar o mesmo bug com ID diferente por página onde for observado; apenas referenciar o ID global e, se necessário, anotar a página onde foi observado no campo `note`.

## Status permitidos

| Status | Significado |
|---|---|
| `TODO` | ainda não iniciado / não reproduzido visualmente |
| `REPRODUCED` | bug reproduzido com evidência (screenshot) |
| `IN_PROGRESS` | correção em andamento |
| `FIXED_SOURCE` | correção aplicada na fonte canônica |
| `DEPLOYED_STAGING` | publicado em staging |
| `TESTED_LOCAL` | testado localmente |
| `TESTED_CI` | testado via GitHub Actions/Playwright |
| `VERIFIED_PROD` | validado em produção |
| `BLOCKED` | bloqueado, com motivo registrado |
| `WONTFIX` | não será corrigido, com justificativa |
| `CLOSED` | corrigido, testado e documentado |

## Definition of Done (resumo)

Ver checklist completo em `visual-fix-definition-of-done.md`. Resumo: causa raiz registrada (arquivo+linha),
correção na fonte canônica, screenshots antes/depois, Playwright local/CI, sem 404/403 novo, sem erro novo em
console/`exception.log`/`system.log`, testado desktop **e** mobile, commit/PR vinculado.

---

## Visão geral por fase

### Fase 0 — Preparação (governança)

| ID | Descrição | Status |
|---|---|---|
| `VISUAL-SETUP-001` | Criar documento mestre | `CLOSED` |
| `VISUAL-SETUP-002` | Criar YAML de status | `CLOSED` |
| `VISUAL-SETUP-003` | Criar pasta de evidências | `CLOSED` |
| `VISUAL-SETUP-004` | Criar specs Playwright base (Home + PLP baseline) | `CLOSED` |
| `VISUAL-SETUP-005` | Configurar upload de artifacts no GitHub Actions | `TODO` |
| `VISUAL-SETUP-006` | Migrar doc/YAML de Home-only para multi-página | `CLOSED` |

### P0 — Corrigir primeiro (16 itens)

| ID | Título | Página/Componente |
|---|---|---|
| `HEADER-P0-001` | Header/minicart/conta inconsistentes | Global |
| `HEADER-P0-002` | Menu Departamentos desalinhado/quebrado | Global |
| `FOOTER-P0-001` | Footer principal quebrado em blocos vermelhos | Global |
| `HOME-P0-001` | Botão B2B duplicado/sobreposto | Home |
| `HOME-P0-002` | Product cards com imagem ausente/placeholder vazio | Home |
| `HOME-P0-003` | Carrosséis com controles desalinhados/quebrados | Home |
| `PLP-P0-001` | Header de categoria com banner/placeholder vazio | PLP |
| `PLP-P0-002` | Espaço vazio excessivo entre categoria e toolbar | PLP |
| `PLP-P0-003` | Ícone/loader solto na área vazia | PLP |
| `PLP-P0-004` | Filtros vazios ou quebrados | PLP |
| `PLP-P0-005` | Widget "Pedidos feitos recentemente" indevido na PLP | PLP |
| `PLP-P0-006` | Botões duplicados no widget lateral | PLP |
| `PLP-P0-007` | Cards de produto sem hierarquia mínima | PLP |
| `PLP-P0-008` | Cards de produto sem CTA principal | PLP |
| `PLP-P0-009` | Ausência de preço/contexto B2B | PLP |
| `PLP-P0-010` | Mobile/filtro sem comportamento definido | PLP |

### P1 — Padronização (26 itens)

| ID | Título |
|---|---|
| `HEADER-P1-001` | Topbar B2B com aparência pesada |
| `FOOTER-P1-001`…`006` | Faixa benefícios · Newsletter · Endereço · Ícones sociais · Categorias · Pagamento |
| `HOME-P1-001`…`007` | Radius · Benefícios · Product card · Categorias · Spacing · Tipografia · CTA B2B |
| `PLP-P1-001`…`012` | Breadcrumb · Toolbar · Botão filtros · Grid/list a11y · Grid produtos · Imagens · Bordas · Badge · Paginação · Espaço footer · Container global · Acessibilidade |

### P2 — Modernização (11 itens)

| ID | Título |
|---|---|
| `FOOTER-P2-001` | Texto legal/CNPJ |
| `HOME-P2-001`…`006` | Hero HTML · Menos carrosséis · Pedidos recentes condicional · Footer moderno · Art direction · Lighthouse/CLS |
| `PLP-P2-001`…`004` | Header SEO · Filtro com chips · Product card B2B avançado · Quick actions |

---

## Componentes Globais

### Header

#### HEADER-P0-001 — Header/minicart/conta inconsistentes

Status: `TODO` · Prioridade: P0 · Componente: Header global · Rota: todas
Branch: `fix/header-footer-global-p0`

**Problema:** ícone/controle do minicart aparece como bloco vermelho pouco informativo; área de conta
("Olá, Fernando / B2B Atacado") aparece apertada e pequena em relação ao restante do header.

**Causa raiz:** a investigar. Ponto de partida — spec de diagnóstico já existente:

```29:44:tests/e2e/specs/header-core-interactions-p0.spec.ts
minicartShell: '.awa-header-minicart[data-awa-header-cart="true"]',
minicartFallback: '.awa-header-cart-fallback',
minicartShowcart: '.minicart-wrapper .showcart, .action.showcart.header-mini-cart',
accountPrompt: '.awa-header-account-prompt',
accountNav: '.top-account.awa-header-account-nav',
```

**Arquivos suspeitos:** `Rokanthemes_Themeoption/templates/html/header.phtml`,
`Magento_Theme/templates/html/awa-header-desktop-grid-critical-fragment.phtml`, `web/css/source/_minicart.less`.

**Critério de aceite:** ícone do minicart visível em todas as larguras · badge de contador quando carrinho
não vazio · área de conta com altura mínima 40–44px · grid claro logo/busca/conta/minicart ·
`border-radius: var(--awa-radius-md)`.

**Evidência:** `docs/visual-qa/evidence/HEADER-P0-001/` · Playwright: `header-core-interactions-p0.spec.ts`,
`home-visual-regression.spec.ts`, `plp-visual-baseline.spec.ts`.

| Data | Status | Autor | Observação |
|---|---|---|---|
| 2026-07-09 | TODO | agente | migrado de HOME-P0-003 (v1); observado também na PLP Bagageiros |

---

#### HEADER-P0-002 — Menu Departamentos desalinhado/quebrado

Status: `TODO` · Prioridade: P0 · Componente: Header / Menu vertical · Rota: todas
Branch: `fix/header-footer-global-p0`

**Problema:** botão "Departamentos" domina a linha de navegação sem proporção controlada; links "Nossas
Marcas"/"Lançamentos"/"Catálogo" ficam pequenos e distantes; menu aberto pode cortar itens por `z-index`.

**Arquivos suspeitos:** `Rokanthemes_VerticalMenu/templates/sidemenu.phtml`, `web/css/source/_vertical-menu.less`, `web/css/source/_z-index.less`.

**Critério de aceite:** botão com proporção fixa/controlada · nav secundária alinhada à mesma altura ·
menu aberto exibe todos os itens sem corte · abre/fecha corretamente (click/ESC/click-fora).

**Evidência:** `docs/visual-qa/evidence/HEADER-P0-002/`.

| Data | Status | Autor | Observação |
|---|---|---|---|
| 2026-07-09 | TODO | agente | migrado de HOME-P0-004 (v1); observado também na PLP Bagageiros |

---

#### HEADER-P1-001 — Topbar B2B com aparência pesada

Status: `TODO` · Prioridade: P1 · Componente: Header / Topbar B2B · Rota: todas

**Problema:** borda/contorno escuro na topbar, texto pequeno, "X" de fechar pouco perceptível — parece aviso
de sistema, não faixa comercial.

**Correção:** remover borda escura excessiva · altura compacta · área clicável do fechar ≥ 32px · persistir
dismissal (localStorage/cookie) · simplificar mensagem.

**Evidência:** `docs/visual-qa/evidence/HEADER-P1-001/`.

---

### Footer

#### FOOTER-P0-001 — Footer principal quebrado em blocos vermelhos

Status: `REPRODUCED` · Prioridade: P0 · Componente: Footer global · Rota: todas
Branch: `fix/header-footer-global-p0`

**Problema:** colunas "Quem somos", "Suporte e Segurança" e "Atendimento" aparecem como grandes blocos
vermelhos separados com texto branco pequeno — parece falha de CSS/layout, não design intencional.

**Comportamento esperado:** footer como seção única, fundo consistente, colunas transparentes sem boxes
vermelhos individuais, headings com contraste e spacing adequados.

**Causa raiz:** a investigar — hipótese: alguma regra de `.block`/coluna do footer herdando
background vermelho de outro componente (ex.: faixa de benefícios/CTA) por especificidade CSS, ou classe
de card aplicada incorretamente por coluna.

**Arquivos suspeitos:** `Rokanthemes_Themeoption/templates/html/footer.phtml`,
`Rokanthemes_Themeoption/templates/html/footer/footer-static5.phtml`, `web/css/source/_footer.less` (linhas
45–65 já usam `@footer-surface`/`@footer-text`, mas não explicam blocos vermelhos por coluna — provavelmente
outro arquivo CSS concorrente está sobrepondo; mapear com DevTools qual regra realmente vence).

**Critério de aceite:** sem colunas em caixas vermelhas quebradas · fundo consistente · contraste ≥ 4.5:1 ·
links legíveis · sem regressão nas demais páginas que compartilham o footer global.

> Nota: absorve a preocupação de contraste antes registrada isoladamente em `HOME-P0-005` (v1) — mesmo
> componente global, mesma causa raiz a confirmar.

**Evidência:** `docs/visual-qa/evidence/FOOTER-P0-001/` · Playwright: `accessibility.spec.ts`,
`footer-contrast-probe.spec.ts` (já existentes), `plp-visual-baseline.spec.ts`.

| Data | Status | Autor | Observação |
|---|---|---|---|
| 2026-07-09 | REPRODUCED | agente | reproduzido via screenshot da PLP Bagageiros; absorve HOME-P0-005 (v1) |

---

#### FOOTER-P1-001 — Faixa de benefícios com contraste e densidade ruins

Status: `TODO` · P1 · Rota: todas. Faixa "Envio para todo o Brasil / Conexão criptografada / Garantia /
Preços de atacado" com texto pequeno e contraste fraco. **Correção:** aumentar contraste, ícones
consistentes, 4 colunas desktop / empilhado mobile. **Evidência:** `evidence/FOOTER-P1-001/`.

#### FOOTER-P1-002 — Newsletter desalinhada

Status: `TODO` · P1 · Rota: todas. Input/legal/botão desalinhados, parecendo montagem manual. **Correção:**
grid 2 colunas desktop (título/descrição | input+botão), mesma altura input/botão, `radius: 8px`, legal
abaixo do input. **Evidência:** `evidence/FOOTER-P1-002/`.

#### FOOTER-P1-003 — Bloco de atendimento/endereço parece campo quebrado

Status: `TODO` · P1 · Rota: todas. Endereço parece input/card desabilitado. **Correção:** card de endereço
real com ícone de localização e link "Ver no mapa". **Evidência:** `evidence/FOOTER-P1-003/`.

#### FOOTER-P1-004 — Ícones sociais pequenos e pouco claros

Status: `TODO` · P1 · Rota: todas. **Correção:** 32–36px, `aria-label`, contraste adequado, remover redes
sem link real. **Evidência:** `evidence/FOOTER-P1-004/`.

#### FOOTER-P1-005 — Linha de categorias no footer pesada e confusa

Status: `TODO` · P1 · Rota: todas. Faixa vermelha com chips parece segundo menu solto. **Correção:** fundo
neutro/integrado, chips `radius: 8px`, quantidade reduzida, "Ver todas as categorias" claro. **Evidência:**
`evidence/FOOTER-P1-005/`.

#### FOOTER-P1-006 — Bloco de pagamento/certificação muito fraco

Status: `TODO` · P1 · Rota: todas. **Correção:** agrupar "Pagamento"/"Segurança", ícones maiores, remover
sem função. **Evidência:** `evidence/FOOTER-P1-006/`.

#### FOOTER-P2-001 — Texto legal/CNPJ pequeno demais

Status: `TODO` · P2 · Rota: todas. **Correção:** aumentar tamanho mínimo e contraste, alinhar ao container.
**Evidência:** `evidence/FOOTER-P2-001/`.

---

## Página: Home (`/`)

> Itens `HOME-P0-004`/`005`/`006` (v1) foram migrados para `HEADER-P0-001`, `HEADER-P0-002` e absorvidos por
> `FOOTER-P0-001` respectivamente — ver [Migração v1 → v2](#migração-v1--v2). Os templates completos abaixo
> são os mesmos já registrados anteriormente, apenas renumerados onde necessário.

#### HOME-P0-001 — Botão B2B duplicado/sobreposto

Status: `TODO` · P0 · Componente: Home / Benefícios / CTA B2B · Branch: `fix/home-visual-cleanup-p0-p1`

**Problema:** dois elementos com aparência de botão "Quero ser revendedor B2B" próximos/sobrepostos na área
de benefícios.

**Causa raiz (hipótese por leitura de código):** `b2b-hero-cta.phtml` renderiza um único botão real
(`.awa-hero-b2b-cta__actions`); hipótese é sobreposição CSS entre o 4º card "Condições B2B" (link inteiro,
ícone WhatsApp) e o botão real abaixo do grid.

```92:96:app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/templates/home/b2b-hero-cta.phtml
<a class="awa-hero-b2b-cta__button action primary"
   href="<?= $block->escapeUrl($registerUrl) ?>">
    <?= $block->escapeHtml(__('Quero ser revendedor B2B')) ?>
</a>
```

**Arquivos suspeitos:** `Magento_Cms/templates/home/b2b-hero-cta.phtml`, `top-home.phtml` (linha ~235),
`web/css/source/_awa-hero-b2b-benefits-2026-06.less`.

**Critério de aceite:** apenas um CTA B2B visível na seção · sem sobreposição · `border-radius: var(--awa-radius-md)` · desktop 1440 e mobile 390 ok.

**Evidência:** `evidence/HOME-P0-001/` · Playwright: `home-visual-regression.spec.ts`.

---

#### HOME-P0-002 — Product cards com imagem ausente/placeholder vazio

Status: `TODO` · P0 · Componente: Vitrines de produto · Branch: `fix/home-visual-cleanup-p0-p1`

**Problema:** card com placeholder "rosa"/quase vazio em vitrine principal.

**Causa raiz:** `list.phtml` já tem fallback via `onerror` para `images/product/placeholder/image.jpg` —
validar se o placeholder padrão tem qualidade ruim ou se o `onerror` não dispara para o SKU específico.

**Arquivos suspeitos:** `Magento_Catalog/templates/product/list.phtml` (linhas 150, 272),
`web/images/product/placeholder/image.jpg`.

**Critério de aceite:** nenhum card vazio em vitrines principais · fallback neutro, não rosa · SKUs sem
imagem documentados.

**Evidência:** `evidence/HOME-P0-002/` · Playwright: `home-visual-regression.spec.ts`.

---

#### HOME-P0-003 — Carrosséis com controles desalinhados/quebrados

*(renumerado de `HOME-P0-006` v1)*

Status: `TODO` · P0 · Componente: Home / Carrosséis · Branch: `fix/home-visual-cleanup-p0-p1`

**Problema:** setas sobrepostas aos cards, botão de pause do hero flutuando fora do conjunto, linhas de
paginação vermelhas ambíguas.

**Arquivos suspeitos:** `cms_index_index.xml` (bloco `top_home`, linha ~205), `_awa-carousel-home-2026-06.less`, `_awa-fix-carousel-2026-06.less`, `_slider.less`.

**Critério de aceite:** setas fora da área de conteúdo · pause dentro do hero com `aria-label` · paginação
com dots/barra real, não linha ambígua.

**Evidência:** `evidence/HOME-P0-003/` · Playwright: `home-visual-regression.spec.ts`.

---

### Home — P1/P2 (resumo; template completo preservado do v1)

| ID | Título | Evidência |
|---|---|---|
| `HOME-P1-001` | Auditar/aplicar `--awa-radius-*` (8px padrão) — tokens já existem em `_tokens.less` | `evidence/HOME-P1-001/` |
| `HOME-P1-002` | Padronizar cards de benefícios (altura igual ±2px) | `evidence/HOME-P1-002/` |
| `HOME-P1-003` | Padronizar product card (`checkCardAlignment`) | `evidence/HOME-P1-003/` |
| `HOME-P1-004` | Padronizar cards de categoria (fundo/crop/escala) | `evidence/HOME-P1-004/` |
| `HOME-P1-005` | Espaçamentos verticais via `@awa-space-*` | `evidence/HOME-P1-005/` |
| `HOME-P1-006` | Tipografia e hierarquia (title 24–28px, card-title 14–15px, CTA 13–14px) | `evidence/HOME-P1-006/` |
| `HOME-P1-007` | CTAs B2B claros (guest/logado/restrito) | `evidence/HOME-P1-007/` |
| `HOME-P2-001` | Hero com CTA real em HTML | `evidence/HOME-P2-001/` |
| `HOME-P2-002` | Redução de carrosséis redundantes | `evidence/HOME-P2-002/` |
| `HOME-P2-003` | Ocultar pedidos recentes quando vazio (parcial via `§BUG-H-017`) | `evidence/HOME-P2-003/` |
| `HOME-P2-004` | Footer moderno (checkpoint de regressão — ver `FOOTER-*`) | `evidence/HOME-P2-004/` |
| `HOME-P2-005` | Art direction consistente nas categorias | `evidence/HOME-P2-005/` |
| `HOME-P2-006` | Lighthouse/CLS ≤ 0.1 | `evidence/HOME-P2-006/` |

---

## Página: Categoria / PLP — Bagageiros (`/bagageiros.html`)

Rota confirmada no banco: `bagageiros.html` → `catalog/category/view/id/67` (entity_id 67).
Evidência inicial: screenshot enviado em 2026-07-09.
Tipo: PLP / Category Page.
Status geral: `REPRODUCED` (maioria dos P0) / `TODO` (mobile, ainda sem captura).
Prioridade geral: P0/P1.

### PLP-P0-001 — Header de categoria com banner/placeholder vazio

Status: `REPRODUCED` · P0 · Componente: category-header · Rota: `/bagageiros.html`

**Problema:** área do título "Bagageiros" tem grande espaço branco com forma cinza quase invisível ao
fundo — parece banner quebrado ou asset ausente.

**Causa raiz (medida via Playwright contra produção em 2026-07-09, não é asset ausente):** o asset
`pub/media/catalog/category/cat-bagageiros.png` existe e é válido (1080×1080px, 502KB). O
`plp-visual-baseline.spec.ts` capturou o `getComputedStyle` real do elemento:

```json
".awa-category-hero__bg-image": {
  "opacity": "0.32",
  "position": "relative",
  "width": "1214px",
  "height": "132px"
}
```

O tamanho está correto — a imagem **preenche** a área do hero (1216×160px). A hipótese inicial (que
`position: relative !important` de `_visual-bug-fixes.less` linha 102–105 encolheria a imagem para o
tamanho intrínseco do HTML) foi testada e **descartada** pelos dados reais. A causa real da aparência
"fantasma/quase invisível" é simplesmente `opacity: 0.32` no elemento.

**Pendente antes de corrigir:** a regra LESS exata que aplica esse `opacity: 0.32` não foi localizada por
busca estática — pelo menos 8 arquivos LESS tocam `.awa-category-hero__bg-image`
(`_awa-product-cards-modern.less`, `_visual-bug-fixes.less`, `_awa-plp-grid-mobile-2026-06.less`,
`_awa-category-layout-fix.less`, `_awa-impeccable-layout-2026-06-16.less`,
`_awa-plp-consistency-pass2-2026-06.less`, `_awa-plp-refine-terminal-2026-06.less`,
`_awa-async-bundle-distill-2026-06.less`), cascata complexa demais para adivinhar por grep. Antes de
corrigir, usar DevTools → Computed → rastrear a regra vencedora de `opacity`. Também validar com o time se
a opacidade baixa foi intencional (efeito de leitura sob o título) antes de simplesmente removê-la — pode
ser preciso trocar por um overlay de leitura em vez de remover a opacidade da imagem.

**Arquivos suspeitos:** `Magento_Catalog/layout/catalog_category_view.xml` (bloco `category.image`, linha
29) + os 8 arquivos LESS listados acima (cascata de `opacity`/`position` a rastrear com DevTools antes de
editar).

**Evidência:** `evidence/PLP-P0-001/` · Playwright: `plp-visual-baseline.spec.ts` (evidência real capturada
em 2026-07-09 contra produção).

### PLP-P0-002 — Espaço vazio excessivo entre categoria e toolbar

Status: `REPRODUCED` · P0 · Componente: category-content

**Problema:** área enorme vazia entre header "Bagageiros" e a toolbar/listagem — sensação de carregamento
quebrado.

**Correção:** remover `min-height`/margin excessivo; verificar bloco de busca/listagem renderizando vazio;
reduzir spacing para 24–32px.

**Evidência:** `evidence/PLP-P0-002/`.

### PLP-P0-003 — Ícone/loader solto na área vazia

Status: `REPRODUCED` · P0 · Componente: loading-state

**Problema:** pequeno ícone (provável loader/search) isolado no centro da área vazia, sem contexto.

**Correção:** remover se resíduo; se for loader, mostrar skeleton real só durante carregamento.

**Evidência:** `evidence/PLP-P0-003/`.

### PLP-P0-004 — Filtros vazios ou quebrados

Status: `REPRODUCED` · P0 · Componente: layered-navigation

**Problema:** sidebar "COMPRAR POR"/"VALOR" sem controles úteis visíveis — parece filtro quebrado/incompleto.

**Correção:** exibir filtro apenas com valores reais; ocultar sidebar por completo se não houver nenhum
filtro utilizável; accordion compacto em desktop, drawer em mobile.

**Evidência:** `evidence/PLP-P0-004/`.

### PLP-P0-005 — Widget "Pedidos feitos recentemente" indevido na PLP

Status: `TESTED_LOCAL` · P0 · Componente: sidebar

**Problema:** bloco "Últimos itens comprados" aparece deslocado na sidebar da PLP, vazio/sem produto
legível — um dos bugs mais claros da página.

**Causa raiz (confirmada por leitura de código, alta confiança):** o bloco nativo Magento
`sale.reorder.sidebar` foi explicitamente removido da Home:

```60:60:app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/layout/cms_index_index.xml
<referenceBlock name="sale.reorder.sidebar" remove="true"/>
```

Mas **não há remoção equivalente** em `Magento_Catalog/layout/catalog_category_view.xml` nem em
`Magento_Theme/layout/catalog_category_view.xml` (ambos lidos por completo — nenhuma menção a
`sale.reorder.sidebar` ou "recentemente" em nenhum dos dois). Esta é a causa mais provável do widget
aparecer na `sidebar.additional` da categoria.

Validação parcial via `plp-visual-baseline.spec.ts` em 2026-07-09: como **guest** (não autenticado),
`.block-reorder` não renderiza (`count: 0`) — comportamento nativo esperado do Magento, já que esse bloco só
exibe pedidos para cliente logado com histórico. Isso **não contradiz** a hipótese, só significa que o
baseline guest não reproduz visualmente o bug. **Falta validar com sessão B2B logada com pedidos recentes**
(mesmo contexto da screenshot original) antes de aplicar a correção — ver critério de aceite do
`HOME-P2-003` para o padrão já usado na Home.

**Correção aplicada (2026-07-09):** adicionado `<referenceBlock name="sale.reorder.sidebar" remove="true"/>`
em `Magento_Catalog/layout/catalog_category_view.xml` linha 17, no mesmo padrão já usado em
`cms_index_index.xml`.

```15:19:app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/layout/catalog_category_view.xml
        <referenceBlock name="awa.impeccable.audit.uncached.global.v10" remove="true"/>
        <referenceBlock name="page.main.title" remove="true"/>
        <referenceBlock name="sale.reorder.sidebar" remove="true"/>
```

**Validação:** login real com conta de teste (`customer_id` 9068, 1 pedido — senha temporária redefinida
via `EncryptorInterface` só para o teste, depois invalidada) em `/bagageiros.html`: sidebar-additional
renderiza vazia (`<div class="catalog-sidebar-adv" style="display: none;"></div>`),
`reorderBlockCount = 0`. `cache:flush` executado. Sem novas entradas em `exception.log`/`system.log`. Sem
CSS/JS 404 novo.

**Evidência:** `evidence/PLP-P0-005/before-*.png` (screenshot original do usuário) +
`after-desktop-1440-logged-in.png` (capturado logado, pós-correção).

### PLP-P0-006 — Botões duplicados no widget lateral

Status: `TESTED_LOCAL` · P0 · Componente: sidebar

**Problema:** dentro do widget de pedidos recentes, dois botões "ADICIONAR AO CARRINHO" — parece duplicação
de CTA.

**Causa raiz e correção:** mesma causa raiz e mesma correção de `PLP-P0-005` — a remoção do widget elimina
os botões duplicados por consequência (não havia botão isolado para corrigir; o widget inteiro não deveria
existir na PLP).

**Evidência:** `evidence/PLP-P0-006/` (mesmos screenshots de `PLP-P0-005`, mesmo widget).

### PLP-P0-007 — Cards de produto sem hierarquia mínima

Status: `REPRODUCED` · P0 · Componente: product-card

**Problema:** produto, código e badge pequenos; card não comunica claramente imagem/nome/disponibilidade/preço/CTA.

**Correção:** product card padrão — imagem em área fixa, título 2 linhas (14–15px), SKU/código (12px),
status/estoque, preço ou mensagem B2B, CTA.

**Arquivos suspeitos:** `Magento_Catalog/templates/product/list.phtml`, `web/css/source/_product-card.less`.

**Evidência:** `evidence/PLP-P0-007/`.

### PLP-P0-008 — Cards de produto sem CTA principal

Status: `REPRODUCED` · P0 · Componente: product-card

**Problema:** card mostra "Pronta entrega" mas não há botão claro ("Ver produto"/"Comprar"/"Entrar para ver
preço") — reduz conversão.

**Correção:** B2B logado → "Ver produto"/"Adicionar à cotação/lista"; guest → "Cadastre-se para ver preço";
botão sempre no rodapé do card.

**Evidência:** `evidence/PLP-P0-008/`.

### PLP-P0-009 — Ausência de preço/contexto B2B

Status: `REPRODUCED` · P0 · Componente: product-card / pricing

**Problema:** produtos sem preço nem explicação — em loja B2B isso precisa virar mensagem comercial, não
vazio.

**Correção:** guest → "Preço B2B disponível após login/aprovação"; logado B2B aprovado → preço ou política
de cotação; nunca deixar lacuna vazia onde o preço deveria estar.

**Evidência:** `evidence/PLP-P0-009/`.

### PLP-P0-010 — Mobile/filtro sem comportamento definido

Status: `TODO` (ainda sem captura mobile — não usar `REPRODUCED` até validar visualmente) · P0 ·
Componente: responsive-plp

**Problema:** layout desktop já com múltiplos bugs; alto risco de quebra em mobile com sidebar+toolbar+grid.

**Correção:** filtro como drawer; toolbar compacta (sticky opcional); grid 1–2 colunas; testar 430px e 360px
sem overflow horizontal.

**Evidência:** `evidence/PLP-P0-010/`.

---

### PLP — P1 (padronização, 12 itens)

| ID | Título | Correção resumida | Evidência |
|---|---|---|---|
| `PLP-P1-001` | Breadcrumb compacto | padding 8–12px, sem caixa alta, remover se vazio | `evidence/PLP-P1-001/` |
| `PLP-P1-002` | Toolbar padronizada | filtro (esq) → view mode → ordenação/qtd (dir), inputs 40px, radius 8px | `evidence/PLP-P1-002/` |
| `PLP-P1-003` | Botão Mostrar/Ocultar filtros | texto reflete estado, `aria-expanded`, drawer mobile | `evidence/PLP-P1-003/` |
| `PLP-P1-004` | Grid/list acessível | botões 40×40, estado ativo, `aria-pressed` | `evidence/PLP-P1-004/` |
| `PLP-P1-005` | Grid de produtos melhor aproveitado | 4 colunas c/ filtro lateral (5 se legível), gaps reduzidos | `evidence/PLP-P1-005/` |
| `PLP-P1-006` | Imagens de produto padronizadas | `object-fit: contain`, `aspect-ratio: 1/1`, produto ocupando 75–85% | `evidence/PLP-P1-006/` |
| `PLP-P1-007` | Bordas/cards padronizados | uma borda por card, radius 8px, sombra leve | `evidence/PLP-P1-007/` |
| `PLP-P1-008` | Badge "Pronta entrega" | 20–24px altura, estados padronizados | `evidence/PLP-P1-008/` |
| `PLP-P1-009` | Paginação | próxima ao fim do grid, botões 36–40px, contagem "Itens X–Y de Z" | `evidence/PLP-P1-009/` |
| `PLP-P1-010` | Espaço antes do footer | 48–64px, sem `min-height` residual | `evidence/PLP-P1-010/` |
| `PLP-P1-011` | Grid/container global | container consistente do header ao footer | `evidence/PLP-P1-011/` |
| `PLP-P1-012` | Acessibilidade dos controles | `:focus-visible`, alvo mínimo 32–44px, `aria-*` corretos | `evidence/PLP-P1-012/` |

### PLP — P2 (modernização, 4 itens)

| ID | Título | Evidência |
|---|---|---|
| `PLP-P2-001` | Header de categoria com descrição/SEO útil | `evidence/PLP-P2-001/` |
| `PLP-P2-002` | Filtro moderno com chips ativos | `evidence/PLP-P2-002/` |
| `PLP-P2-003` | Product card B2B avançado | `evidence/PLP-P2-003/` |
| `PLP-P2-004` | Quick actions: lista de compras/cotação | `evidence/PLP-P2-004/` |

---

## Padronização visual — referência rápida

Radius (já definido em `_tokens.less`, não recriar):

```less
@awa-radius-xs: 4px;    // badges
@awa-radius-sm: 6px;    // controles pequenos
@awa-radius-md: 8px;    // padrão: cards, botões, inputs, dropdowns
@awa-radius-lg: 12px;   // containers grandes
@awa-radius-pill: 999px; // só chips reais
```

Product card padrão (Home e PLP compartilham o mesmo componente-alvo): imagem fixa 1:1, título até 2 linhas,
código/SKU, status de estoque, preço ou mensagem B2B, CTA principal, ações secundárias se necessário.

Toolbar PLP: `[Mostrar/Ocultar filtros] [Grid/List]  ···  [Ordenar por] [Itens por página]`.

Sidebar "Comprar por": Categoria / Marca / Modelo / Valor / Disponibilidade / Aplicação — **nunca renderizar
filtro sem valores**.

Footer: Newsletter → Benefícios → Institucional → Suporte → Atendimento → Redes sociais →
Pagamento/segurança → Legal. Sem blocos vermelhos independentes por coluna.

---

## Próxima ação recomendada

1. ~~Atualizar documento mestre com os IDs da PLP Bagageiros~~ — feito nesta entrega.
2. ~~Atualizar YAML de status~~ — feito nesta entrega.
3. ~~Criar baseline Playwright da PLP~~ — feito nesta entrega (`plp-visual-baseline.spec.ts`, diagnóstico,
   sem assert rígido — ver nota no próprio arquivo).
4. **Não corrigir nada ainda.** Próximo passo, quando autorizado: abrir branch `fix/plp-bagageiros-p0` e
   corrigir apenas os itens P0 listados, começando por `PLP-P0-005`/`006` (causa raiz já identificada e de
   baixo risco: uma linha de `referenceBlock remove="true"` em `catalog_category_view.xml`).
