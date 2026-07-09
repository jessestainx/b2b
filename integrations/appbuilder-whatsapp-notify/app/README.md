# notify-order — Adobe I/O Runtime action

Veja a explicação completa (arquitetura, motivação, deploy) em
[`../README.md`](../README.md).

## Comandos rápidos

```bash
npm install          # instala deps da action (@adobe/aio-sdk, node-fetch)
npm test             # roda os testes (test/notify-order.test.js)
npm run lint         # eslint em actions/ e test/

../node_modules/.bin/aio app build     # valida e empacota a action
../node_modules/.bin/aio app deploy    # requer login Adobe (ver README pai)
```

## `actions/notify-order/index.js`

- Recebe o webhook assinado (HMAC-SHA256) disparado por
  `GrupoAwamotos\WhatsAppCommerce\Model\AppBuilderDispatcher`.
- Valida `order_id`, `event`, `phone`.
- Usa o State SDK para não reenviar a mesma notificação em retries
  (idempotência por `order_id:event`, TTL de 6h).
- Chama a WhatsApp Cloud API (Meta) diretamente da infraestrutura da Adobe.

Inputs configurados em `app.config.yaml` (definidos via `.env` / secrets do
workspace no Developer Console):

| Input | Descrição |
|---|---|
| `WEBHOOK_SHARED_SECRET` | precisa ser igual ao configurado no admin do Magento |
| `WHATSAPP_TOKEN` | token da WhatsApp Cloud API |
| `WHATSAPP_PHONE_ID` | phone number ID da WhatsApp Cloud API |
