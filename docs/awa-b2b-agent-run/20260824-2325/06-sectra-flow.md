# Fluxo Sectra (Magento → ERP)

**Direção confirmada:** Magento expõe pedidos; o ERP puxa (`OrderPullManagement`). Não há evidência de push síncrono no checkout.

## Componentes

| Papel | Classe |
|---|---|
| API pull | `GrupoAwamotos\ERPIntegration\Model\Api\OrderPullManagement` |
| Gate no place | `GrupoAwamotos\B2B\Model\Sectra\OrderImportGate` |
| Observer 1ª persistência | `SectraOrderImportGateObserver` (`sales_order_save_after`) |
| Observer validado | `SectraValidatedOrderPlaceObserver` |
| Log | `grupoawamotos_b2b_sectra_sync_log` (colunas: log_id, event_type, level, customer_id, order_id, cnpj, sectra_chave, message, created_at) |
| Status | `sales_order.sectra_import_status` |
| Admin fila | `grupoawamotos_b2b/sectraQueue/index` ACL `GrupoAwamotos_B2B::sectra_queue` |
| Cron | backfill/release em `OrderImportGate`; `StuckOrderCleanup`; `SyncSectraProspectStatus` |

## Idempotência

O observer do gate **só aplica se `sectra_import_status` é NULL**, evitando reavaliar invoice/comentários.

`OrderPullManagement` ignora status em `NON_IMPORTABLE`.

Há tabela `grupoawamotos_erp_order_retry` e consumers `erp.order.sync.*`.

## Payload

Não foi alterado o contrato. O pull monta pedido Magento já persistido. Dados pessoais não foram copiados para o repositório.

## Riscos observados

- 17 pedidos com status NULL (backfill cron deve cobrir).
- 37 cancelados antes da importação.
- 1 `import_failed`.
- Valor `canceled_smoke` fora do enum (anomalia legada).

## Testes desta execução

Nenhum pedido criado. Nenhum reprocessamento admin. Nenhum stub Sectra (staging ausente).
