# REXIS ML - Guia de API REST e Automações

## 📋 Índice

1. [API REST Endpoints](#api-rest-endpoints)
2. [Configuração de Automações](#configuração-de-automações)
3. [Email Alerts](#email-alerts)
4. [WhatsApp Integration](#whatsapp-integration)
5. [Auto Quote Creation](#auto-quote-creation)
6. [Frontend Recommendations](#frontend-recommendations)

---

## 🔌 API REST Endpoints

### Autenticação

Todas as requisições exigem autenticação via **OAuth** ou **Admin Token**.

```bash
# Gerar Admin Token
curl -X POST "https://seu-dominio.com.br/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"senha123"}'
```

### 1. Obter Recomendações por Cliente

**Endpoint:** `GET /rest/V1/rexis/recommendations/:customerId`

**Parâmetros de Query:**
- `classificacao` (opcional): Filtrar por tipo (Churn, Cross-sell, Irregular)
- `minScore` (opcional): Score mínimo (padrão: 0.7)
- `limit` (opcional): Limite de resultados (padrão: 10)

**Exemplo:**

```bash
curl -X GET "https://seu-dominio.com.br/rest/V1/rexis/recommendations/123?minScore=0.8&limit=5" \
  -H "Authorization: Bearer {token}"
```

**Resposta:**

```json
[
  {
    "chave_global": "123_PROD456_2024-02",
    "identificador_cliente": 123,
    "identificador_produto": "PROD456",
    "classificacao_produto": "Oportunidade Churn",
    "pred": 0.87,
    "probabilidade_compra": 87.5,
    "previsao_gasto_round_up": 450.00,
    "recencia": 45,
    "frequencia": 8,
    "valor_monetario": 3200.50
  }
]
```

### 2. Obter Cross-sell por Produto

**Endpoint:** `GET /rest/V1/rexis/crosssell/:sku`

**Parâmetros de Query:**
- `minLift` (opcional): Lift mínimo (padrão: 1.5)
- `limit` (opcional): Limite de resultados (padrão: 10)

**Exemplo:**

```bash
curl -X GET "https://seu-dominio.com.br/rest/V1/rexis/crosssell/PROD123?minLift=2.0" \
  -H "Authorization: Bearer {token}"
```

**Resposta:**

```json
[
  {
    "antecedent": "PROD123",
    "consequent": "PROD456",
    "support": 0.15,
    "confidence": 0.75,
    "lift": 2.5,
    "conviction": 3.2
  }
]
```

### 3. Obter RFM de Cliente

**Endpoint:** `GET /rest/V1/rexis/rfm/:customerId`

**Exemplo:**

```bash
curl -X GET "https://seu-dominio.com.br/rest/V1/rexis/rfm/123" \
  -H "Authorization: Bearer {token}"
```

**Resposta:**

```json
{
  "identificador_cliente": 123,
  "recency_score": 5,
  "frequency_score": 4,
  "monetary_score": 5,
  "rfm_score": 545,
  "segmento": "Champions",
  "ultima_compra": "2024-01-15",
  "total_compras": 25,
  "valor_total": 12500.00
}
```

### 4. Registrar Conversão

**Endpoint:** `POST /rest/V1/rexis/convert`

**Payload:**

```json
{
  "chaveGlobal": "123_PROD456_2024-02",
  "valorConversao": 450.00
}
```

**Exemplo:**

```bash
curl -X POST "https://seu-dominio.com.br/rest/V1/rexis/convert" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"chaveGlobal":"123_PROD456_2024-02","valorConversao":450.00}'
```

### 5. Obter Métricas Gerais

**Endpoint:** `GET /rest/V1/rexis/metrics`

**Exemplo:**

```bash
curl -X GET "https://seu-dominio.com.br/rest/V1/rexis/metrics" \
  -H "Authorization: Bearer {token}"
```

**Resposta:**

```json
{
  "total_recomendacoes": 1523,
  "oportunidades_churn": 342,
  "oportunidades_crosssell": 678,
  "valor_potencial": 125000.50,
  "clientes_analisados": 450,
  "produtos_recomendados": 156,
  "score_medio": 0.78,
  "taxa_conversao": 15.3
}
```

---

## ⚙️ Configuração de Automações

### Acesso às Configurações

1. Admin Panel → **Stores** → **Configuration**
2. **Grupo Awamotos** → **REXIS ML**

### Seções de Configuração

#### 1️⃣ **General Settings**

- **Habilitar REXIS ML**: ON/OFF
- **Score Mínimo**: Threshold para exibir recomendações (0-1)

#### 2️⃣ **Email Alerts**

- **Habilitar Alertas de Churn**: Enviar emails diários
- **Destinatários**: Emails separados por vírgula
- **Score Mínimo (Churn)**: Padrão 0.85
- **Valor Mínimo (Churn)**: Padrão R$ 500

#### 3️⃣ **WhatsApp Integration**

- **Habilitar WhatsApp**: ON/OFF
- **URL da API**: Ex: `https://api.evolution.com.br`
- **API Key**: Chave de autenticação
- **Números Destinatários**: Ex: `5511999998888,5511988887777`
- **Score Mínimo (Cross-sell)**: Padrão 0.75

#### 4️⃣ **Sincronização ERP**

- **Sincronização Automática**: ON/OFF
- **Frequência**: Diária/Semanal/Mensal
- **Última Sincronização**: Info display

---

## 📧 Email Alerts

### Como Funciona

**Cron Job:** Diariamente às 9h (`0 9 * * *`)

**Critérios:**
- Classificação: `Oportunidade Churn`
- Score ML: ≥ 0.85
- Valor Previsto: ≥ R$ 500
- Limite: Top 20 oportunidades

### Template de Email

O email inclui:
- Lista de clientes em risco de churn
- Nome do cliente e produto
- Score ML (%)
- Valor previsto
- Dias sem comprar
- Botão para acessar dashboard

### Personalizar Template

**Arquivo:** `view/frontend/email/rexisml_churn_alert.html`

Editar variáveis:
- `{{var opportunities}}` - Array de oportunidades
- `{{var total_value}}` - Valor total potencial
- `{{var alert_date}}` - Data do alerta

### Teste Manual

```bash
php bin/magento rexis:send-churn-alert
```

---

## 💬 WhatsApp Integration

### APIs Compatíveis

- **Evolution API** ✅ (Recomendado)
- **Baileys** ✅
- **Twilio WhatsApp** ✅
- **360Dialog** ✅

### Configuração Evolution API

1. Instalar Evolution API:
```bash
docker run -d \
  --name evolution-api \
  -p 8080:8080 \
  -e AUTHENTICATION_API_KEY=sua-chave-aqui \
  atendai/evolution-api
```

2. Configurar no Admin:
   - **URL da API**: `http://localhost:8080`
   - **API Key**: `sua-chave-aqui`
   - **Números**: `5511999998888` (sem espaços ou caracteres especiais)

### Formato da Mensagem WhatsApp

```
🚀 *REXIS ML - Oportunidades de Cross-sell*

📊 *5 oportunidades detectadas*

👤 *João Silva*
📦 Kit de Ferramentas Premium
💰 R$ 450,00
📈 Score: 87.5%

👤 *Maria Santos*
📦 Lubrificante Sintético 5W30
💰 R$ 320,00
📈 Score: 82.3%

📱 Acesse o dashboard para mais detalhes.
```

### Teste Manual

```bash
php bin/magento rexis:send-whatsapp-test --phone=5511999998888
```

### Logs

Verificar logs em:
```bash
tail -f var/log/rexisml_whatsapp.log
```

---

## 🛒 Auto Quote Creation

### Como Funciona

**Trigger:** Após conclusão de pedido (`sales_order_place_after`)

**Lógica:**
1. Cliente finaliza pedido
2. Sistema busca recomendações de Cross-sell com:
   - Score ≥ 0.80
   - Valor ≥ R$ 200
3. Cria carrinho de cotação com até 3 produtos
4. Marca como inativo (não interfere no carrinho ativo)
5. Adiciona nota explicativa

### Verificar Cotações

Admin Panel → **Sales** → **Quotes**

Filtrar por: `customer_note LIKE '%REXIS ML%'`

### Desabilitar

```php
// etc/frontend/events.xml
<!-- Comentar ou remover o observer -->
```

---

## 🎯 Frontend Recommendations

### Exibição Automática

**Onde aparece:**
- ✅ Customer Account Dashboard
- ✅ Checkout Success Page (opcional)
- ✅ Product Page (cross-sell opcional)

### Personalizar Exibição

**Layout XML:**

```xml
<!-- view/frontend/layout/catalog_product_view.xml -->
<referenceContainer name="content.aside">
    <block class="GrupoAwamotos\RexisML\Block\Recommendations"
           name="rexisml.crosssell"
           template="GrupoAwamotos_RexisML::recommendations.phtml">
        <arguments>
            <argument name="classificacao" xsi:type="string">Oportunidade Cross-sell</argument>
            <argument name="limit" xsi:type="number">3</argument>
            <argument name="title" xsi:type="string">Compre Junto</argument>
        </arguments>
    </block>
</referenceContainer>
```

### Estilos Disponíveis

**Classes CSS:**
- `.rexis-churn` - Bordas vermelhas (produtos que cliente parou de comprar)
- `.rexis-crosssell` - Bordas azuis (cross-sell)
- `.rexis-irregular` - Bordas verdes (compra recorrente)

### Badge de ML Score

Exibido automaticamente quando score ≥ 85%

---

## 🧪 Testes

### 1. Testar API

```bash
# Criar script de teste
cat > test_rexis_api.sh << 'EOF'
#!/bin/bash
TOKEN="seu-token-aqui"
BASE_URL="https://seu-dominio.com.br/rest/V1"

# Testar recomendações
curl -X GET "$BASE_URL/rexis/recommendations/123" \
  -H "Authorization: Bearer $TOKEN"

# Testar métricas
curl -X GET "$BASE_URL/rexis/metrics" \
  -H "Authorization: Bearer $TOKEN"
EOF

chmod +x test_rexis_api.sh
./test_rexis_api.sh
```

### 2. Testar Email

```bash
# Forçar envio de email de teste
php bin/magento rexis:test-email comercial@empresa.com
```

### 3. Testar WhatsApp

```bash
# Enviar mensagem de teste
php bin/magento rexis:test-whatsapp 5511999998888
```

### 4. Testar Cron

```bash
# Executar cron manualmente
php bin/magento cron:run --group=default

# Verificar logs
tail -f var/log/system.log | grep REXIS
```

---

## 📊 Monitoramento

### Logs Disponíveis

```bash
# Log geral do sistema
tail -f var/log/system.log | grep "REXIS ML"

# Log de debug (se habilitado)
tail -f var/log/debug.log | grep RexisML

# Log de exceptions
tail -f var/log/exception.log | grep RexisML
```

### Métricas de Performance

Dashboard Admin → **REXIS ML** → **Dashboard**

**KPIs monitorados:**
- Total de recomendações
- Taxa de conversão
- Valor potencial
- Score médio ML

### Alertas de Falha

Configurar monitoramento de:
- Falha no envio de emails
- Falha na API WhatsApp
- Erros de sincronização
- Baixa taxa de conversão

---

## 🔧 Troubleshooting

### Problema: Email não envia

**Solução:**
1. Verificar configuração SMTP
2. Checar logs: `var/log/system.log`
3. Testar template:
```bash
php bin/magento dev:email-template:preview rexisml_churn_alert
```

### Problema: WhatsApp não envia

**Solução:**
1. Verificar se API está acessível:
```bash
curl -H "apikey: SUA_CHAVE" http://api-url/status
```
2. Validar formato do número (DDI+DDD+número)
3. Checar logs: `var/log/rexisml_whatsapp.log`

### Problema: Recomendações não aparecem

**Solução:**
1. Verificar se módulo está habilitado em config
2. Verificar se cliente está logado
3. Confirmar que existem recomendações:
```sql
SELECT * FROM rexis_dataset_recomendacao
WHERE identificador_cliente = 123 AND pred >= 0.7;
```

### Problema: API retorna 401

**Solução:**
1. Regenerar token de autenticação
2. Verificar permissões ACL: `GrupoAwamotos_RexisML::recommendations`
3. Limpar cache:
```bash
php bin/magento cache:clean config
```

---

## 📈 Próximos Passos

### Melhorias Futuras

1. **Real-time Recommendations**
   - Atualizar recomendações em tempo real durante navegação
   - Usar WebSocket ou AJAX polling

2. **A/B Testing**
   - Testar diferentes thresholds de score
   - Comparar efetividade de diferentes mensagens

3. **Push Notifications**
   - Integrar com PWA para push notifications
   - Alertas mobile para vendedores

4. **Integração CRM**
   - Exportar leads para Salesforce/HubSpot
   - Sincronização bidirecional

---

## 📞 Suporte

Para dúvidas ou problemas, entre em contato:
- **Email:** dev@grupoawamotos.com.br
- **GitHub Issues:** [Link do repositório]

---

**Documentação gerada em:** <?= date('Y-m-d') ?>
**Versão do Módulo:** 1.0.0
**Magento Version:** 2.4.x
