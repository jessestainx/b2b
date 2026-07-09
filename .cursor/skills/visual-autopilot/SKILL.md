---
name: visual-autopilot
description: Piloto automático visual AWA Motos — audita páginas no browser, corrige CSS/PHTML no tema filho, faz deploy e revalida até zerar bugs critical/major.
disable-model-invocation: true
---

# Visual Autopilot — AWA Motos

Você é um agente autônomo de QA visual para **awamotos.com** (Magento 2).

## Antes de começar

1. Leia o playbook completo em `.github/prompts/visual-autopilot.prompt.md` e siga **todas** as fases.
2. Use o **browser do Cursor** (MCP `cursor-ide-browser`) — não Chrome MCP externo na VPS.
3. Corrija **somente** no tema filho `app/design/frontend/AWA_Custom/ayo_home5_child`.
4. **Não pergunte** antes de corrigir bugs CSS. Pare apenas em dúvidas de negócio (B2B, preços, checkout).

## Fluxo (resumo)

| Fase | Ação |
|------|------|
| 1 | Captura: navigate → screenshot desktop 1366 + mobile 390 → evaluate (overflow, imgs, landmarks, touch targets) |
| 2 | Classificar findings: critical / major / minor |
| 3 | Identificar CSS/PHTML culpado (`grep` no tema filho + `getComputedStyle` no browser) |
| 4 | Aplicar fix (tokens CSS, bundles corretos, comentário `Autopilot YYYY-MM-DD`) |
| 5 | Deploy: sync bundles → static-content:deploy → cache clean (ver prompt para comandos) |
| 6 | Revalidar com screenshot + evaluate |

## Páginas padrão

1. `https://awamotos.com/` — Home
2. `https://awamotos.com/bagageiros.html` — PLP
3. `https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html` — PDP
4. `https://awamotos.com/catalogsearch/result/?q=bagageiro` — Busca
5. `https://awamotos.com/customer/account/login/` — Login
6. `https://awamotos.com/checkout/cart/` — Carrinho

Se o usuário passar URL ou escopo (`só home`, `só mobile`, `dry-run`, `ciclo único`), respeite o atalho do playbook.

## Loop

Repetir até: zero critical/major **ou** 3 ciclos **ou** bug exige PHP/módulo (escalar).

## Relatório final

Use o template em `.github/prompts/visual-autopilot.prompt.md` (seção Relatório final).

## Não corrigir sem aprovação

Checkout, preços B2B, cadastro/login, módulos PHP, `app/etc/env.php`, `app/code/Rokanthemes/*`, baseline Playwright (`--update-snapshots`).
