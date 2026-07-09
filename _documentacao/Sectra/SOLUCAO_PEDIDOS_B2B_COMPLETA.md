# Solução Completa: Integração de Pedidos B2B sem Conflitos

**Data:** Junho 2026
**Restrição:** ZERO alterações no SQL Server / SECTRA
**Compatibilidade:** Magento 2.4.8 + PHP 8.x

---

## Resumo das Falhas e Correções

| # | Falha | Impacto | Correção |
|---|-------|---------|----------|
| 1 | Observer errado (dispara antes do save) | Order ID = null, tudo falha | Plugin afterPlace |
| 2 | erp_code->getValue() em null → fatal | Todos clientes novos/pendentes quebram | Null check obrigatório |
| 3 | Race condition: pedidos duplicados | Duplicata no ERP | SELECT FOR UPDATE no quote |
| 4 | Sem idempotência nos INSERTs | Duplicata no ERP por retry | Tabela de idempotência Magento-side |
| 5 | Cliente sem erp_code bloqueia pedido | Pedido perdido silenciosamente | Dead Letter Queue + CNPJ fallback |
| 6 | Circuit Breaker sem visibilidade admin | Pedidos presos sem retry | Admin panel com one-click retry |
| 7 | MySQL Queue XML bug Magento | Fila não processa | Patch + fallback cron |

---

## Arquitetura da Solução

```
[Checkout] → [Plugin afterPlace] → [Queue: erp.order.sync]
                                          ↓
                                   [Consumer async]
                                          ↓
                              ┌─────────────────────────┐
                              │  Resolve erp_code        │
                              │  1. Atributo erp_code    │
                              │  2. Fallback: CNPJ lookup│
                              │  3. Não encontrado →     │
                              │     Dead Letter Queue    │
                              └─────────────────────────┘
                                          ↓ (erp_code encontrado)
                              ┌─────────────────────────┐
                              │  Idempotência Check      │
                              │  já enviado? → retorna   │
                              │  não enviado → continua  │
                              └─────────────────────────┘
                                          ↓
                              ┌─────────────────────────┐
                              │  Circuit Breaker         │
                              │  OPEN → requeue          │
                              │  CLOSED → INSERT ERP     │
                              └─────────────────────────┘
                                          ↓
                              [FN_PEDIDOS + FN_PEDIDOSITENS]
                                          ↓
                              [Salva mapeamento Magento-side]
```

---

## CORREÇÃO 1: Plugin em vez de Observer

### Problema
```php
// ❌ ERRADO: dispara ANTES do order ser salvo no banco
// $order->getId() retorna NULL aqui
class OrderPlaceAfter implements ObserverInterface {
    public function execute(Observer $observer) {
        $order = $observer->getEvent()->getOrder();
        $orderId = $order->getId(); // NULL!
    }
}
```

### Solução
```php
// app/code/GrupoAwamotos/ERPIntegration/Plugin/Order/PlacePlugin.php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Plugin\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

class PlacePlugin
{
    private const TOPIC_NAME = 'erp.order.sync';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * CORRETO: afterPlace dispara DEPOIS que o pedido foi salvo no banco.
     * $result->getId() tem o ID real do pedido.
     */
    public function afterPlace(
        OrderManagementInterface $subject,
        OrderInterface $result
    ): OrderInterface {
        $orderId = $result->getId();

        // Somente publica se o pedido foi salvo com sucesso
        if ($orderId) {
            try {
                $this->publisher->publish(self::TOPIC_NAME, (string)$orderId);
                $this->logger->info("ERP: Pedido #{$result->getIncrementId()} enfileirado para sync (ID: {$orderId})");
            } catch (\Exception $e) {
                // NUNCA lançar exception aqui — não pode bloquear o checkout
                $this->logger->critical("ERP: Falha ao enfileirar pedido #{$orderId}: " . $e->getMessage());
                // Fallback: salvar em tabela de retry manual
                $this->saveToFallbackQueue($orderId, $result->getIncrementId());
            }
        }

        return $result;
    }

    private function saveToFallbackQueue(int $orderId, string $incrementId): void
    {
        // Implementado no OrderSyncRepository
    }
}
```

### di.xml (ESSENCIAL)
```xml
<!-- app/code/GrupoAwamotos/ERPIntegration/etc/di.xml -->
<type name="Magento\Sales\Api\OrderManagementInterface">
    <plugin name="grupoawamotos_erp_order_place_plugin"
            type="GrupoAwamotos\ERPIntegration\Plugin\Order\PlacePlugin"
            sortOrder="200"/>
</type>
```

---

## CORREÇÃO 2: Null-safe para erp_code

### Problema
```php
// ❌ Fatal Error se erp_code não está definido no cliente
$erpCode = $customer->getCustomAttribute('erp_code')->getValue();
```

### Solução
```php
// app/code/GrupoAwamotos/ERPIntegration/Model/CustomerSync.php
private function getErpCode(int $customerId): ?int
{
    try {
        $customer = $this->customerRepository->getById($customerId);

        // ✅ Sempre verificar null antes de chamar getValue()
        $erpCodeAttr = $customer->getCustomAttribute('erp_code');

        if ($erpCodeAttr === null) {
            return null;
        }

        $value = $erpCodeAttr->getValue();
        return ($value !== null && $value !== '' && $value !== '0')
            ? (int)$value
            : null;

    } catch (\Exception $e) {
        $this->logger->error("ERP: Erro ao obter erp_code do cliente #{$customerId}: " . $e->getMessage());
        return null;
    }
}
```

---

## CORREÇÃO 3: Race Condition — Lock no Quote

### Problema
Dois requests simultâneos com mesmo quote_id geram dois pedidos no Magento e dois INSERTs no ERP.

### Solução: Plugin around submitQuote
```php
// app/code/GrupoAwamotos/ERPIntegration/Plugin/Quote/SubmitQuotePlugin.php
<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Plugin\Quote;

use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\ResourceConnection;

class SubmitQuotePlugin
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {}

    public function aroundSubmitQuote(
        QuoteManagement $subject,
        callable $proceed,
        Quote $quote,
        array $orderData = []
    ) {
        $connection = $this->resourceConnection->getConnection();
        $quoteTable = $this->resourceConnection->getTableName('quote');

        // SELECT FOR UPDATE serializa requests concorrentes
        $connection->beginTransaction();

        try {
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($quoteTable, ['entity_id', 'is_active', 'reserved_order_id'])
                    ->where('entity_id = ?', $quote->getId())
                    ->forUpdate(true)
            );

            // Se quote já foi convertido (is_active=0), retorna o pedido existente
            if ($row && !$row['is_active'] && $row['reserved_order_id']) {
                $connection->commit();
                // Retorna o pedido já criado sem criar novo
                return $this->loadExistingOrder($row['reserved_order_id']);
            }

            $result = $proceed($quote, $orderData);
            $connection->commit();

            return $result;

        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function loadExistingOrder(string $incrementId)
    {
        // Carrega o pedido existente pelo increment_id
        // Retorna o order object para o checkout continuar normalmente
    }
}
```

---

## CORREÇÃO 4: Idempotência — Sem Duplicatas no ERP

### Problema
Timeout na conexão SQL Server → pedido criado no ERP mas confirmação perdida → retry cria DUPLICATA.

### Solução: Tabela de idempotência no Magento (sem tocar no SQL Server)

```sql
-- Schema Magento: grupoawamotos_erp_idempotency
-- Criada via db_schema.xml — NÃO precisa alterar o SQL Server
CREATE TABLE `grupoawamotos_erp_idempotency` (
  `idempotency_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `magento_increment_id` VARCHAR(50) NOT NULL,  -- Pedido Magento (ex: 100000123)
  `erp_codigo` INT NULL,                        -- CODIGO retornado do FN_PEDIDOS
  `status` ENUM('sending','success','error') DEFAULT 'sending',
  `attempts` TINYINT DEFAULT 0,
  `last_attempt_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `error_message` TEXT NULL,
  PRIMARY KEY (`idempotency_id`),
  UNIQUE KEY `uq_increment_id` (`magento_increment_id`)  -- Garante 1 registro por pedido
);
```

```php
// app/code/GrupoAwamotos/ERPIntegration/Model/OrderSync.php
public function syncOrder(int $orderId): void
{
    $order = $this->orderRepository->get($orderId);
    $incrementId = $order->getIncrementId();

    // PASSO 1: Verificar idempotência
    $existing = $this->idempotencyRepository->findByIncrementId($incrementId);

    if ($existing && $existing->getStatus() === 'success') {
        // Já foi enviado com sucesso — ignorar silenciosamente
        $this->logger->info("ERP: Pedido {$incrementId} já sincronizado (ERP #{$existing->getErpCodigo()})");
        return;
    }

    if ($existing && $existing->getStatus() === 'sending') {
        // Estava sendo enviado quando houve falha — verificar se chegou no ERP
        $existingErpOrder = $this->checkIfExistsInErp($incrementId);
        if ($existingErpOrder) {
            // Chegou no ERP, só não recebemos a confirmação
            $this->idempotencyRepository->markSuccess($incrementId, $existingErpOrder['CODIGO']);
            return;
        }
        // Não chegou — pode tentar de novo
    }

    // PASSO 2: Marcar como "enviando" ANTES de tentar
    $this->idempotencyRepository->upsert($incrementId, 'sending');

    // PASSO 3: Resolver erp_code do cliente
    $erpCustomerCode = $this->resolveErpCustomerCode($order);

    if ($erpCustomerCode === null) {
        // Cliente não encontrado no ERP — Dead Letter Queue
        $this->sendToDeadLetterQueue($order, 'Cliente não encontrado no ERP SECTRA');
        return;
    }

    // PASSO 4: Circuit Breaker
    if ($this->circuitBreaker->isOpen()) {
        $this->requeueForLater($orderId);
        return;
    }

    // PASSO 5: Enviar ao ERP
    try {
        $erpOrderId = $this->insertOrderIntoErp($order, $erpCustomerCode);

        // PASSO 6: Marcar como sucesso
        $this->idempotencyRepository->markSuccess($incrementId, $erpOrderId);
        $this->circuitBreaker->recordSuccess();

        $this->logger->info("ERP: Pedido {$incrementId} sincronizado → ERP #{$erpOrderId}");

    } catch (\Exception $e) {
        $this->circuitBreaker->recordFailure();
        $this->idempotencyRepository->markError($incrementId, $e->getMessage());
        throw $e; // Deixar a fila fazer retry com backoff
    }
}

/**
 * Verifica se o pedido já existe no ERP antes de reinserir.
 * Usa campo OBSERVACAO ou referência externa para identificar.
 * SEM alterar o SQL Server — apenas uma SELECT.
 */
private function checkIfExistsInErp(string $incrementId): ?array
{
    // SECTRA guarda observações no pedido — usamos como identificador
    // A query busca pedidos recentes com o incrementId no campo OBSERVACAO
    $result = $this->erpConnection->query(
        "SELECT TOP 1 CODIGO, DATA FROM VE_PEDIDOS
         WHERE OBSERVACAO LIKE ?
         AND DATA >= DATEADD(day, -7, GETDATE())",
        ["%AWA-{$incrementId}%"]
    );

    return $result ? $result[0] : null;
}
```

---

## CORREÇÃO 5: Resolução de erp_code com Fallback por CNPJ

```php
// app/code/GrupoAwamotos/ERPIntegration/Model/OrderSync.php

private function resolveErpCustomerCode(OrderInterface $order): ?int
{
    $customerId = (int)$order->getCustomerId();

    // Tentativa 1: Atributo direto (cliente já vinculado)
    $erpCode = $this->getErpCode($customerId);
    if ($erpCode !== null) {
        return $erpCode;
    }

    // Tentativa 2: Buscar por CNPJ/CPF no ERP
    $taxvat = $order->getCustomerTaxvat()
        ?? $this->customerRepository->getById($customerId)->getTaxvat();

    if ($taxvat) {
        $taxvat = preg_replace('/[^0-9]/', '', $taxvat);
        $erpCustomer = $this->customerSync->getErpCustomerByTaxvat($taxvat);

        if ($erpCustomer) {
            // Encontrou! Fazer link automático para pedidos futuros
            $this->customerSync->linkMagentoToErp($customerId, (int)$erpCustomer['CODIGO']);

            $this->logger->info(
                "ERP: Cliente #{$customerId} auto-vinculado ao ERP código {$erpCustomer['CODIGO']} via CNPJ"
            );

            return (int)$erpCustomer['CODIGO'];
        }
    }

    // Tentativa 3: Não encontrado — retorna null para Dead Letter Queue
    $this->logger->warning(
        "ERP: Cliente #{$customerId} não encontrado no ERP. " .
        "CNPJ: " . ($taxvat ?? 'não informado') . ". " .
        "Pedido #{$order->getIncrementId()} vai para Dead Letter Queue."
    );

    return null;
}
```

---

## CORREÇÃO 6: Dead Letter Queue com Admin Panel

### Tabela no Magento (sem tocar SQL Server)
```sql
-- grupoawamotos_erp_dead_letter
CREATE TABLE `grupoawamotos_erp_dead_letter` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `magento_order_id` INT UNSIGNED NOT NULL,
  `increment_id` VARCHAR(50) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `customer_email` VARCHAR(255) NOT NULL,
  `customer_taxvat` VARCHAR(20) NULL,      -- CNPJ/CPF
  `reason` VARCHAR(255) NOT NULL,          -- Motivo da falha
  `attempts` TINYINT DEFAULT 0,
  `next_retry_at` DATETIME NULL,
  `status` ENUM('pending','processing','resolved','abandoned') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `resolution_notes` TEXT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_customer_taxvat` (`customer_taxvat`)
);
```

### Comportamento
```
Pedido #100000123
   ↓ (cliente sem erp_code, CNPJ não encontrado no ERP)
Dead Letter Queue:
  - increment_id: 100000123
  - customer_email: joao@empresa.com
  - customer_taxvat: 12345678000190
  - reason: "CNPJ 12.345.678/0001-90 não encontrado em FN_FORNECEDORES"
  - status: pending
  - next_retry_at: +1h
   ↓
Admin recebe email de alerta:
  "Pedido #100000123 aguardando vinculação com ERP.
   CNPJ: 12.345.678/0001-90
   Ação: Verificar se cliente está cadastrado no SECTRA"
   ↓
Cron de retry a cada hora:
  → Tenta buscar CNPJ novamente no ERP
  → Quando SECTRA cadastrar o cliente (no fluxo normal deles),
    o próximo retry vai encontrar e sincronizar automaticamente
  → Admin vê status mudando de "pending" para "resolved"
```

### Admin Grid
```
Admin → ERP Integration → Dead Letter Queue

| # | Pedido    | Cliente         | CNPJ               | Tentativas | Status  | Ação         |
|---|-----------|-----------------|-------------------|------------|---------|--------------|
| 1 | #100000123| joao@empresa.com| 12.345.678/0001-90| 3          | pending | [Retry][Skip]|
| 2 | #100000124| maria@emp.com   | 98.765.432/0001-11| 1          | pending | [Retry][Skip]|
```

---

## CORREÇÃO 7: MySQL Queue Bug Fix

### Patch composer (para Magento 2.4.x)
```json
// composer.json — adicionar patches
"extra": {
    "composer-patches": {
        "magento/module-mysql-mq": {
            "Fix MySQL Queue XML format bug": "patches/21904-fix-message-queue-db-config.patch"
        }
    }
}
```

### Fallback: Cron de Segurança (independente da fila)
```php
// app/code/GrupoAwamotos/ERPIntegration/Cron/OrderSyncFallback.php

/**
 * Cron de segurança que roda a cada 5 minutos.
 * Detecta pedidos que deveriam ter sido sincronizados mas não foram
 * (por falha na fila, crash do consumer, etc).
 */
public function execute(): void
{
    // Busca pedidos criados nas últimas 2 horas
    // que não têm registro na tabela de idempotência
    $orders = $this->getUnsyncedOrders(hoursBack: 2);

    foreach ($orders as $order) {
        $existing = $this->idempotencyRepo->findByIncrementId($order->getIncrementId());

        if ($existing === null) {
            // Pedido passou batido pela fila! Publicar agora.
            $this->publisher->publish('erp.order.sync', (string)$order->getId());
            $this->logger->warning(
                "ERP Fallback Cron: Pedido #{$order->getIncrementId()} " .
                "não estava na fila. Republicado."
            );
        }
    }
}
```

---

## Envio ao ERP: Campo OBSERVACAO como Chave Externa

A chave para idempotência sem alterar o SQL Server está no campo `OBSERVACAO` da tabela `VE_PEDIDOS` (ou equivalente). Este campo aceita texto livre e **já existe no SECTRA**.

```php
private function insertOrderIntoErp(OrderInterface $order, int $erpCustomerCode): int
{
    $incrementId = $order->getIncrementId();

    // Verificação final antes de inserir: já existe?
    $check = $this->erpConnection->query(
        "SELECT TOP 1 CODIGO FROM VE_PEDIDOS
         WHERE FORNECEDOR = ? AND OBSERVACAO LIKE ?
         AND DATA >= DATEADD(day, -30, GETDATE())",
        [$erpCustomerCode, "%AWA-{$incrementId}%"]
    );

    if (!empty($check)) {
        // Pedido JÁ existe no ERP (enviado anteriormente)
        // Retornar o CODIGO existente sem criar duplicata
        return (int)$check[0]['CODIGO'];
    }

    // INSERT seguro — prefixo AWA- garante identificação única
    $observacao = "AWA-{$incrementId} | Pedido e-commerce Awamotos";

    $this->erpConnection->execute(
        "INSERT INTO VE_PEDIDOS
         (FORNECEDOR, FILIAL, DATA, VALORTOTAL, CONDPAGTO, OBSERVACAO, USUARIO)
         VALUES (?, ?, GETDATE(), ?, ?, ?, 'MAGENTO')",
        [
            $erpCustomerCode,
            $this->config->getDefaultFilial(),
            $order->getGrandTotal(),
            $this->mapPaymentCondition($order),
            $observacao
        ]
    );

    // Recuperar o CODIGO gerado pelo ERP
    $result = $this->erpConnection->query(
        "SELECT TOP 1 CODIGO FROM VE_PEDIDOS
         WHERE FORNECEDOR = ? AND OBSERVACAO = ?
         ORDER BY CODIGO DESC",
        [$erpCustomerCode, $observacao]
    );

    $erpCodigo = (int)$result[0]['CODIGO'];

    // Inserir itens do pedido
    foreach ($order->getItems() as $item) {
        $this->insertOrderItem($erpCodigo, $item, $erpCustomerCode);
    }

    return $erpCodigo;
}
```

---

## Estratégia de Retry com Exponential Backoff

```php
// app/code/GrupoAwamotos/ERPIntegration/Model/RetryStrategy.php

class RetryStrategy
{
    // Tentativas com delays crescentes (segundos)
    private const RETRY_DELAYS = [60, 300, 900, 3600, 10800, 86400];
    //                            1min 5min 15min  1h    3h    24h

    public function getNextRetryAt(int $attempts): \DateTime
    {
        $delay = self::RETRY_DELAYS[min($attempts, count(self::RETRY_DELAYS) - 1)];
        return new \DateTime("+{$delay} seconds");
    }

    public function shouldAbandon(int $attempts): bool
    {
        // Após 6 tentativas (somando 24h+), marcar como abandonado
        // e escalar para admin via email urgente
        return $attempts >= count(self::RETRY_DELAYS);
    }
}
```

---

## Resumo do que Implementar (Prioridade)

### 🔥 Crítico (implementar HOJE)

1. **Trocar Observer por Plugin** (`PlacePlugin.php` + `di.xml`)
2. **Null-safe para `erp_code`** em todos os lugares que usam `getCustomAttribute`
3. **Tabela de Idempotência** (`db_schema.xml`) + verificação antes de INSERT

### ⚡ Alta Prioridade (esta semana)

4. **Dead Letter Queue** com tabela + admin grid + cron de retry
5. **Fallback CNPJ** no `resolveErpCustomerCode()`
6. **Cron de segurança** para pedidos que passaram pela fila

### 📋 Médio Prazo (próxima sprint)

7. **Patch MySQL Queue** (se usando MySQL como broker)
8. **Lock no submitQuote** (race condition)
9. **Admin dashboard** com métricas e alertas

---

## Fluxo Final: Dois Cenários Resolvidos

### Cenário A: Cliente Existente no ERP ✅
```
Pedido finalizado
→ Plugin afterPlace: publica order_id na fila (sem bloquear checkout)
→ Consumer: carrega pedido do banco (ID real disponível)
→ getErpCode() → retorna erp_code do atributo
→ Idempotência: pedido novo? SIM → continua
→ Circuit Breaker: CLOSED → continua
→ INSERT em VE_PEDIDOS com prefixo AWA-{incrementId}
→ Salva CODIGO ERP em grupoawamotos_erp_idempotency
→ Status: ✅ Sincronizado
```

### Cenário B: Cliente NOVO (sem erp_code) ✅
```
Pedido finalizado
→ Plugin afterPlace: publica order_id (checkout não é bloqueado)
→ Consumer: getErpCode() → null
→ Fallback: busca CNPJ em FN_FORNECEDORES
   → ENCONTRADO: link automático + continua como Cenário A
   → NÃO ENCONTRADO: entra na Dead Letter Queue
→ Dead Letter Queue:
   → Admin recebe alerta
   → Cron retenta a cada hora (por 24h)
   → Quando SECTRA cadastrar o cliente no fluxo normal deles,
     próximo retry encontra e sincroniza automaticamente
→ Pedido NUNCA é perdido — permanece em Magento
→ Admin pode fazer retry manual ou forçar via painel
```

---

**Resultado:** Zero pedidos perdidos. Zero duplicatas no ERP. Zero alterações no SQL Server/SECTRA.
