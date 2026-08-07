# Status execução noturna — 2026-08-03

Autorização: configurar/corrigir tudo o necessário, testar; **Z-API por último**.

## Concluído e testado

| Item | Resultado |
|------|-----------|
| SMTP Hostinger `sac@awamotos.com` | AUTH + envio OK |
| Logo e-mail AWA | `awa_logo_email.png` ativo |
| Header/footer branded | Tema filho Magento_Email |
| Templates DB 2/6/7/11 | Envio OK; logo+barra+rodapé |
| Template Amasty órfão | Removido |
| WhatsAppCommerce Fase 1 | Código corrigido (feature continua OFF) |
| Segmentos WA opt-in | Consulta OK (2 clientes) |
| Carrinho abandonado | 22 registros / 9 recuperados |
| Pagamento PIX offline | `banktransfer` ativado (além de A Combinar) |
| Manuais | Executivo + Vendedoras + TI SMTP |

## Z-API (deixado por último — ação humana)

O token Agentic Mail havia sido gravado por engano em Client-Token Z-API.  
**Foi removido** de B2B e ERP para não autenticar WhatsApp com credencial errada.

**Pendência ao acordar:** colar o **Client-Token real da Z-API** em:
- `grupoawamotos_erp/whatsapp/zapi_client_token`
- `grupoawamotos_b2b/whatsapp/client_token` (se a API exigir)

Instâncias Z-API e demais flags WhatsApp ERP/B2B permanecem como estavam (`enabled=1`).

## Não feito de propósito (risco sem você acordado)

- 2FA admin (bloquearia logins sem app autenticador)
- Gateway cartão real / Payment Services
- Ligar `whatsapp_commerce/general` ou SmartSuggestions WA (fila 100 pending)
- Endurecer DMARC DNS
- `setup:upgrade` / `di:compile` / static deploy completo

## Backups

- `/home/deploy/backups/awa-smtp-hostinger-20260803-032048/`
- `/home/deploy/backups/awa-email-branding-20260803-033158/`
- `/home/deploy/backups/awa-whatsappcommerce-fase1-20260803-033641/`
- `/home/deploy/backups/awa-comercial-users-20260803-030611/`

## Contas comerciais

URL: https://awamotos.com/admin  
Senha compartilhada temporária: ver chat anterior (`Awa@Vendas2026!`)  
Login supervisora: `supervisora`
