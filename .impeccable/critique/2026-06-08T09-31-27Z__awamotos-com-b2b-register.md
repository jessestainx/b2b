---
target: "https://awamotos.com/b2b/register/"
total_score: 30
p0_count: 0
p1_count: 2
p2_count: 2
timestamp: 2026-06-08T09-31-27Z
slug: awamotos-com-b2b-register
---
# Crítica: Cadastro B2B — awamotos.com/b2b/register/

**Target:** https://awamotos.com/b2b/register/
**Data:** 2026-06-08
**Registro:** product (formulário operacional B2B)

## Design Health Score

| # | Heurística | Score | Problema-chave |
|---|-----------|-------|----------------|
| 1 | Visibilidade do status | 3 | Stepper e validação CNPJ funcionam; conteúdo dependia de JS para aparecer |
| 2 | Correspondência ao mundo real | 4 | CNPJ, Receita Federal, alerta sobre contador: linguagem certa para PJ |
| 3 | Controle e liberdade | 3 | Steps clicáveis; no mobile o acordeão pode esconder contexto |
| 4 | Consistência e padrões | 2 | Cascata CSS fragmentada; labels herdavam estilo de conta (11px uppercase) |
| 5 | Prevenção de erros | 3 | Máscaras, força de senha, aviso e-mail RF vs pessoal |
| 6 | Reconhecimento vs memória | 3 | Meta pills + benefícios repetem a mesma promessa |
| 7 | Flexibilidade e eficiência | 3 | Form longo; link de login duplicado (topo + rodapé do form) |
| 8 | Design estético e minimalista | 2 | Grid de 3 cards com ícones animados; gradientes decorativos |
| 9 | Recuperação de erros | 3 | Estados `.mage-error` e alertas ERP presentes |
| 10 | Ajuda e documentação | 4 | WhatsApp, nota de análise em 1 dia útil, copy sobre contador |
| **Total** | | **30/40** | **Satisfatório — bom fluxo B2B, visual ainda “marketing demais” para um form** |

## Anti-Patterns Verdict

**LLM:** A página não grita “landing genérica”, mas os três cards de benefícios (ícone + título + texto), animação `iconFloat`, gradiente no painel e pills `100% PJ / Validação / Ativação` empilhados acima do formulário são tells de scaffold AI/marketing. O stepper numerado é legítimo (fluxo real em 4 etapas). O auth shell (header/footer ocultos via `b2b-auth-shell`) está correto.

**Detector (`form.phtml`):** Limpo — nenhum antipattern automático no template.

**Browser:** Inspeção visual ao vivo indisponível (MCP browser não carregou nesta sessão). Evidência via HTML ao vivo + código-fonte.

## Overall Impression

O cadastro B2B é funcional e bem pensado para o contexto brasileiro (CNPJ, RF, separação contador vs contato). O maior gap é densidade informacional antes do primeiro campo: três blocos de confiança (subtítulo + meta + benefícios) competem com o CNPJ. A interface deveria parecer mais “balcão de pedidos” e menos “landing de conversão”.

## What's Working

1. **Validação CNPJ com contexto RF** — badge de situação, atividade principal e alerta explícito sobre e-mail/telefone do contador reduzem erro de cadastro.
2. **Auth shell dedicado** — `b2b-auth-shell` remove header, menu e footer; foco no formulário sem distração de catálogo.
3. **Stepper sticky no mobile** — progresso sempre visível durante preenchimento longo.

## Priority Issues

**[P1] Redundância meta + benefícios**
- Por quê: usuário lê três vezes “desconto, crédito, cotações” antes de digitar CNPJ.
- Fix: manter meta pills no mobile (já ocultas <480px) e colapsar benefícios por padrão em todas as larguras, ou fundir em um único bloco.
- Comando: `/impeccable distill b2b/register`

**[P1] Grid idêntico de benefícios (AI card pattern)**
- Por quê: três tiles iguais com SVG gradiente leem como template, não como ferramenta B2B.
- Fix: lista simples ou uma linha de três fatos sem cards aninhados (parcialmente aplicado nesta sessão).
- Comando: `/impeccable quieter b2b/register benefits`

**[P2] Conteúdo dependente de JavaScript** *(corrigido parcialmente)*
- Por quê: seções 2–4 com `display:none` e benefícios com `is-hidden` deixavam formulário incompleto até RequireJS.
- Fix: remover gates inline no HTML; CSS mobile recolhe benefícios; JS só refina.
- Comando: `/impeccable harden b2b/register`

**[P2] Labels 11px uppercase (`account-b2b.css`)**
- Por quê: contraste e legibilidade ruins em formulário denso; padrão de conta, não de cadastro.
- Fix: override para 14px sentence case no register-override (aplicado).
- Comando: `/impeccable typeset b2b/register`

**[P3] Link “Faça login” duplicado**
- Por quê: header do card + `actions-toolbar` repetem a mesma ação.
- Fix: manter só o do topo (próximo ao H1) ou só o do rodapé após submit.
- Comando: `/impeccable clarify b2b/register`

## Persona Red Flags

**Carlos (revendedor apressado, mobile):** Benefícios e meta pills ainda empurram o campo CNPJ abaixo da dobra; trust-note já oculto <768px ajuda, mas benefícios expandidos no HTML até o JS carregar ainda alongam a página.

**Fernanda (primeiro cadastro PJ):** Labels em uppercase confundiam com micro-copy de dashboard; corrigido para sentence case.

**Ana (acessibilidade):** Placeholders em `#aaa` falhavam contraste; override usa `#6b7280`. Toggle de senha com `aria-pressed` está correto.

## Minor Observations

- Gradiente radial no `#b2b-register-shell` é decorativo demais para product UI.
- `register.css` do módulo tem 45+ tokens `--b2b-reg-c*` paralelos ao design system AWA.
- Deploy `setup:static-content:deploy` falhou por erro LESS não relacionado (`_awa-polish-home-pass2-2026-06.less`); CSS override copiado manualmente para `pub/static`.

## Alterações aplicadas nesta sessão

- `form.phtml`: removidos `display:none` das seções e `is-hidden` dos benefícios.
- `register-override.css`: labels legíveis, benefícios mais discretos, SSR desktop dos benefícios.
- `register-form.js`: classe `is-expanded` para disclosure mobile.
