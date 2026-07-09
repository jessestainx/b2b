# AWA Motos — Adobe App Builder: offload de notificação WhatsApp

Prova de conceito **funcional** (testes passando, build validado) que usa o
[Adobe Developer App Builder](https://developer.adobe.com/app-builder/) para
resolver um problema real do código atual: `GrupoAwamotos\WhatsAppCommerce`
envia a confirmação de WhatsApp **de forma síncrona**, dentro do próprio
request de checkout/admin, segurando um worker PHP-FPM neste VPS (já limitado
em RAM/CPU, ver `AGENTS.md`) enquanto espera a resposta da API do WhatsApp.

```
sales_order_place_after (Observer)
  └─ MessageSender::sendOrderNotification()
       └─ WhatsappSenderInterface::sendMessage()   ← chamada HTTP síncrona,
                                                      bloqueia o checkout
```

## O que este projeto faz

Uma action serverless (`notify-order`) roda no Adobe I/O Runtime e assume essa
chamada:

```
sales_order_place_after (Observer)
  └─ MessageSender::sendOrderNotification()
       └─ AppBuilderDispatcher::dispatch()   ← POST assinado (HMAC), timeout
            │                                   curto (3s por padrão)
            ▼
       Adobe I/O Runtime: actions/notify-order
            └─ chama a API do WhatsApp (fora do VPS, auto-scale, retry
               gerenciado pela Adobe)
```

Se o offload estiver desabilitado, a URL não estiver configurada, ou a
chamada falhar/der timeout, `MessageSender` cai automaticamente no envio
síncrono atual — **zero risco de regressão**, comportamento 100%
opt-in via admin (`Lojas → Configurações → AWA Motos → WhatsApp Commerce →
Offload para Adobe App Builder`).

## Por que isso é valioso aqui (não é só "rodar um exemplo")

- **Libera recursos do VPS no pico de tráfego**: o checkout não fica mais
  refém da latência (ou de uma instabilidade) da API do WhatsApp/Meta.
- **Idempotência de graça**: a action usa o Adobe State SDK para não reenviar
  a mesma notificação em caso de retry, algo que o código PHP atual não faz.
- **Escala automática e isolada**: picos de pedidos (campanhas, Black Friday)
  não competem por PHP-FPM/Redis com o resto da loja.
- **Zero acoplamento de rede sensível**: o payload do webhook já contém tudo
  que a action precisa (pedido, telefone, evento) — não é necessário expor
  banco de dados, ERP ou Redis para a internet.

## Estrutura

```
integrations/appbuilder-whatsapp-notify/
├── node_modules/          # aio CLI (dev tooling, não roda em produção)
├── package.json           # devDependency: @adobe/aio-cli
└── app/                   # o projeto App Builder de verdade
    ├── app.config.yaml    # manifest: 1 action (notify-order)
    ├── actions/
    │   ├── notify-order/index.js   # verifica HMAC, chama WhatsApp Cloud API
    │   └── utils.js
    ├── test/notify-order.test.js   # 5 testes (assinatura, validação,
    │                                 envio, idempotência, erro da API)
    └── .env                # credenciais locais (nunca commitar)
```

No lado Magento (`app/code/GrupoAwamotos/WhatsAppCommerce`):

- `Model/AppBuilderDispatcher.php` — assina o payload (HMAC-SHA256) e faz o
  POST com timeout curto via `Magento\Framework\HTTP\Client\Curl`.
- `Model/MessageSender.php` — tenta o dispatcher antes do envio síncrono.
- `Helper/Config.php` + `etc/system.xml` + `etc/config.xml` — novo grupo de
  configuração `app_builder_offload` (desabilitado por padrão).

## Rodando os testes da action

```bash
cd integrations/appbuilder-whatsapp-notify/app
npm test
```

## Deploy (único passo manual que exige uma conta Adobe)

Tudo neste repositório já está pronto (`aio app build` valida e empacota a
action com sucesso). Falta só autenticar e publicar — isso exige um login
interativo que só o dono da conta Adobe Developer Console pode fazer:

```bash
cd integrations/appbuilder-whatsapp-notify
npx aio login                       # abre o navegador para login Adobe
npx aio console org select          # escolher a org
npx aio console project select      # ou 'project create' se ainda não existir
npx aio console workspace select    # ou 'workspace create'
cd app
../node_modules/.bin/aio app use --no-input
../node_modules/.bin/aio app deploy
```

Após o deploy, o comando imprime a URL pública da action, algo como:

```
https://<namespace>.adobeioruntime.net/api/v1/web/awa-whatsapp/notify-order
```

Configure os secrets da action (Developer Console → Workspace → a própria
action, ou via `.env` + redeploy):

- `WEBHOOK_SHARED_SECRET` — qualquer string aleatória forte.
- `WHATSAPP_TOKEN` / `WHATSAPP_PHONE_ID` — credenciais da WhatsApp Cloud API
  (Meta for Developers).

E no admin do Magento (mesmo `shared_secret` dos dois lados):

1. `Lojas → Configurações → AWA Motos → WhatsApp Commerce`
2. Seção **Offload para Adobe App Builder** → Habilitar = Sim
3. **URL da Action** = a URL impressa pelo deploy
4. **Segredo Compartilhado** = o mesmo valor de `WEBHOOK_SHARED_SECRET`
5. `bin/magento cache:flush`

## Limpeza de disco (opcional)

`node_modules/` e `app/node_modules/` (~950 MB) só existem para
desenvolvimento/deploy da action — nada disso roda no VPS em produção. Pode
apagar com segurança após o deploy e reinstalar com `npm install` quando
precisar alterar a action de novo.
