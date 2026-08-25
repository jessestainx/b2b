# Máquina de estados B2B (real)

## Cadastro / cliente

Estados **reais** no EAV `b2b_approval_status` (produção e código):

```
pending --> data_review --> approved
   |            |              |
   |            v              v
   +-------> rejected      suspended
   |
   +-------> approved (direto)
```

Aliases adicionados nesta branch (sem novo valor no banco):

| Vocabulário do briefing | Valor persistido |
|---|---|
| under_review | `data_review` |
| needs_information | `data_review` |
| blocked | `suspended` |

Transições implementadas no serviço:

| Método | Destino |
|---|---|
| `setCustomerPending` | pending |
| `requestDataReview` | data_review |
| `approveCustomer` | approved (+ grupo B2B / CNAE) |
| `rejectCustomer` | rejected |
| `suspendCustomer` | suspended |

Aprovação comercial ≠ crédito: limite vive em `grupoawamotos_b2b_credit_limit`.

Preços: somente `approved` (e com ERP se `hide_price_no_erp=1`). Pending/data_review/rejected/suspended/sem status: sem tabela (fail-closed nesta branch).

## Pedido / Sectra

Status reais em `sales_order.sectra_import_status` (produção):

| Status | Qtd observada |
|---|---|
| NULL | 17 |
| awaiting_customer_validation | 1 |
| ready_for_import | 1 |
| imported | 27 |
| import_failed | 1 |
| order_cancelled_before_erp_import | 37 |
| not_applicable | 1 |
| canceled_smoke | 1 (legado/anomalia) |

Fluxo:

```
place order
  -> SectraOrderImportGateObserver (sales_order_save_after, só se status NULL)
  -> awaiting_customer_validation | blocked_* | ready_for_import
  -> SectraValidatedOrderPlaceObserver (se cliente aprovado e validado no ERP) => ready_for_import
  -> OrderPullManagement.getPendingOrders (Magento -> Sectra)
  -> imported | import_failed
cancelamento antes do pull => order_cancelled_before_erp_import
```

Constantes: `GrupoAwamotos\B2B\Model\Sectra\SectraImportStatus`.
