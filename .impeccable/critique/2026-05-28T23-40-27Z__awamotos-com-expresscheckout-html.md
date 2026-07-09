---
target: OPC expresscheckout com itens
total_score: 20
p0_count: 1
p1_count: 2
timestamp: 2026-05-28T23-40-27Z
slug: awamotos-com-expresscheckout-html
---
## Design Health Score

| # | Heurística | Score | Issue principal |
|---|-----------|-------|-----------------|
| 1 | Visibility of System Status | 2 | 119KB async CSS introduz FOUC parcial; progress bar sem step count |
| 2 | Match System / Real World | 3 | Labels em português, fluxo familiar; terminologia B2B correta |
| 3 | User Control and Freedom | 2 | Sem "Voltar ao carrinho" no header simplificado; comprador preso sem saída clara |
| 4 | Consistency and Standards | 3 | Tokens AWA consistentes; cards de etapas padronizados |
| 5 | Error Prevention | 1 | BlockExpressCheckoutPlugin disabled — gate B2B ausente na entrada; only API-level block on submit |
| 6 | Recognition Rather Than Recall | 2 | Sidebar com 11 blocos; PO number em area de pagamento, não no início |
| 7 | Flexibility and Efficiency | 2 | Sem autocomplete de PO numbers; sem atalho de teclado para avançar etapa |
| 8 | Aesthetic and Minimalist Design | 2 | Sidebar mistura dados B2B, extras OPC, newsletter, gift sem separação visual |
| 9 | Error Recovery | 1 | Erro API no submit aparece como flash no topo, fora do viewport do formulário preenchido |
| 10 | Help and Documentation | 2 | Pedido mínimo B2B invisível no OPC; newsletter label em inglês |
| **Total** | | **20/40** | **Fair — gates B2B desativados na entrada; sidebar sobrecarregada** |

## Anti-Patterns Verdict

**LLM assessment:** Não parece AI slop visual. Layout 2-col, header simplificado e tokens AWA são coerentes. O risco de qualidade vem da sidebar como repositório de features sem curadoria (11 blocos), não de escolhas estéticas problemáticas.

**Deterministic scan:** detect.mjs em awa-opc-checkout-critical.phtml e templates KO B2B retornou exit 0 — zero findings.

**Visual overlays:** Browser MCP não disponível nesta sessão. Critique baseado em análise de fonte completa.

## Overall Impression

O OPC tem boa estrutura de apresentação (CSS crítico inline, header limpo, cards de etapas consistentes). O problema real é sistêmico: gate B2B desacoplado da entrada (BlockExpressCheckoutPlugin disabled), sidebar com 11 blocos sem agrupamento e pedido mínimo invisível no checkout.

## What's Working

1. **CSS crítico inline bem executado:** ~3KB inline previnem CLS. Tokens var(--awa-cc-*) sem hardcode.
2. **Campos B2B com a11y completa:** po-number.html tem aria-describedby, aria-invalid, aria-errormessage. b2b-terms.html tem modal com role="dialog", aria-modal.
3. **Header simplificado correto:** nav, minicart e top-bar ocultos via CSS crítico sem flash.

## Priority Issues

**[P0] BlockExpressCheckoutPlugin desativado — gate B2B ausente na entrada do OPC**
- **Why it matters:** Compradores não aprovados ou abaixo do mínimo entram, preenchem tudo, e só recebem erro genérico da API no submit. Flash no topo, fora do viewport.
- **Fix:** Reativar BlockExpressCheckoutPlugin (remover disabled="true" em di.xml) ou garantir mensagem de erro inline próxima ao botão.
- **Suggested command:** /impeccable harden

**[P1] Sidebar com 11 blocos em sequência sem agrupamento visual**
- **Why it matters:** Sumário, PO, termos, notas, desconto, comentário, newsletter, gift, agreements, WhatsApp opt-in, botão finalizar — tudo em scroll único. Botão finalizar enterrado no fim.
- **Fix:** Agrupar em 3 zonas: (1) sumário + total; (2) extras opcionais colapsáveis; (3) finalização sticky.
- **Suggested command:** /impeccable distill

**[P1] Pedido mínimo B2B invisível no OPC**
- **Why it matters:** Progress bar existe no carrinho mas não no checkout. Comprador pode remover itens via API e só descobrir o bloqueio no submit.
- **Fix:** Injetar indicação do pedido mínimo no sumário da sidebar via jsLayout ou extensão do MinOrderConfigPlugin.
- **Suggested command:** /impeccable harden

**[P2] Sem saída visível ("back to cart") no header simplificado**
- **Why it matters:** Header logo only sem link de volta ao carrinho. Compradores B2B editam pedidos frequentemente.
- **Fix:** Adicionar link "← Editar carrinho" discreto no header simplificado.
- **Suggested command:** /impeccable layout

**[P2] Newsletter checkbox em inglês e fora de contexto**
- **Why it matters:** "Check to Subscribe Our Newsletter" em inglês num checkout B2B 100% em português. Template OPC não traduzido.
- **Fix:** Mover para pós-checkout ou traduzir e reposicionar.
- **Suggested command:** /impeccable clarify

## Persona Red Flags

**Jordan (first-timer B2B):** Preenche tudo, clica finalizar com subtotal abaixo do mínimo, recebe erro API no topo. Não sabe se foi pagamento, endereço ou pedido. Alto abandono.

**Casey (mobile, interrompido):** Sidebar abaixo das etapas em mobile. Botão finalizar a 3-4 telas de scroll. Interrompido no meio do checkout não sabe onde estava.

**Revendedor AWA (project-specific):** PO number no final (seção de pagamento), não no início. Não vê crédito B2B disponível no OPC (só no carrinho). Liga para comercial para confirmar.

## Minor Observations

- Total ~132KB CSS async (119KB + 13KB awa-checkout-polish).
- data-bind="html: getTermsContent()" pode injetar HTML sem sanitização client-side.
- Newsletter usa template genérico Magento_Ui sem customização de label, daí o inglês.
- Página de sucesso tem b2b.checkout.success.badge com crédito — único momento em que comprador vê status de crédito no fluxo.

## Questions to Consider

- Se BlockExpressCheckoutPlugin está desativado intencionalmente, qual gate substitui a validação de entrada no OPC?
- Newsletter e gift message são necessários para o perfil B2B, ou são features B2C herdadas do template Rokanthemes?
- O botão "Finalizar Pedido" deveria ser sticky no mobile?
