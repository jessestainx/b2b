---
target: "https://awamotos.com/expresscheckout.html"
total_score: 28
p0_count: 0
p1_count: 2
p2_count: 3
timestamp: 2026-05-28T20-46-40Z
slug: awamotos-com-expresscheckout-html
---
## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Empty state é claro; minicart mostra "Pedido mínimo 0%" sem mensagem até o JS hidratar |
| 2 | Match System / Real World | 2 | URL `expresscheckout.html` redireciona 302 para `/checkout/cart/`; promessa de checkout expresso não se cumpre com carrinho vazio |
| 3 | User Control and Freedom | 3 | CTAs de saída (catálogo, WhatsApp) presentes; footer e bottom nav ocultos no cart focus mode |
| 4 | Consistency and Standards | 3 | Visual alinhado ao tema AWA; nomenclatura de URL vs título "Carrinho de Compras" diverge |
| 5 | Error Prevention | 3 | Impede checkout acidental com carrinho vazio; pedido mínimo B2B só aparece no minicart |
| 6 | Recognition Rather Than Recall | 3 | Chips de categoria ajudam; CTAs duplicados de catálogo geram hesitação |
| 7 | Flexibility and Efficiency | 2 | Sem atalho de recompra/cotação rápida; busca no header simplificado é o único acelerador |
| 8 | Aesthetic and Minimalist Design | 3 | Card central limpo; DOM ainda carrega mega-menu completo (~327 KB HTML) |
| 9 | Error Recovery | 3 | n/a no estado vazio |
| 10 | Help and Documentation | 3 | Link WhatsApp com aria-label; falta microcopy sobre pedido mínimo na página |
| **Total** | | **28/40** | **Good — endereçar IA da URL e sobrecarga de escolhas** |

## Anti-Patterns Verdict

**LLM assessment:** Não parece "AI slop". O empty state é custom (`noItems.phtml`), usa tokens AWA (`var(--awa-primary)`, BEM), copy B2B em português e WhatsApp contextual. Evita side-stripe borders, gradient text e glassmorphism. O risco de template genérico está nos chips de categoria (padrão e-commerce comum), mas a execução é coerente com a marca.

**Deterministic scan:** `detect.mjs` em `noItems.phtml` e `awa-cart-checkout-critical.phtml` retornou **0 findings** (exit 0).

**Visual overlays:** Browser MCP indisponível nesta sessão; injeção de overlay não foi possível. Evidência via HTML live + source review.

## Overall Impression

O carrinho vazio em si está bem trabalhado: card central, CTA primário vermelho AWA, chips de categoria e escape comercial via WhatsApp. O maior problema não é visual, é de **promessa vs realidade**: quem chega por `expresscheckout.html` espera checkout, recebe redirect para carrinho vazio. Em seguida, três CTAs de catálogo + oito chips competem pela mesma decisão ("por onde recomeço?").

## What's Working

1. **Empty state orientado à ação** — Título, subtítulo B2B e CTA "Explorar catálogo" formam um funil legível dentro do card (`noItems.phtml`).
2. **Cart focus mode** — CSS crítico oculta nav principal, footer, bottom nav e cookie banner reposicionado; reduz ruído operacional no fluxo de compra.
3. **Canal comercial de escape** — WhatsApp com mensagem pré-preenchida e `aria-label` explícito para cotação B2B.

## Priority Issues

**[P1] URL `expresscheckout.html` não entrega checkout**
- **Why it matters:** Redirect 302 para `/checkout/cart/` quebra a expectativa de "express checkout" (Rokanthemes OPC). Compradores B2B vindos de e-mail, ERP ou bookmark desconfiam do fluxo.
- **Fix:** Com carrinho vazio, manter URL mas mostrar empty state com copy que explique o redirect; com itens, garantir que `expresscheckout.html` abre o OPC real. Considerar alias só quando houver itens.
- **Suggested command:** `/impeccable clarify`

**[P1] "Pedido mínimo 0%" no minicart sem contexto imediato**
- **Why it matters:** O bloco `awa-b2b-min-order-progress` renderiza percentual 0% e mensagem vazia antes do JS; sinal de erro ou bloqueio para quem não conhece a regra de R$ 1.500.
- **Fix:** SSR da mensagem "Faltam R$ 1.500,00..." no `<p data-role="message">`; ocultar barra inteira quando carrinho vazio.
- **Suggested command:** `/impeccable harden`

**[P2] CTAs de catálogo redundantes**
- **Why it matters:** "Explorar catálogo" (home) e "Ver catálogo completo" (`/catalogo`) competem sem hierarquia clara; aumenta tempo de decisão.
- **Fix:** Um CTA primário + link texto secundário único; alinhar destino (home vs catálogo PDF/listagem).
- **Suggested command:** `/impeccable distill`

**[P2] Oito chips de categoria no ponto de decisão**
- **Why it matters:** 8 chips + 3 links = 11 opções visíveis; viola working memory (≤4). Comprador mobile precisa scrollar antes de agir.
- **Fix:** Mostrar 4 chips + "Ver todas"; ou mover chips para below-fold colapsável.
- **Suggested command:** `/impeccable layout`

**[P2] Hierarquia de headings fraca**
- **Why it matters:** H1 permanece "Carrinho de Compras" enquanto a mensagem emocional ("Seu carrinho está vazio") está em `<p>`. Screen readers anunciam título genérico primeiro.
- **Fix:** H1 = mensagem do empty state; meta secundária opcional; ou `aria-labelledby` no card.
- **Suggested command:** `/impeccable audit`

## Persona Red Flags

**Casey (mobile, interrompido):** CTA primário fica full-width (bom), mas oito chips empurram a ação para baixo da dobra. Minicart no header ainda expõe "Pedido mínimo 0%" ao abrir carrinho.

**Jordan (first-timer B2B):** Bookmark `expresscheckout.html` → redirect → "Carrinho de Compras" vazio. Não fica claro se checkout falhou ou se faltam produtos. Duas opções de catálogo sem explicar diferença.

**Riley (edge cases):** HTML de 327 KB com mega-menu completo no DOM mesmo com CSS `display:none`; falha graceful se CSS async atrasar. Mensagem de pedido mínimo depende de KO/JS para preencher `data-role="message"`.

**Revendedor AWA (project-specific):** Copy menciona "pedido B2B" mas não informa pedido mínimo de R$ 1.500 na página principal do empty state; informação fica escondida no minicart.

## Minor Observations

- Skip links triplicados (dois "Ir para o conteúdo principal" + navegação).
- `Explorar catálogo` aponta para `/` (home), não para PLP ou catálogo B2B.
- Trust strip B2B (`trust-strip.phtml`) não aparece para guest no empty state.
- Título `<title>` genérico "Carrinho de Compras" poderia variar no empty state.

## Questions to Consider

- E se `expresscheckout.html` só existisse quando há itens no carrinho, e carrinho vazio usasse `/checkout/cart/` canonical?
- O comprador B2B precisa de oito categorias aqui, ou de quatro atalhos + busca?
- Um empty state "confiante" mostraria pedido mínimo e login B2B inline, não só no minicart?
