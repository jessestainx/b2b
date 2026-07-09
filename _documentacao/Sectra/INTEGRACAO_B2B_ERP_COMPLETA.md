# Módulo ERP Integration - Documentação Completa

## Visão Geral

Módulo enterprise de integração com ERP SQL Server para Magento 2.4.8, com sincronização bidirecional, circuit breaker, message queue e recursos avançados de análise.

## Versão: 2.0.0
**Última atualização:** Fevereiro 2026

---

## 🔌 Funcionalidades Principais

### 1. Conexão SQL Server

**Suporte a drivers:**
- `sqlsrv` (Microsoft SQL Server Driver for PHP)
- `pdo_sqlsrv` (PDO Driver)
- `pdo_dblib` (FreeTDS)
- Detecção automática do melhor driver disponível

**Credenciais seguras (ordem de prioridade):**
1. Variáveis de ambiente (RECOMENDADO)
2. `app/etc/env.php` (deployment config)
3. Admin Config (criptografado)

**Circuit Breaker:**
- Proteção contra falhas do ERP
- Estados: CLOSED, OPEN, HALF_OPEN
- Recuperação automática
- Timeout configurável

---

## 📦 Sincronizações Implementadas

### 1. Sincronização de Produtos
**Arquivo:** `Model/ProductSync.php`

**Recursos:**
- Importação completa de produtos do ERP
- Filtro por produtos comercializáveis (`CKCOMERCIALIZA = 'S'`)
- Criação/atualização automática
- Mapeamento de atributos:
  - SKU → `CODPRODUTO`
  - Nome → `DESCRICAO`
  - Preço → Tabela de preços configurável
  - Estoque → Multi-filial
  - Status → `ATIVO`
  - Peso → `PESO`

**Frequência padrão:** 360 minutos (6 horas)

**Comando CLI:**
```bash
php bin/magento erp:sync-products
```

---

### 2. Sincronização de Categorias
**Arquivo:** `Model/CategorySync.php`

**Recursos:**
- Criação de árvore de categorias do ERP
- Categoria raiz configurável (padrão: "Catálogo ERP")
- Mapeamento automático
- Associação de produtos

**Tabelas ERP:**
- `FN_GRUPO` (Grupos de produtos)
- `FN_SUBGRUPO` (Subgrupos)

**Comando CLI:**
```bash
php bin/magento erp:sync-categories
```

---

### 3. Sincronização de Estoque
**Arquivo:** `Model/StockSync.php`

**Recursos:**
- Estoque em tempo real (via Plugin)
- Multi-filial com agregação
- Cache inteligente (TTL configurável)
- Negative cache (produtos não encontrados)

**Modos de agregação:**
- `sum` - Soma de todas as filiais
- `min` - Menor estoque entre filiais
- `max` - Maior estoque
- `avg` - Média

**Tabela ERP:**
- `FN_ESTOQUE` (Estoque por filial)

**Plugin:** `StockPlugin`
```php
// Intercepta consultas de estoque e busca do ERP
afterGetQty(Product $product)
```

**Comando CLI:**
```bash
php bin/magento erp:sync-stock [--sku=SKU]
```

---

### 4. Sincronização de Preços
**Arquivo:** `Model/PriceSync.php`

**Recursos:**
- Múltiplas tabelas de preço (FATORPRECO)
- Preço padrão configurável (default: 24 - NACIONAL)
- Preços específicos por cliente B2B
- Tier prices (preços por quantidade)

**Tabelas ERP:**
- `FN_TABELAPRECO` (Tabelas de preço)
- Relação com cliente via `FATORPRECO`

**Frequência padrão:** 240 minutos (4 horas)

---

### 5. Sincronização de Clientes B2B
**Arquivo:** `Model/CustomerSync.php`

**Recursos:**
- Busca cliente por CNPJ/CPF
- Auto-link Magento ↔ ERP
- Importação de dados cadastrais
- Sincronização de endereços
- Limite de crédito
- Transportadora preferencial

**Tabela ERP:**
- `FN_FORNECEDORES` (where `CKCLIENTE = 'S'`)
- `FN_CONTATO` (Contatos do cliente)

**Atributos criados:**
- `erp_code` - Código do cliente no ERP (CODIGO)

**Métodos principais:**
```php
// Buscar cliente por CNPJ
getErpCustomerByTaxvat(string $cnpj): ?array

// Obter código ERP de um cliente Magento
getErpCodeByCustomerId(int $customerId): ?int

// Vincular cliente Magento → ERP
linkMagentoToErp(int $magentoId, int $erpCode): bool

// Sincronizar endereços
syncCustomerAddresses(int $customerId, int $erpCode): bool

// Obter limite de crédito
getCustomerCreditFromErp(string $erpCode): ?array
```

**Comando CLI:**
```bash
php bin/magento erp:sync-customers
```

---

### 6. Sincronização de Pedidos
**Arquivo:** `Model/OrderSync.php`

**Recursos:**
- Envio automático de pedidos para ERP
- Message Queue (async) para resiliência
- Circuit Breaker para proteção
- Retry automático em caso de falha
- Log completo de sincronização
- Status de sincronização

**Tabela ERP:**
- `FN_PEDIDOS` (Cabeçalho do pedido)
- `FN_PEDIDOSITENS` (Itens do pedido)

**Observer:** `OrderPlaceAfter`
```php
// Dispara quando pedido é finalizado
execute(Observer $observer)
```

**Message Queue:**
- Queue: `erp.order.sync`
- Consumer: `erpOrderSyncConsumer`
- Topic: `erp.order.sync`

**Comando CLI:**
```bash
# Sincronizar pedido específico
php bin/magento erp:sync-order --order-id=123

# Processar fila manualmente
php bin/magento queue:consumers:start erpOrderSyncConsumer
```

**Cron:** `ProcessOrderQueue`
```xml
<schedule>*/5 * * * *</schedule>
```

---

### 7. Sincronização de Imagens
**Arquivo:** `Model/ImageSync.php`

**Recursos:**
- Múltiplas fontes de imagem
- Download e importação automática
- Limpeza de imagens órfãs
- Preservação de imagens manuais

**Modos de origem:**
- `table` - Tabela do banco (BLOB ou URL)
- `folder` - Pasta local/rede
- `url` - URL remota com placeholder {sku}
- `auto` - Detecta automaticamente

**Configurações:**
- Base path: Caminho local
- Base URL: URL remota (ex: `https://cdn.com/images/{sku}.jpg`)
- Replace existing: Substituir imagens existentes
- Clean orphans: Remover imagens não usadas

**Comando CLI:**
```bash
php bin/magento erp:sync-images [--sku=SKU]
```

---

## 🔒 Circuit Breaker

**Arquivo:** `Model/CircuitBreaker.php`

**Estados:**
- **CLOSED** - Funcionamento normal
- **OPEN** - ERP indisponível, requisições bloqueadas
- **HALF_OPEN** - Testando recuperação

**Configuração:**
- Threshold de falhas: 5 (padrão)
- Timeout: 60 segundos
- Recovery timeout: 30 segundos

**Comandos CLI:**
```bash
# Ver status
php bin/magento erp:circuit-breaker status

# Forçar reset
php bin/magento erp:circuit-breaker reset

# Forçar abertura (teste)
php bin/magento erp:circuit-breaker open
```

---

## 📊 Recursos Avançados

### 1. RFM Analysis (Recency, Frequency, Monetary)
**Arquivo:** `Model/Rfm/Calculator.php`

**Segmentos:**
- **Champions** - Melhores clientes
- **Loyal** - Clientes fiéis
- **At Risk** - Em risco de perda
- **Can't Lose** - Não pode perder
- **Lost** - Clientes perdidos
- **Promising** - Promissores
- **Need Attention** - Precisam atenção

**API:**
```php
GET /rest/V1/erp/rfm/customer/:customerId
GET /rest/V1/erp/rfm/segment/:segment
```

**Comando CLI:**
```bash
php bin/magento erp:rfm-analysis
```

**Cron:** `UpdateRfmAnalysis`
```xml
<schedule>0 2 * * *</schedule> <!-- 2h da manhã -->
```

---

### 2. Sales Forecast (Projeção de Vendas)
**Arquivo:** `Model/Forecast/SalesProjection.php`

**Métodos:**
- `moving_average` - Média móvel
- `exponential_smoothing` - Suavização exponencial
- `hybrid` - Combinação de métodos (PADRÃO)

**Recursos:**
- Projeção de vendas mensal
- Confiança configurável (padrão: 85%)
- Alertas de meta
- Gráficos e relatórios

**API:**
```php
GET /rest/V1/erp/forecast/monthly
GET /rest/V1/erp/forecast/daily
```

**Cron:** `UpdateForecasts`
```xml
<schedule>0 3 * * *</schedule> <!-- 3h da manhã -->
```

---

### 3. Suggested Cart (Carrinho Sugerido)
**Arquivo:** `Model/Cart/SuggestedCart.php`

**Recursos:**
- Baseado em histórico de compras
- Produtos complementares
- Otimização para frete grátis
- Cache por cliente (TTL: 30 min)

**Algoritmo:**
1. Busca últimas N compras do cliente
2. Identifica produtos mais comprados
3. Remove produtos já no carrinho
4. Adiciona produtos complementares
5. Otimiza para atingir valor de frete grátis

**Configurações:**
- Mín produtos: 3
- Máx produtos: 15
- Threshold frete grátis: R$ 1500

**API:**
```php
GET /rest/V1/erp/cart/suggested/:customerId
POST /rest/V1/erp/cart/add-suggested
```

**Controllers:**
```
/erp/customer/suggestedcart - Visualizar sugestões
/erp/cart/add-suggested - Adicionar ao carrinho
```

---

### 4. WhatsApp Integration (Z-API)
**Arquivo:** `Model/WhatsApp/ZApiClient.php`

**Recursos:**
- Notificações de pedidos
- Status de entrega
- Pagamento confirmado
- Reengajamento de clientes
- Cupons automáticos

**Eventos notificados:**
- ✅ Novo pedido
- ✅ Pagamento confirmado
- ✅ Pedido em separação
- ✅ Pedido enviado
- ✅ Pedido entregue

**Configuração:**
- Z-API Instance ID
- Z-API Token
- Z-API Client Token (opcional)
- Telefone admin para testes

**Variáveis de ambiente (RECOMENDADO):**
```bash
ZAPI_INSTANCE_ID=sua_instancia
ZAPI_TOKEN=seu_token
ZAPI_CLIENT_TOKEN=seu_client_token
```

**Templates sugeridos:**
- `order_status_update` - Status do pedido
- `payment_confirmed` - Pagamento confirmado
- `shipping_update` - Atualização de envio
- `reengagement_coupon` - Cupom de reengajamento

**Comando CLI:**
```bash
# Testar conexão WhatsApp
php bin/magento whatsapp:status

# Enviar mensagem de teste
php bin/magento whatsapp:test --phone=5511999999999
```

**Observers:**
- `OrderPlaceAfter` - Novo pedido
- `OrderStatusChange` - Mudança de status
- `ShipmentSaveAfter` - Envio criado

---

### 5. Cupons Automáticos
**Arquivo:** `Model/Coupon/Generator.php`

**Recursos:**
- Geração automática por segmento RFM
- Descontos personalizados
- Envio por email/WhatsApp
- Validade configurável

**Descontos por segmento:**
- At Risk: 15%
- Can't Lose: 20%
- Lost: 25%
- Padrão: 10%

**Cron:** `SendReengagementCoupons`
```xml
<schedule>0 10 * * 1</schedule> <!-- Segundas 10h -->
```

---

## 🛠️ Configuração

### 1. Conexão SQL Server

**Opção A: Variáveis de Ambiente (RECOMENDADO)**
```bash
# .env ou configuração do servidor
export ERP_SQL_HOST="seu-servidor.com"
export ERP_SQL_PORT="1433"
export ERP_SQL_DATABASE="nome_banco"
export ERP_SQL_USERNAME="usuario"
export ERP_SQL_PASSWORD="senha"
```

**Opção B: app/etc/env.php**
```php
return [
    // ... outras configs
    'erp' => [
        'host' => 'seu-servidor.com',
        'port' => 1433,
        'database' => 'nome_banco',
        'username' => 'usuario',
        'password' => 'senha'
    ]
];
```

**Opção C: Admin Panel**
```
Stores > Configuration > Grupo Awamotos > ERP Integration > Connection
```

### 2. Habilitar no Admin

**Path:** `Stores > Configuration > Grupo Awamotos > ERP Integration`

**Seções disponíveis:**

#### Connection Settings
- Enable Integration
- Use Environment Variables
- Host, Port, Database
- Username, Password (encrypted)
- Driver Selection
- Connection Timeout
- Trust Server Certificate

#### Product Sync
- Enable Product Sync
- Sync Frequency (minutes)
- Filter Comercializa Only

#### Category Sync
- Enable Category Sync
- Root Category Name
- Include in Menu

#### Stock Sync
- Enable Stock Sync
- Realtime Stock
- Branch (Filial)
- Multi-Branch Mode
- Branch List (comma separated)
- Aggregation Mode
- Cache TTL

#### Price Sync
- Enable Price Sync
- Sync Frequency
- Default Price List (FATORPRECO)

#### Customer Sync
- Enable Customer Sync
- Sync Frequency

#### Order Sync
- Enable Order Sync
- Send on Place
- Use Queue (async)

#### Image Sync
- Enable Image Sync
- Source Type
- Base Path / Base URL
- Replace Existing
- Clean Orphans

#### Suggestions
- Enable Suggestions
- Max Suggestions
- Cart Min/Max Products
- Free Shipping Threshold

#### RFM Analysis
- Enable RFM
- Analysis Period (months)
- Alert At Risk Customers

#### Forecast
- Enable Forecast
- Method (moving_average, exponential_smoothing, hybrid)
- Confidence Level
- Monthly Target
- Alert Enabled

#### WhatsApp (Z-API)
- Enable WhatsApp
- Instance ID, Token, Client Token
- Admin Phone
- Enable notifications (order, payment, shipping)
- Enable Reengagement

#### Coupons
- Enable Auto Coupons
- Valid Days
- Default Discount
- Min Order Amount
- Segment Discounts

---

## 📋 Comandos CLI

### Diagnóstico e Status
```bash
# Status geral do ERP
php bin/magento erp:status

# Diagnóstico completo
php bin/magento erp:diagnose

# Testar conexão
php bin/magento erp:test-connection

# Status Circuit Breaker
php bin/magento erp:circuit-breaker status
```

### Sincronizações
```bash
# Sincronizar produtos
php bin/magento erp:sync-products

# Sincronizar categorias
php bin/magento erp:sync-categories

# Sincronizar estoque (todos ou específico)
php bin/magento erp:sync-stock [--sku=SKU]

# Sincronizar clientes
php bin/magento erp:sync-customers

# Sincronizar pedido específico
php bin/magento erp:sync-order --order-id=123

# Sincronizar imagens
php bin/magento erp:sync-images
```

### Análises
```bash
# Executar análise RFM
php bin/magento erp:rfm-analysis

# Atualizar forecasts
php bin/magento erp:update-forecasts
```

### WhatsApp
```bash
# Status da conexão WhatsApp
php bin/magento whatsapp:status

# Enviar mensagem de teste
php bin/magento whatsapp:test --phone=5511999999999
```

### Manutenção
```bash
# Limpar logs antigos
php bin/magento erp:clean-logs [--days=30]

# Ver logs de sincronização
php bin/magento erp:sync-logs [--limit=50]

# Processar fila de pedidos manualmente
php bin/magento queue:consumers:start erpOrderSyncConsumer
```

---

## 🔄 Cron Jobs

### Sincronizações Periódicas
```xml
<!-- Produtos: a cada 6 horas -->
<job name="erp_sync_products" instance="GrupoAwamotos\ERPIntegration\Cron\SyncProducts">
    <schedule>0 */6 * * *</schedule>
</job>

<!-- Categorias: diariamente às 2h -->
<job name="erp_sync_categories" instance="GrupoAwamotos\ERPIntegration\Cron\SyncCategories">
    <schedule>0 2 * * *</schedule>
</job>

<!-- Preços: a cada 4 horas -->
<job name="erp_sync_prices" instance="GrupoAwamotos\ERPIntegration\Cron\SyncPrices">
    <schedule>0 */4 * * *</schedule>
</job>

<!-- Clientes: diariamente às 3h -->
<job name="erp_sync_customers" instance="GrupoAwamotos\ERPIntegration\Cron\SyncCustomers">
    <schedule>0 3 * * *</schedule>
</job>

<!-- Status de pedidos: a cada 30 min -->
<job name="erp_sync_order_statuses" instance="GrupoAwamotos\ERPIntegration\Cron\SyncOrderStatuses">
    <schedule>*/30 * * * *</schedule>
</job>

<!-- Imagens: a cada 12 horas -->
<job name="erp_sync_images" instance="GrupoAwamotos\ERPIntegration\Cron\SyncImages">
    <schedule>0 */12 * * *</schedule>
</job>
```

### Análises e Relatórios
```xml
<!-- RFM Analysis: diariamente às 2h -->
<job name="erp_update_rfm" instance="GrupoAwamotos\ERPIntegration\Cron\UpdateRfmAnalysis">
    <schedule>0 2 * * *</schedule>
</job>

<!-- Forecasts: diariamente às 3h -->
<job name="erp_update_forecasts" instance="GrupoAwamotos\ERPIntegration\Cron\UpdateForecasts">
    <schedule>0 3 * * *</schedule>
</job>

<!-- Alertas: diariamente às 8h -->
<job name="erp_send_alerts" instance="GrupoAwamotos\ERPIntegration\Cron\SendAlerts">
    <schedule>0 8 * * *</schedule>
</job>

<!-- Relatório semanal: domingos às 18h -->
<job name="erp_weekly_report" instance="GrupoAwamotos\ERPIntegration\Cron\SendWeeklyReport">
    <schedule>0 18 * * 0</schedule>
</job>
```

### Reengajamento
```xml
<!-- Cupons de reengajamento: segundas às 10h -->
<job name="erp_send_coupons" instance="GrupoAwamotos\ERPIntegration\Cron\SendReengagementCoupons">
    <schedule>0 10 * * 1</schedule>
</job>

<!-- WhatsApp reengajamento: quartas às 14h -->
<job name="erp_whatsapp_reengagement" instance="GrupoAwamotos\ERPIntegration\Cron\SendWhatsAppReengagement">
    <schedule>0 14 * * 3</schedule>
</job>
```

### Manutenção
```xml
<!-- Limpar logs antigos: diariamente às 4h -->
<job name="erp_clean_logs" instance="GrupoAwamotos\ERPIntegration\Cron\CleanSyncLogs">
    <schedule>0 4 * * *</schedule>
</job>

<!-- Processar fila de pedidos: a cada 5 min -->
<job name="erp_process_order_queue" instance="GrupoAwamotos\ERPIntegration\Cron\ProcessOrderQueue">
    <schedule>*/5 * * * *</schedule>
</job>
```

---

## 📊 Tabelas do Banco de Dados

### Magento

```sql
-- Logs de sincronização
grupoawamotos_erp_sync_log
  - log_id (PK)
  - entity_type (product, customer, order, etc)
  - entity_id
  - erp_code
  - operation (create, update, delete)
  - status (success, error, pending)
  - message
  - created_at

-- Estado do Circuit Breaker
grupoawamotos_erp_circuit_breaker
  - id (PK)
  - state (closed, open, half_open)
  - failure_count
  - last_failure_time
  - updated_at

-- Mapeamento Magento → ERP
grupoawamotos_erp_customer_mapping
  - mapping_id (PK)
  - customer_id (FK)
  - erp_code
  - sync_status
  - last_sync
  - created_at
```

### ERP (SQL Server) - Tabelas Consultadas

```sql
-- Produtos
FN_PRODUTOS
  - CODIGO (PK)
  - CODPRODUTO (SKU)
  - DESCRICAO
  - ATIVO
  - PESO
  - CKCOMERCIALIZA
  - GRUPO, SUBGRUPO

-- Estoque
FN_ESTOQUE
  - PRODUTO (FK)
  - FILIAL
  - SALDOATUAL

-- Clientes
FN_FORNECEDORES (where CKCLIENTE = 'S')
  - CODIGO (PK)
  - RAZAO, FANTASIA
  - CGC (CNPJ), CPF
  - ENDERECO, NUMERO, BAIRRO, CIDADE, UF, CEP
  - CONDPAGTO (Condição pagamento)
  - FATORPRECO (Tabela de preço)
  - TRANSPPREF (Transportadora preferencial)

-- Contatos
FN_CONTATO
  - FORNECEDOR (FK)
  - NOME
  - EMAIL
  - FONE1, FONECEL
  - PRINCIPAL

-- Pedidos
FN_PEDIDOS
  - CODIGO (PK)
  - FORNECEDOR (FK)
  - DATA
  - VALORBRUTO, VALORDESC, VALORTOTAL
  - STATUS

-- Itens do Pedido
FN_PEDIDOSITENS
  - PEDIDO (FK)
  - PRODUTO (FK)
  - QUANTIDADE
  - VALORUNITARIO
  - VALORTOTAL

-- Tabelas de Preço
FN_TABELAPRECO
  - FATORPRECO
  - PRODUTO (FK)
  - PRECO

-- Categorias
FN_GRUPO
  - CODIGO (PK)
  - DESCRICAO

FN_SUBGRUPO
  - CODIGO (PK)
  - GRUPO (FK)
  - DESCRICAO
```

---

## 🔗 Integração com Módulo B2B

### Pontos de Integração

#### 1. Registro de Cliente B2B
**Observer:** `B2B\Observer\ErpApprovalSyncObserver`

```php
// Quando cliente B2B é aprovado
1. Busca CNPJ no ERP (CustomerSync::getErpCustomerByTaxvat)
2. Se encontrado → Link automático
3. Sincroniza limite de crédito
4. Importa endereços
5. Define transportadora preferencial
```

#### 2. Preços B2B
**Service:** `B2B\Model\ErpIntegration`

```php
// Verifica se cliente tem tabela de preço no ERP
- Se cliente tem erp_code → busca FATORPRECO
- Aplica preços específicos da tabela do cliente
- Descontos B2B + Preços ERP = Preço final
```

#### 3. Pedidos B2B
**Flow:**
```
Pedido B2B → OrderSync (ERP) → Envio para FN_PEDIDOS
                              ↓
                    Vincula erp_code do cliente
                              ↓
                    ERP processa pedido
                              ↓
                    Status sincronizado de volta
```

#### 4. Visibilidade de Preços
**Plugin:** `B2B\Plugin\GroupPricePlugin`

```php
// Se cliente não tem erp_code → oculta preço
// Mensagem: "Sua tabela de preços está sendo definida"
```

---

## 🧪 Testes

### Unitários
```bash
# Executar todos os testes
vendor/bin/phpunit app/code/GrupoAwamotos/ERPIntegration/Test/Unit

# Teste específico
vendor/bin/phpunit app/code/GrupoAwamotos/ERPIntegration/Test/Unit/Model/ConnectionTest.php
```

### Integração
```bash
# Testes de integração
vendor/bin/phpunit app/code/GrupoAwamotos/ERPIntegration/Test/Integration

# Teste de conexão
vendor/bin/phpunit app/code/GrupoAwamotos/ERPIntegration/Test/Integration/ConnectionIntegrationTest.php
```

### Testes Manuais

#### Testar Conexão
```bash
php bin/magento erp:test-connection
```

Resultado esperado:
```
✓ Connection successful
✓ Driver: sqlsrv
✓ Server version: Microsoft SQL Server 2019
✓ Database: SEUBANCOERP
✓ Credential source: environment
```

#### Testar Sync de Cliente
```bash
# Buscar cliente por CNPJ
php bin/magento erp:customer:search --cnpj=12345678000190
```

#### Testar Sync de Pedido
```bash
# Sincronizar pedido de teste
php bin/magento erp:sync-order --order-id=100000123
```

---

## 🐛 Troubleshooting

### Problema: Conexão falha

**Sintomas:**
```
Connection refused / timeout
```

**Soluções:**
1. Verificar credenciais:
   ```bash
   php bin/magento erp:diagnose
   ```

2. Testar conectividade:
   ```bash
   telnet SEU_SERVIDOR 1433
   ```

3. Verificar firewall/VPN

4. Checar driver instalado:
   ```bash
   php -m | grep -i sql
   ```

### Problema: Circuit Breaker OPEN

**Sintomas:**
```
Circuit breaker is OPEN. ERP integration suspended.
```

**Soluções:**
1. Ver status:
   ```bash
   php bin/magento erp:circuit-breaker status
   ```

2. Verificar logs:
   ```bash
   tail -f var/log/erp_sync.log
   ```

3. Resetar manualmente (após resolver o problema):
   ```bash
   php bin/magento erp:circuit-breaker reset
   ```

### Problema: Pedidos não sincronizam

**Verificações:**
1. Queue consumer rodando:
   ```bash
   ps aux | grep erpOrderSyncConsumer
   ```

2. Iniciar consumer manualmente:
   ```bash
   php bin/magento queue:consumers:start erpOrderSyncConsumer
   ```

3. Ver mensagens na fila:
   ```bash
   php bin/magento queue:consumers:list
   ```

4. Verificar logs:
   ```bash
   tail -f var/log/erp_sync.log | grep -i order
   ```

### Problema: Estoque não atualiza

**Verificações:**
1. Cache habilitado:
   ```bash
   php bin/magento config:show grupoawamotos_erp/sync_stock/cache_ttl
   ```

2. Limpar cache de estoque:
   ```bash
   php bin/magento cache:clean erp_stock
   ```

3. Testar SKU específico:
   ```bash
   php bin/magento erp:sync-stock --sku=SEU-SKU
   ```

### Problema: WhatsApp não envia

**Verificações:**
1. Status da integração:
   ```bash
   php bin/magento whatsapp:status
   ```

2. Testar envio:
   ```bash
   php bin/magento whatsapp:test --phone=5511999999999
   ```

3. Verificar credenciais Z-API no admin

4. Logs:
   ```bash
   tail -f var/log/whatsapp.log
   ```

---

## 📈 Performance

### Cache Strategy

**Estoque:**
- TTL: 300s (5 min) - configurável
- Negative cache: 60s
- Multi-level: Redis → ERP

**Preços:**
- Cache de tabelas: 3600s (1h)
- Invalidação por produto

**RFM:**
- Cache de análise: 86400s (24h)
- Atualização noturna via cron

**Sugestões:**
- Cache por cliente: 1800s (30 min)
- Invalidação em nova compra

### Otimizações

**Batch Processing:**
- Produtos: 100 por lote
- Clientes: 50 por lote
- Preços: 500 por lote

**Índices Recomendados (ERP):**
```sql
-- Produtos
CREATE INDEX IX_PRODUTOS_CODPRODUTO ON FN_PRODUTOS(CODPRODUTO);
CREATE INDEX IX_PRODUTOS_ATIVO ON FN_PRODUTOS(ATIVO);

-- Estoque
CREATE INDEX IX_ESTOQUE_PRODUTO_FILIAL ON FN_ESTOQUE(PRODUTO, FILIAL);

-- Clientes
CREATE INDEX IX_FORNEC_CGC ON FN_FORNECEDORES(CGC);
CREATE INDEX IX_FORNEC_CKCLIENTE ON FN_FORNECEDORES(CKCLIENTE);
```

---

## 🔐 Segurança

### Credenciais

**NUNCA commitar:**
- Senhas no código
- Tokens em arquivos versionados
- Credenciais no admin sem criptografia

**SEMPRE usar:**
1. Variáveis de ambiente (produção)
2. `app/etc/env.php` (staging)
3. Admin com criptografia (desenvolvimento)

### Logs

**Não logar:**
- Senhas
- Tokens completos
- Dados sensíveis de clientes

**Sanitização automática:**
```php
// Logs já sanitizam dados sensíveis automaticamente
$this->logger->info('Customer synced', [
    'customer_id' => $id,
    'taxvat' => substr($taxvat, 0, 4) . '***' // Mascarado
]);
```

---

## 📝 Changelog

### v2.0.0 (Fevereiro 2026)
- Circuit Breaker implementado
- Message Queue para pedidos
- RFM Analysis completo
- Sales Forecast (3 métodos)
- WhatsApp Integration (Z-API)
- Suggested Cart
- Cupons automáticos
- Multi-branch stock aggregation
- Image sync com múltiplas fontes

### v1.0.0 (Janeiro 2026)
- Conexão SQL Server
- Sync básico (produtos, estoque, preços)
- Sync de clientes B2B
- Sync de pedidos
- Integração com módulo B2B

---

## 📞 Suporte

### Logs
```bash
# ERP Sync
tail -f var/log/erp_sync.log

# System (geral)
tail -f var/log/system.log | grep -i erp

# Exception
tail -f var/log/exception.log | grep -i erp

# WhatsApp
tail -f var/log/whatsapp.log

# Debug
tail -f var/log/debug.log | grep -i erp
```

### Comandos Úteis
```bash
# Status completo
php bin/magento erp:diagnose

# Limpar tudo e recompilar
php bin/magento cache:flush
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f

# Reindexar
php bin/magento indexer:reindex

# Ver configuração atual
php bin/magento config:show grupoawamotos_erp
```

---

## 🚀 Roadmap

### Planejado para v2.1
- [ ] Dashboard admin com KPIs em tempo real
- [ ] Sync de NF-e (Notas Fiscais)
- [ ] Integração com transportadoras (rastreio)
- [ ] ML/AI para previsões mais precisas
- [ ] Multi-tenant (múltiplos ERPs)
- [ ] GraphQL API
- [ ] Webhooks para eventos

### Em Avaliação
- [ ] Sync de XML NF-e
- [ ] Integração Mercado Livre
- [ ] Integração Amazon
- [ ] B2B Marketplace

---

## 📄 Licença

Propriedade de Grupo Awamotos.
Todos os direitos reservados.

---

**Desenvolvido com ❤️ para Magento 2.4.8**
