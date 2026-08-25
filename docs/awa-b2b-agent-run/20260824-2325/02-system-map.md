# Mapa do sistema B2B

**Classificação: CONFIRMADA** (código commitado em `bad08baee` + leitura de banco de produção)

## Módulo de cadastro

`GrupoAwamotos_B2B` — frontName `b2b`.

| Peça | Caminho |
|---|---|
| GET cadastro | `Controller/Register/Index.php` |
| POST cadastro | `Controller/Register/Save.php` |
| CNPJ ajax | `Controller/Register/ValidateCnpj.php` |
| Sucesso | `Controller/Register/Success.php` + `Block/Register/Success.php` |
| Template vivo | tema filho `GrupoAwamotos_B2B/templates/register/form.phtml` |
| Wizard JS | `view/frontend/web/js/register-form.js` |
| Layout | `b2b_register_index.xml` |

Etapas no HTML: Empresa, Endereço, Contato, Segurança.

## Aprovação

- Atributo EAV `b2b_approval_status`: `pending`, `data_review`, `approved`, `rejected`, `suspended`.
- Serviço: `Model/CustomerApproval.php`
- Admin: `Controller/Adminhtml/Customer/{Pending,View,Approve,Reject,RequestReview,MassApprove,MassReject}.php`
- Painel comercial: `CommercialPanel/` + ACL `GrupoAwamotos_B2B::commercial_*`
- Crédito separado: `grupoawamotos_b2b_credit_limit` e menu Credito B2B

## Preço e carrinho

- `Model/PriceVisibility.php` (strict B2B, pending sem preço, aprovado sem ERP sem preço)
- Plugins HTML: `HidePricePlugin`, `HideFinalPricePlugin`
- GraphQL (esta branch): `etc/graphql/di.xml`
- JSON-LD: plugin em `GrupoAwamotos_SchemaOrg\Block\ProductSchema`
- Carrinho: `BlockCartAddPlugin`, checkout `BlockCheckoutPlugin`

Config efetiva produção: `b2b_mode=strict`, `hide_price_guests=1`, `show_price_pending=0`.

## Grupos de cliente

| ID | Código |
|---|---|
| 4 | B2B Atacado |
| 5 | B2B VIP |
| 6 | B2B Revendedor |
| 7 | B2B Pendente |
| 8 | B2B Aprovado |

Cadastros com status (produção): approved 8928, pending 48, rejected 1, suspended 1.

## Assistente

`GrupoAwamotos_AiAssistant` — widget `view/frontend/templates/chat/widget.phtml`, `guided-coach.js`, POST `/aiassistant/chat/message`.

## Sectra / ERP

- Pull: `GrupoAwamotos\ERPIntegration\Model\Api\OrderPullManagement`
- Gate: `B2B\Observer\SectraOrderImportGateObserver`, `SectraValidatedOrderPlaceObserver`
- Status em `sales_order.sectra_import_status`
- Log: tabela `grupoawamotos_b2b_sectra_sync_log` (existe no banco)
- Consumers: `erp.order.sync.consumer`, `erp.order.sync.retry.consumer`, `grupoawamotos.b2b.whatsapp.consumer`

## Rotas frontName

- Storefront: `b2b/*`
- Admin: `grupoawamotos_b2b`, `b2b`, `awa_commercial`, `awa_b2b`

## Testes existentes (antes desta execução)

- 43 `*Test.php` em `app/code/GrupoAwamotos`
- Playwright em `tests/e2e/specs/` (dezenas de specs visuais + `b2b-register.spec.ts`)
- PHPUnit B2B: `app/code/GrupoAwamotos/B2B/Test/phpunit.xml`
