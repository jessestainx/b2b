# Critique: Portal B2B — Repetir Pedido

**Target:** `https://awamotos.com/b2b/reorder/history` (captura anexada)
**Data:** 2026-05-27
**Register:** product

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Header mostra estado guest em página autenticada; breadcrumb não inclui "Repetir Pedido"; área principal aparenta vazia |
| 2 | Match System / Real World | 3 | Termos B2B em PT-BR adequados; breadcrumb "Início" sem acento |
| 3 | User Control and Freedom | 3 | Sidebar e links padrão Magento permitem sair/voltar |
| 4 | Consistency and Standards | 2 | Dois padrões de item ativo na nav (faixa lateral vs. fundo tintado); header contradiz sidebar |
| 5 | Error Prevention | 3 | Seleção por checkbox antes de adicionar ao carrinho |
| 6 | Recognition Rather Than Recall | 2 | 11 itens flat na sidebar sem agrupamento |
| 7 | Flexibility and Efficiency | 3 | Toggle all + seleção parcial por pedido |
| 8 | Aesthetic and Minimalist Design | 2 | Header ocupa ~40% da viewport; vazio dominante no conteúdo |
| 9 | Error Recovery | 2 | Empty state mínimo ("Nenhum pedido encontrado") sem próximo passo |
| 10 | Help and Documentation | 1 | Sem orientação contextual no fluxo de recompra |
| **Total** | | **24/40** | **Acceptable — melhorias significativas necessárias** |

## Anti-Patterns Verdict

**LLM assessment:** Não parece "AI slop" genérico. É Magento B2B customizado com identidade AWA reconhecível. Porém usa faixa lateral vermelha no item ativo da sidebar (`border-left: 3px`), anti-pattern explícito do Impeccable e já substituído em outro bundle (`awa-b2b-ui-promax`) por fundo tintado sem faixa.

**Deterministic scan:** `detect.mjs` em templates B2B retornou **0 findings** (limpo).

**Visual overlays:** Browser MCP indisponível nesta sessão; injeção de overlay não executada. Evidência baseada em captura do usuário + inspeção de código/CSS.

## Overall Impression

A estrutura B2B existe (sidebar, breadcrumbs, identidade AWA), mas a página operacional falha em comunicar estado e conteúdo. O comprador logado vê convite para login no header enquanto navega "Repetir Pedido", e a coluna principal parece completamente vazia. A maior oportunidade: tornar o painel B2B confiável e legível no primeiro segundo, sem sacrificar a identidade visual.

## What's Working

1. **Sidebar B2B completa** — fluxos empresariais (cotações, crédito, ERP, aprovações) acessíveis num único lugar.
2. **Template de recompra bem modelado** — cards por pedido, comparação preço original/atual, seleção granular (`reorder.phtml`).
3. **Tokens e polish B2B existentes** — `b2b-account-pages.css` e `awa-b2b-ui-promax` já definem padrão de item ativo sem faixa lateral (parcialmente aplicado).

## Priority Issues

### [P0] Área principal aparenta vazia em "Repetir Pedido"
- **Why:** Comprador não vê título, subtítulo, pedidos nem empty state; parece página quebrada.
- **Fix:** Garantir render imediato de `.b2b-section-header` + conteúdo (critical CSS inline ou exclusão desta rota do CSS gate); investigar se `b2b_reorder_history` está fora de `$isCustomerAccountRoute`.
- **Suggested command:** `/impeccable harden`

### [P1] Header exibe "faça Login" em sessão autenticada
- **Why:** `customer-data` adiado (3,5–6,5s) deixa estado guest no FPC; contradiz sidebar ativa e mina confiança.
- **Fix:** Eager-load `customer-data` em rotas `b2b_*` e `customer_account_*`; ou server-side hint quando cookie de sessão existe.
- **Suggested command:** `/impeccable optimize`

### [P1] Breadcrumb incompleto — falta página atual
- **Why:** Layout passa `page_title` vazio em `b2b_reorder_history.xml`; usuário perde orientação ("onde estou?").
- **Fix:** Definir `page_title` = "Repetir Pedido" no layout ou derivar do `<h1>`.
- **Suggested command:** `/impeccable clarify`

### [P2] Faixa lateral no item ativo da sidebar
- **Why:** Anti-pattern Impeccable; conflita com padrão promax (fundo tintado); mobile já remove a faixa, desktop não.
- **Fix:** Unificar em `background + font-weight` como em `awa-b2b-ui-promax-2026-05-22.css` linhas 195–199; remover `border-left: 3px` de `awa-visual-bugfix.css` ~7760.
- **Suggested command:** `/impeccable polish`

### [P2] Sidebar com 11+ itens sem agrupamento
- **Why:** Viola chunking (≤4 por grupo); comprador B2B precisa escanear lista longa a cada visita.
- **Fix:** Agrupar em "Conta", "Pedidos & compras", "Empresa & crédito" com subtítulos ou separadores visuais.
- **Suggested command:** `/impeccable layout`

## Persona Red Flags

**Alex (Power User):** Header pede login enquanto já está no painel; breadcrumb não confirma rota; se conteúdo depende de JS/CSS tardio, recompra fica bloqueada por segundos desnecessários.

**Jordan (First-Timer):** Tela branca à direita da sidebar não explica o que fazer; empty state (se existir) não sugere "ir ao catálogo" ou "ver pedidos"; 11 links sem hierarquia geram paralisia.

**Carlos (Revendedor AWA — persona de projeto):** Veio repetir pedido rápido; header diz que precisa logar para ver preços; área de recompra invisível; desconfia que a integração ERP/preço B2B falhou.

## Minor Observations

- Breadcrumb usa "Início" sem acento; template PHP usa "Home" — inconsistência de tradução.
- Header com 3 faixas (promo + busca + nav vermelha) consome viewport antes do trabalho B2B.
- Mensagem do topo B2B ("Cadastre agora") permanece relevante mesmo para clientes já logados.
- `b2b_reorder_history` não entra em `$isCustomerAccountRoute` (só `b2b_account_*`), possível rota órfã de otimizações slim.

## Cognitive Load

**4 falhas** (moderado-alto): sidebar >4 itens sem chunking; header compete com conteúdo; estado auth exige reconciliar header vs sidebar; possível memória entre breadcrumb e título ausente.

## Questions to Consider

- E se o painel B2B tivesse header compacto (só logo + conta + carrinho) e escondesse promo/busca de catálogo?
- O empty state de "Repetir Pedido" poderia mostrar os 3 últimos pedidos como atalho, em vez de mensagem solta?
- Por que o padrão visual promax da sidebar não vence o bugfix com faixa lateral no desktop?
