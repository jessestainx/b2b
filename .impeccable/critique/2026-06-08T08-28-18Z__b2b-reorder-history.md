---
timestamp: 2026-06-08T08-28-18Z
slug: b2b-reorder-history
---
# Critique: Repetir Pedido — B2B

**Target:** `https://awamotos.com/b2b/reorder/history`
**Data:** 2026-06-08
**Register:** product

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Breadcrumb e live region ok; preços atuais carregam async (estado "…" antes do AJAX) |
| 2 | Match System / Real World | 3 | PT-BR B2B adequado; fluxo pedido → carrinho reconhecível |
| 3 | User Control and Freedom | 3 | Seleção parcial, toggle all, fallback POST sem JS; sem desfazer após add |
| 4 | Consistency and Standards | 3 | Nav com fundo tintado unificado; shell B2B alinhado ao restante da conta |
| 5 | Error Prevention | 3 | Checkbox + validação "nenhum item"; form_key no AJAX |
| 6 | Recognition Rather Than Recall | 3 | Sidebar em 4 grupos; cards mostram nº pedido, data, status, total |
| 7 | Flexibility and Efficiency | 3 | Toggle all, lazy-load de preços, fila de 2 requests paralelas |
| 8 | Aesthetic and Minimalist Design | 3 | Hierarquia forte nos cards; header catálogo ainda alto na viewport |
| 9 | Error Recovery | 3 | Empty state com CTAs; mensagens AJAX em live region; fallback "—" em falha de preço |
| 10 | Help and Documentation | 2 | Subtítulo orienta o fluxo; sem ajuda sobre variação de preço (+%) |
| **Total** | | **30/40** | **Good — base sólida, polish pontual restante** |

## Anti-Patterns Verdict

**LLM assessment:** Não parece AI slop. Interface Magento B2B customizada com identidade AWA (vermelho AWA, cards operacionais, sem eyebrow genérico). Cards de pedido são affordance correta para recompra; empty state tracejado é intencional, não template SaaS.

**Deterministic scan:** `detect.mjs` em `reorder.phtml` e `breadcrumbs.phtml` retornou **0 findings**.

**Visual overlays:** Browser MCP indisponível nesta sessão. Sem injeção de overlay. Evidência: inspeção de código/templates/CSS publicados + curl (página uncacheable, redirect 302 sem sessão).

## Overall Impression

A página saiu de "área principal vazia / página quebrada" para um fluxo operacional legível. O comprador B2B vê título, pedidos ou empty state, compara preço original vs atual e adiciona ao carrinho com feedback. A maior oportunidade restante é reduzir ruído do header de catálogo nas superfícies de conta e fechar a lacuna de confiança enquanto preços ainda carregam.

## What's Working

1. **Modelo de recompra** — cards por pedido, seleção granular, coluna "Preço atual" com destaque AWA, CTA "Adicionar ao carrinho" com peso visual claro.
2. **Estados de borda** — empty state com ícone, copy e dois CTAs; live region para sucesso/erro AJAX; `<noscript>` para degradação graciosa.
3. **Navegação B2B** — breadcrumbs Início / Portal B2B / Repetir Pedido; sidebar chunkada (Painel, Compras, Empresa, Minha conta) sem faixa lateral no item ativo.

## Priority Issues

### [P2] Header de catálogo compete com o trabalho B2B
- **Why:** Faixas promo + busca + nav vermelha consomem viewport antes do conteúdo operacional; comprador em recompra precisa rolar para ver o primeiro card.
- **Fix:** Modo header compacto em rotas `b2b_*` / `customer_account_*` (ocultar promo strip ou reduzir altura).
- **Suggested command:** `/impeccable distill b2b/account`

### [P2] Envio possível antes dos preços atuais carregarem
- **Why:** Células mostram "…" até o AJAX; usuário pode clicar "Adicionar ao carrinho" sem ver preço revisado, enfraquecendo confiança B2B.
- **Fix:** Desabilitar CTA até prices resolve no card, ou label "Carregando preços…" no botão; manter fallback se timeout.
- **Suggested command:** `/impeccable harden b2b/reorder/history`

### [P2] Sidebar ainda longa no total
- **Why:** Chunking por grupo ajuda, mas ~9 links B2B + links legados "Minha conta" (Magento) ainda exigem varredura em visitas repetidas.
- **Fix:** Mover itens raros (aprovações, faturas) para submenu ou painel; manter ≤4 links visíveis por grupo.
- **Suggested command:** `/impeccable layout b2b/account`

### [P3] Subtítulo repete o título
- **Why:** h1 "Repetir Pedido" + subtítulo longo com a mesma intenção aumenta ruído above-the-fold.
- **Fix:** Encurtar para uma linha factual ("Compare preços do seu grupo e adicione itens ao carrinho.") ou remover se breadcrumb + h1 bastarem.
- **Suggested command:** `/impeccable clarify b2b/reorder/history`

## Persona Red Flags

**Alex (Power User):** Toggle all e seleção parcial funcionam; lazy-load de preços reduz rede. Falta atalho de teclado para submit no card focado e não há ação em lote entre pedidos.

**Jordan (First-Timer):** Empty state agora guia para catálogo e histórico. Tabela com 6 colunas no desktop pode intimidar; no mobile os rótulos `data-th` em uppercase ainda soam técnicos.

**Carlos (Revendedor AWA — persona de projeto):** Fluxo de recompra com preço B2B visível após load atende o job-to-be-done. Ainda precisa confiar que o preço no carrinho será o do grupo se clicar antes do AJAX terminar.

## Minor Observations

- `reorder-note` abaixo do CTA repete informação já implícita no subtítulo.
- Status badges dependem de classes `reorder-status--{status}`; status Magento fora do mapa podem ficar sem estilo.
- B2B aprovado vê `status-panel` no header em vez do prompt guest (correto); usuários híbridos B2C/B2B podem ver UI diferente.

## Cognitive Load

**2 falhas** (baixo-moderado): header chrome alto; tabela densa no desktop. Chunking da sidebar passou (≤4 por grupo). Progressive disclosure nos preços (lazy) é adequada.

## Questions to Consider

- O header de catálogo deveria desaparecer completamente no painel B2B ou só encolher?
- Vale bloquear o CTA até os preços carregarem, ou mostrar preço original riscado + atual quando pronto?
- Repetir Pedido deveria ser o segundo item do grupo Compras (após histórico Magento)?
