# 🧪 REXIS ML - Guia Completo de Testes

## ✅ Status da Instalação

- ✅ Módulo habilitado
- ✅ 4 tabelas SQL criadas
- ✅ Dados de teste inseridos
- ✅ Cache limpo

---

## 🎯 Testes que Você Pode Fazer AGORA

### 1. 📊 DASHBOARD ADMINISTRATIVO

**Como acessar:**
1. Faça login no Admin Panel
2. No menu lateral, procure **"REXIS ML"**
3. Clique em **"Dashboard"**

**O que você verá:**
- ✅ 8 KPIs com estatísticas
- ✅ 3 gráficos interativos (ApexCharts)
- ✅ Tabelas com top oportunidades
- ✅ Botão de refresh

**URL direta:**
```
https://seu-dominio.com.br/admin/rexisml/dashboard/index
```

---

### 2. 🔌 TESTAR API REST

#### a) Gerar Token de Autenticação

```bash
curl -X POST "https://seu-dominio.com.br/rest/V1/integration/admin/token" \
  -H "Content-Type: application/json" \
  -d '{"username":"seu_admin","password":"sua_senha"}'
```

**Salve o token retornado!**

#### b) Buscar Métricas Gerais

```bash
curl -X GET "https://seu-dominio.com.br/rest/V1/rexis/metrics" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "total_recomendacoes": 6,
  "oportunidades_churn": 3,
  "oportunidades_crosssell": 3,
  "valor_potencial": 2810.00,
  "clientes_analisados": 3,
  "produtos_recomendados": 6,
  "score_medio": 0.8517,
  "taxa_conversao": 0
}
```

#### c) Buscar Recomendações de um Cliente

```bash
curl -X GET "https://seu-dominio.com.br/rest/V1/rexis/recommendations/1?minScore=0.7&limit=10" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

---

### 3. 💻 VERIFICAR BANCO DE DADOS

```sql
-- Ver todas as recomendações
SELECT
    identificador_cliente,
    identificador_produto,
    classificacao_produto,
    ROUND(pred * 100, 1) as score_pct,
    previsao_gasto_round_up as valor,
    recencia
FROM rexis_dataset_recomendacao
ORDER BY pred DESC;

-- Estatísticas por classificação
SELECT
    classificacao_produto,
    COUNT(*) as quantidade,
    ROUND(AVG(pred) * 100, 1) as score_medio,
    SUM(previsao_gasto_round_up) as valor_total
FROM rexis_dataset_recomendacao
GROUP BY classificacao_produto;
```

---

### 4. 📧 TESTAR ALERTAS (Quando CLI estiver ativo)

Atualmente os comandos CLI estão temporariamente desabilitados durante a instalação.

**Para ativar:**

1. Edite o arquivo: `app/code/GrupoAwamotos/RexisML/etc/di.xml`

2. Descomente as linhas dos comandos CLI:
```xml
<item name="rexis_test_email" xsi:type="object">GrupoAwamotos\RexisML\Console\Command\TestEmailCommand</item>
<item name="rexis_test_whatsapp" xsi:type="object">GrupoAwamotos\RexisML\Console\Command\TestWhatsAppCommand</item>
<item name="rexis_stats" xsi:type="object">GrupoAwamotos\RexisML\Console\Command\StatsCommand</item>
```

3. Limpe o cache:
```bash
rm -rf generated/code/* var/cache/*
php bin/magento cache:flush
```

4. Teste os comandos:
```bash
# Ver estatísticas
php bin/magento rexis:stats

# Testar email
php bin/magento rexis:test-email seu@email.com

# Testar WhatsApp
php bin/magento rexis:test-whatsapp 5511999998888
```

---

### 5. 🎨 TESTAR WIDGET NO CMS

**Passo a passo:**

1. Admin → **Content** → **Widgets**
2. Clique em **"Add Widget"**
3. Em **"Type"**, selecione: **REXIS ML - Recomendações Personalizadas**
4. Em **"Design Theme"**, selecione seu tema
5. Clique **"Continue"**
6. Configure:
   - **Título:** "Produtos Recomendados"
   - **Tipo:** Oportunidade Cross-sell
   - **Quantidade:** 4
   - **Template:** Padrão (Grid)
7. Em **"Layout Updates"**, adicione onde quer exibir
8. Salve

**Inserir manualmente em CMS Page:**
```
{{widget type="GrupoAwamotos\RexisML\Block\Widget\Recommendations" widget_title="Sugestões para Você" limit="4"}}
```

---

### 6. 🧪 TESTE RÁPIDO NO NAVEGADOR

Acesse estas URLs (substitua pelo seu domínio):

#### Dashboard Admin
```
https://seu-dominio.com.br/admin/rexisml/dashboard/index
```

#### AJAX Endpoint (precisa estar logado)
```
https://seu-dominio.com.br/rexisml/ajax/getrecommendations?limit=5
```

---

## 📊 DADOS DE TESTE INSERIDOS

### Recomendações (6 registros)

| Cliente | Produto | Classificação | Score | Valor | Recência |
|---------|---------|---------------|-------|-------|----------|
| 1 | PROD001 | Churn | 92% | R$ 550 | 65 dias |
| 2 | PROD023 | Churn | 88% | R$ 620 | 72 dias |
| 3 | PROD012 | Churn | 85% | R$ 490 | 54 dias |
| 1 | PROD102 | Cross-sell | 82% | R$ 320 | 15 dias |
| 2 | PROD145 | Cross-sell | 80% | R$ 280 | 18 dias |
| 3 | PROD167 | Cross-sell | 85% | R$ 410 | 12 dias |

**Total Valor Potencial:** R$ 2.670,00

---

## 🔧 PRÓXIMOS PASSOS

### 1. Configurar Credenciais no Admin

**Stores → Configuration → Grupo Awamotos → REXIS ML**

Configure:
- Email recipients (para alertas de Churn)
- WhatsApp API URL e Key (se tiver Evolution API)
- Score mínimo (padrão: 0.7)

### 2. Sincronizar Dados Reais do ERP

Quando estiver pronto, execute:

```bash
python3 scripts/rexis_ml_sync.py
```

Ou via CLI (quando ativo):
```bash
php bin/magento rexis:sync
```

### 3. Ativar Automações

1. Configure cron jobs no servidor
2. Configure SMTP para emails
3. Configure Evolution API para WhatsApp
4. Teste os alertas automáticos

---

## 🐛 TROUBLESHOOTING

### Dashboard não aparece no menu

**Solução:**
```bash
php bin/magento cache:clean config
php bin/magento indexer:reindex
```

### API retorna 401 Unauthorized

**Causa:** Token inválido ou expirado

**Solução:** Gerar novo token de autenticação

### Erro "Class does not exist" ao ativar CLI commands

**Causa:** Cache de DI desatualizado

**Solução:**
```bash
rm -rf generated/code/* generated/metadata/*
php bin/magento setup:di:compile
```

### Tabelas vazias no Dashboard

**Causa:** Sem dados inseridos

**Solução:** Execute o script SQL de teste novamente ou faça sincronização ERP

---

## 📸 Screenshots Esperados

### Dashboard
Você deve ver:
- 📊 8 cards com KPIs coloridos
- 📈 Gráfico de pizza (distribuição)
- 📊 Gráfico de barras (RFM)
- 📊 Gráfico horizontal (produtos)
- 📋 3 tabelas com dados

### API Response
```json
{
  "success": true,
  "total": 6,
  "recommendations": [...]
}
```

---

## ✅ CHECKLIST DE VALIDAÇÃO

Marque conforme for testando:

- [ ] Dashboard acessível no admin
- [ ] Gráficos carregando com dados
- [ ] API REST respondendo (métricas)
- [ ] API REST respondendo (recomendações)
- [ ] Dados aparecendo nas tabelas SQL
- [ ] Widget configurável no CMS
- [ ] AJAX endpoint respondendo JSON
- [ ] Configurações salvando no admin
- [ ] Cache limpando sem erros
- [ ] Logs sem erros críticos

---

## 🎯 TESTE COMPLETO (15 minutos)

### Roteiro de Teste Rápido:

1. **[2 min]** Acessar dashboard → verificar gráficos
2. **[3 min]** Gerar token API → testar endpoint de métricas
3. **[2 min]** Verificar dados no SQL
4. **[3 min]** Criar widget CMS → visualizar frontend
5. **[2 min]** Configurar settings no admin
6. **[3 min]** Testar AJAX no navegador (F12 → Network)

**Se todos passarem: Sistema 100% funcional! 🎉**

---

## 📞 SUPORTE

**Documentação completa:**
- `README.md` - Visão geral
- `API_AUTOMATIONS_GUIDE.md` - API completa
- `ENHANCED_FEATURES.md` - Features avançadas
- `COMPLETE_IMPLEMENTATION.md` - Detalhes técnicos

**Problemas?**
- Verificar logs: `var/log/system.log`
- Verificar exceções: `var/log/exception.log`
- Debug mode: `bin/magento deploy:mode:set developer`

---

**Última atualização:** <?= date('Y-m-d H:i') ?>
**Versão do REXIS ML:** 1.1.0
