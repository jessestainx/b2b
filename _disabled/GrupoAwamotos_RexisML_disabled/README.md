# 🤖 GrupoAwamotos RexisML - Sistema de Recomendações Inteligentes

Módulo de integração entre Power BI, ERP e Magento para recomendações baseadas em Machine Learning e Market Basket Analysis.

---

## 📋 **ÍNDICE**

1. [Visão Geral](#visão-geral)
2. [Funcionalidades](#funcionalidades)
3. [Instalação](#instalação)
4. [Configuração](#configuração)
5. [Uso](#uso)
6. [Estrutura de Dados](#estrutura-de-dados)
7. [API](#api)
8. [Dashboard](#dashboard)

---

## 🎯 **VISÃO GERAL**

Este módulo replica a inteligência do Power BI (dashboard awa_v3) dentro do Magento, permitindo:

- **Recomendações de produtos** baseadas em ML
- **Market Basket Analysis** (regras de associação)
- **Classificação RFM de clientes** (Recency, Frequency, Monetary)
- **Identificação de oportunidades:**
  - 🚨 **Churn** - Produtos que o cliente parava de comprar
  - 🔄 **Cross-sell** - Produtos complementares
  - ⚠️ **Irregular** - Padrão inconsistente de compra

### **Fluxo de Dados:**

```
┌──────────────┐
│   ERP SQL    │
│ (SECTRASERVER)│
└──────┬───────┘
       │
       ├────────────────┐
       │                │
       ↓                ↓
┌──────────────┐   ┌──────────────┐
│  Power BI    │   │ Script Python│
│  (awa_v3)    │   │(rexis_ml_sync)│
└──────────────┘   └──────┬───────┘
                          │
                          ↓
                  ┌──────────────────┐
                  │ Magento MySQL    │
                  │ (Tabelas REXIS)  │
                  └──────┬───────────┘
                         │
                         ↓
                  ┌──────────────────┐
                  │ Frontend/Admin   │
                  │ (Recomendações)  │
                  └──────────────────┘
```

---

## ✨ **FUNCIONALIDADES**

### **1. Recomendações Inteligentes**
- Score de predição (0-1) para cada produto por cliente
- Classificações automáticas (Churn, Cross-sell, Irregular)
- Previsão de gasto esperado

### **2. Market Basket Analysis**
- Regras de associação: "Quem compra A tende a comprar B"
- Métricas: Support, Confidence, Lift
- Sugestões de Cross-sell automáticas

### **3. Classificação RFM**
- Segmentação de clientes:
  - Cliente Recorrente
  - Cliente Novo
  - Cliente Inativo
  - Cliente Fiel
  - Cliente Regular

### **4. Dashboard Administrativo**
- Visualizações interativas (Chart.js/ApexCharts)
- Métricas de conversão
- Lista de oportunidades por cliente

---

## 🔧 **INSTALAÇÃO**

### **1. Instalar o Módulo**

```bash
# Módulo já está em app/code/GrupoAwamotos/RexisML
cd /home/user/htdocs/srv1113343.hstgr.cloud

# Habilitar módulo
php bin/magento module:enable GrupoAwamotos_RexisML

# Executar setup
php bin/magento setup:upgrade

# Criar tabelas no banco
# (InstallSchema será executado automaticamente)

# Verificar
php bin/magento module:status | grep RexisML
```

### **2. Instalar Dependências Python**

O script de sincronização requer Python 3 e algumas bibliotecas:

```bash
# Verificar Python
python3 --version

# Instalar pip (se necessário)
sudo apt-get install python3-pip

# Instalar dependências
pip3 install pymssql pymysql pandas numpy mlxtend

# OU usar requirements.txt
pip3 install -r scripts/rexis_requirements.txt
```

### **3. Criar arquivo de requirements (opcional)**

```bash
cat > scripts/rexis_requirements.txt << EOF
pymssql==2.2.8
pymysql==1.1.0
pandas==2.1.0
numpy==1.24.0
mlxtend==0.22.0
EOF
```

---

## ⚙️ **CONFIGURAÇÃO**

### **1. Configurar Credenciais do ERP**

Editar arquivo: `scripts/rexis_ml_sync.py`

```python
# Linha 18-24
ERP_CONFIG = {
    'server': 'SECTRASERVER',
    'database': 'INDUSTRIAL',
    'user': 'SEU_USUARIO_ERP',      # ← AJUSTAR
    'password': 'SUA_SENHA_ERP'      # ← AJUSTAR
}
```

### **2. Ajustar Parâmetros do Modelo**

```python
# Linha 35-42
PARAMS = {
    'meses_para_churn': 3,          # Churn após 3 meses sem compra
    'min_compras_recorrente': 5,    # Mínimo para ser recorrente
    'periodo_analise_meses': 12,    # Analisar últimos 12 meses
    'min_support': 0.01,            # Suporte mínimo MBA (1%)
    'min_confidence': 0.3,          # Confiança mínima (30%)
    'min_lift': 1.2                 # Lift mínimo
}
```

### **3. Permissões do Script**

```bash
chmod +x scripts/rexis_ml_sync.py
```

---

## 🚀 **USO**

### **Comando 1: Sincronização Manual**

```bash
# Sincronização incremental (atualiza dados novos)
php bin/magento rexis:sync

# Sincronização completa (limpa e reprocessa tudo)
php bin/magento rexis:sync --full
```

### **Comando 2: Via Python Direto**

```bash
# Executar script diretamente
python3 scripts/rexis_ml_sync.py
```

### **Comando 3: Via Cron (Automático)**

Criar cron job:

```bash
# Editar crontab
crontab -e

# Adicionar (executa todo dia às 6h da manhã)
0 6 * * * cd /home/user/htdocs/srv1113343.hstgr.cloud && python3 scripts/rexis_ml_sync.py >> var/log/rexis_sync.log 2>&1
```

---

## 🗄️ **ESTRUTURA DE DADOS**

### **Tabelas Criadas:**

#### **1. `rexis_dataset_recomendacao`**
Principal tabela de recomendações

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | int | ID auto-incremento |
| chave_global | varchar(255) | Cliente_Produto_Mes (unique) |
| mes_rexis_code | varchar(10) | Ex: 11-2025 |
| identificador_cliente | varchar(50) | CNPJ ou código ERP |
| customer_id | int | FK customer_entity |
| identificador_produto | varchar(50) | SKU ou código ERP |
| product_id | int | FK catalog_product_entity |
| classificacao_cliente | varchar(50) | Recorrente, Novo, Inativo |
| **classificacao_produto** | varchar(50) | **Churn, Cross-sell, Irregular** |
| ja_comprou | boolean | Cliente já comprou? |
| **pred** | decimal(10,4) | **Score ML (0-1)** |
| probabilidade_compra | decimal(10,4) | % probabilidade |
| previsao_gasto_round_up | decimal(12,2) | Valor esperado |
| valor_convertida | decimal(12,2) | Valor real convertido |
| quantidade_convertida | int | Qtd real convertida |
| recencia | int | Dias desde última compra |

#### **2. `rexis_network_rules`**
Regras de Market Basket Analysis

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| antecedent | varchar(255) | Produto A (SKU) |
| consequent | varchar(255) | Produto B (SKU) |
| support | decimal(10,6) | Frequência |
| confidence | decimal(10,6) | Confiança (%) |
| **lift** | decimal(10,4) | **Força da associação** |
| conviction | decimal(10,4) | Conviction |
| leverage | decimal(10,6) | Leverage |

**Exemplo de regra:**
```
Se cliente compra "PEDALEIRA TITAN 150" (antecedent)
Então é provável que compre "MANETE FREIO DISCO" (consequent)
Com lift = 2.5 (250% mais provável que o normal)
```

#### **3. `rexis_customer_classification`**
Classificação RFM dos clientes

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| customer_id | int | FK customer_entity |
| identificador_cliente | varchar(50) | CNPJ |
| classificacao_cliente | varchar(50) | Segmento RFM |
| rfm_score | varchar(10) | Ex: 555 (melhor) |
| recency | int | Dias desde última compra |
| frequency | int | Número de pedidos |
| monetary | decimal(12,2) | Valor total gasto |
| mean_ticket_per_order | decimal(12,2) | Ticket médio |

#### **4. `rexis_metricas_conversao`**
Métricas mensais de performance

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| mes_rexis_code | varchar(10) | Mês referência |
| n_clientes_rec_mes_atual | int | Clientes recomendados |
| n_cliente_comprou_mes_atual | int | Clientes convertidos |
| **perc_conversao_cliente** | decimal(10,4) | **% Conversão** |
| valor_esperado_atual | decimal(12,2) | Valor projetado |
| valor_convertido_atual | decimal(12,2) | Valor real |

---

## 📊 **CONSULTAS SQL ÚTEIS**

### **1. Top 10 Oportunidades de Churn**

```sql
SELECT
    r.identificador_cliente,
    c.razao AS cliente_nome,
    r.identificador_produto,
    r.pred AS score,
    r.probabilidade_compra,
    r.previsao_gasto_round_up,
    r.recencia
FROM rexis_dataset_recomendacao r
LEFT JOIN customer_entity c ON c.entity_id = r.customer_id
WHERE r.classificacao_produto = 'Oportunidade Churn'
  AND r.pred >= 0.7
ORDER BY r.pred DESC
LIMIT 10;
```

### **2. Produtos mais recomendados para Cross-sell**

```sql
SELECT
    identificador_produto,
    COUNT(*) AS n_recomendacoes,
    AVG(pred) AS score_medio,
    SUM(previsao_gasto_round_up) AS valor_potencial
FROM rexis_dataset_recomendacao
WHERE classificacao_produto = 'Oportunidade Cross-Sell'
  AND pred >= 0.5
GROUP BY identificador_produto
ORDER BY score_medio DESC
LIMIT 20;
```

### **3. Regras MBA com maior lift**

```sql
SELECT
    antecedent AS produto_origem,
    consequent AS produto_sugerido,
    lift,
    confidence,
    support
FROM rexis_network_rules
WHERE lift >= 2.0
  AND confidence >= 0.4
  AND is_active = 1
ORDER BY lift DESC
LIMIT 20;
```

### **4. Classificação de clientes (distribuição)**

```sql
SELECT
    classificacao_cliente,
    COUNT(*) AS n_clientes,
    AVG(monetary) AS ticket_medio,
    AVG(frequency) AS freq_media,
    AVG(recency) AS recencia_media
FROM rexis_customer_classification
WHERE mes_rexis_code = '12-2025'  -- Mês atual
GROUP BY classificacao_cliente
ORDER BY n_clientes DESC;
```

### **5. Taxa de conversão mensal (últimos 6 meses)**

```sql
SELECT
    mes_rexis_code,
    n_clientes_rec_mes_atual AS recomendados,
    n_cliente_comprou_mes_atual AS convertidos,
    perc_conversao_cliente AS taxa_conversao,
    valor_esperado_atual,
    valor_convertido_atual,
    ROUND((valor_convertido_atual / valor_esperado_atual * 100), 2) AS assertividade
FROM rexis_metricas_conversao
ORDER BY mes_rexis_code DESC
LIMIT 6;
```

---

## 🎨 **DASHBOARD (Próxima Fase)**

Será criado um dashboard administrativo com:

### **Gráficos:**
- Evolução de conversão mensal (linha)
- Distribuição de classificações de clientes (pizza)
- Top 10 oportunidades de Churn (barra)
- Rede de produtos (network graph)
- Heatmap de associações

### **Filtros:**
- Por período (mês)
- Por classificação
- Por cliente
- Por vendedor

### **Ações:**
- Enviar email de reativação (Churn)
- Criar cotação automática (Cross-sell)
- Enviar WhatsApp
- Exportar CSV

---

## 🔌 **API REST (Próxima Fase)**

```php
// GET recomendações para um cliente
GET /rest/V1/rexis/recommendations/:customerId

// GET regras de cross-sell para um produto
GET /rest/V1/rexis/crosssell/:sku

// GET classificação RFM de um cliente
GET /rest/V1/rexis/rfm/:customerId

// POST marcar recomendação como convertida
POST /rest/V1/rexis/convert
{
    "chave_global": "12345_SKU123_12-2025",
    "valor_convertida": 150.00,
    "quantidade_convertida": 2
}
```

---

## 📝 **LOGS**

Logs de sincronização:

```bash
# Log do Python
tail -f var/log/rexis_sync.log

# Log do Magento CLI
tail -f var/log/system.log | grep -i rexis
```

---

## 🐛 **TROUBLESHOOTING**

### **Erro: "pymssql not found"**

```bash
# Ubuntu/Debian
sudo apt-get install freetds-dev
pip3 install pymssql

# CentOS/RHEL
sudo yum install freetds-devel
pip3 install pymssql
```

### **Erro: "Access denied for user 'magento'"**

Verificar credenciais no script Python (linha 27-32)

### **Erro: "Table doesn't exist"**

```bash
# Recriar tabelas
php bin/magento setup:upgrade --keep-generated
```

### **Recomendações não aparecem**

```bash
# 1. Verificar se sincronização rodou
mysql -u magento -p'Aw4m0t0s2025Mage' -D magento -e "SELECT COUNT(*) FROM rexis_dataset_recomendacao"

# 2. Ver últimas recomendações
mysql -u magento -p'Aw4m0t0s2025Mage' -D magento -e "SELECT * FROM rexis_dataset_recomendacao ORDER BY id DESC LIMIT 10"

# 3. Re-sincronizar
php bin/magento rexis:sync --full
```

---

## 📞 **SUPORTE**

Para dúvidas ou problemas:
1. Verificar logs: `var/log/rexis_sync.log`
2. Consultar este README
3. Verificar STATUS_SISTEMA_COMPLETO.md

---

## 🎯 **ROADMAP**

### **Fase 1 - Atual:**
- ✅ Estrutura de dados (tabelas SQL)
- ✅ Models Magento
- ✅ Script Python de sincronização
- ✅ Comando CLI

### **Fase 2 - Próxima:**
- ⏳ Dashboard administrativo
- ⏳ API REST completa
- ⏳ Integração com SmartSuggestions
- ⏳ Automações (email, WhatsApp)

### **Fase 3 - Futura:**
- ⏳ Modelo ML próprio (não depender do Power BI)
- ⏳ Real-time recommendations
- ⏳ A/B Testing de recomendações
- ⏳ Analytics de performance

---

**Desenvolvido por:** Grupo Awamotos + Claude Code
**Versão:** 1.0.0
**Data:** 17/02/2026
**Status:** ✅ Pronto para uso
