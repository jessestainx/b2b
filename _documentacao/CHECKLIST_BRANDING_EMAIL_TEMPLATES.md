# Checklist — Branding dos templates de e-mail AWA

**Status:** APLICADO E TESTADO (2026-08-03)
**Data:** 2026-08-03
**Depende de:** autorização explícita + asset do logo

## Objetivo

Padronizar visual dos e-mails transacionais Magento com identidade AWA (logo, cores, header/footer), mantendo o SMTP Hostinger já ativo.

## Situação atual (CONFIRMADA)

- 11 templates custom no banco (`grupoawamotos_*` IDs 2–11) em PT-BR
- Header/footer ainda defaults Magento (`design_email_header_template` / `footer`)
- Sem override `Magento_Email` no tema filho
- `design/email/logo` não configurado
- Existe guia: `docs/email-template-style-guide.md` e `docs/EMAIL_TEMPLATES_GUIA.md`
- Template órfão: ID 1 `Amasty: Abandoned Cart Reminder` (módulo Amasty inexistente)

## Escopo proposto (quando autorizado)

1. Upload do logo e-mail em `Stores → Configuration → Design → Transactional Emails → Logo`
2. Override no tema filho:
   - `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Email/email/header.html`
   - `.../footer.html`
3. Usar tokens/cores AWA (vermelho marca) + preheader
4. Revisar IDs 2–11 para herdar header/footer novos
5. Desativar/remover template Amasty órfão
6. Teste: pedido, fatura, reset senha, abandono de carrinho
7. Deploy estático **somente tema filho** se necessário + `cache:clean` de config/layout/block_html

## Fora de escopo agora

- Troca de provedor SMTP (já Hostinger)
- Z-API Client-Token (deixado por último a pedido)
- Campanhas marketing em massa

## Critério de sucesso

- Logo AWA visível no topo
- From = `sac@awamotos.com`
- Layout legível no Gmail mobile
- Sem regressão nos disparos B2B/vendas
