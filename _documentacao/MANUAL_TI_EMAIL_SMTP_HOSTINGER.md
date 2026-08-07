# Manual TI — E-mail / SMTP Hostinger (Magento)

**Versão:** 1.0
**Data:** 2026-08-03
**Status:** ATUAL (substitui `docs/CONFIGURACAO_EMAIL_FINAL.md`)

> Segredos (senha SMTP, tokens) **não** ficam neste arquivo.

---

## 1. Configuração ativa em produção

| Parâmetro | Valor |
|-----------|--------|
| Extensão | MagePal Gmail SMTP App + `GrupoAwamotos_SmtpFix` |
| Conta | `sac@awamotos.com` |
| Host | `smtp.hostinger.com` |
| Porta | `465` |
| Segurança | SSL |
| Auth | LOGIN |
| Identidades Magento | todas → `sac@awamotos.com` |
| Envio assíncrono vendas | `sales_email/general/async_sending = 1` |

Paths Magento relevantes:
- `system/gmailsmtpapp/*`
- `system/smtp/*`
- `trans_email/ident_*`

Backup da troca: `/home/deploy/backups/awa-smtp-hostinger-20260803-032048/`

---

## 2. DNS `awamotos.com` (verificado 2026-08-03)

| Registro | Estado |
|----------|--------|
| MX | `mx1/mx2.hostinger.com` |
| SPF | `v=spf1 include:_spf.mail.hostinger.com include:spf-ll.xmailer.com.br ~all` |
| DKIM | selector `default._domainkey` presente |
| DMARC | `v=DMARC1; p=none` (frouxo — evoluir depois) |
| Autodiscover | CNAME Hostinger OK |

**Nota:** `awamotos.com.br` usa outro stack (OneDNS/Wabstore). Remetente Magento **não** deve voltar para `@awamotos.com.br` enquanto o SMTP for Hostinger em `awamotos.com`.

---

## 3. O que NÃO usar para e-mail Magento

| Recurso | Motivo |
|---------|--------|
| Token Agentic Mail / hMail API | Para agentes/MCP; Magento precisa de SMTP |
| MCP `mcp.mail.hostinger.com` | IDE/agentes, não transporte da loja |
| Gmail `b2b.awamotos@gmail.com` | Substituído em 2026-08-03 |
| PHP `mail()` / Postfix local | Entregabilidade ruim |

---

## 4. Teste rápido (somente com autorização)

No admin Magento:
1. **Stores → Configuration → Advanced → System → SMTP Configuration (MagePal)**
2. Usar botão de teste / validação do módulo
3. Confirmar inbox em `sac@awamotos.com` e pasta spam

CLI (exemplo — não rodar sem OK):
- Enviar template via `TransportBuilder` como feito na validação de 2026-08-03

---

## 5. Rollback

1. Restaurar valores de `/home/deploy/backups/awa-smtp-hostinger-20260803-032048/core_config_email.json` em `core_config_data`
2. `php bin/magento cache:clean config`
3. Retestar AUTH SMTP

---

## 6. Próximos passos de e-mail (sem Z-API)

1. Branding: logo + header/footer AWA em `design/email`
2. Endurecer DMARC de `p=none` → `quarantine` (após validar DKIM estável)
3. Remover template órfão Amasty Abandoned Cart no banco
4. Monitorar bounces via logs Hostinger (hPanel → Email Logs)

---

## 7. Histórico

| Data | Alteração |
|------|-----------|
| 2026-08-03 | Migração Gmail → Hostinger SMTP `sac@awamotos.com`; AUTH e envio Magento validados |
