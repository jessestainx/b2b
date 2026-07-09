# Módulo B2B GrupoAwamotos - Documentação Completa

## Visão Geral

Módulo B2B enterprise-grade para Magento 2.4.8 com funcionalidades completas para comércio business-to-business.

## Versão: 1.3.0
**Última atualização:** Janeiro 2026

---

## 🔒 Modo de Operação

### Modo Estrito (Strict) - PADRÃO
- **Apenas cadastros B2B são permitidos**
- Registro padrão de clientes desabilitado
- Tentativas de acesso a `/customer/account/create` são redirecionadas para `/b2b/register`
- Links de "Criar Conta" apontam para cadastro B2B
- Ideal para lojas exclusivamente corporativas

### Modo Misto (Mixed)
- Permite cadastros B2B e B2C
- Ambos os formulários de registro estão disponíveis
- Sugestão de B2B exibida no registro padrão
- Ideal para lojas híbridas

**Configuração:** `Admin > Stores > Configuration > Grupo Awamotos > B2B Settings > General > Modo B2B`

---

## Funcionalidades Principais

### 1. 👥 Gestão de Clientes B2B

**Grupos de Clientes:**
- **B2B Atacado (ID: 4)** - Desconto automático de 15%
- **B2B VIP (ID: 5)** - Desconto automático de 20%
- **B2B Revendedor (ID: 6)** - Desconto automático de 10%
- **B2B Pendente (ID: 7)** - Aguardando aprovação

**Atributos de Cliente:**
- `b2b_cnpj` - CNPJ da empresa
- `b2b_razao_social` - Razão Social
- `b2b_inscricao_estadual` - Inscrição Estadual
- `b2b_approved` - Status de aprovação
- `b2b_company_phone` - Telefone comercial

### 2. 💰 Sistema de Preços B2B

**GroupPricePlugin:**
- Aplica descontos automáticos baseados no grupo do cliente
- Verifica status de aprovação antes de aplicar desconto
- Hooks: `afterGetPrice()`, `afterGetFinalPrice()`

**Visibilidade de Preços:**
- Opção de ocultar preços para visitantes
- Mensagem customizável "Faça login para ver preços"
- Controle para clientes pendentes

### 3. 📋 Sistema de Cotações (RFQ)

**URLs:**
- `/b2b/quote/index` - Solicitar nova cotação
- `/b2b/quote/history` - Histórico de cotações

**Admin:**
- Listagem de cotações em `B2B > Cotações`
- Responder cotação com valor e prazo
- Aprovar/Rejeitar com notificação automática

**Status de Cotação:**
- `pending` - Aguardando análise
- `processing` - Em análise
- `approved` - Aprovada com valor
- `rejected` - Rejeitada
- `expired` - Expirada
- `converted` - Convertida em pedido

### 4. 📊 Dashboard B2B do Cliente

**URL:** `/b2b/account/dashboard`

**Informações exibidas:**
- Dados da empresa (CNPJ, Razão Social)
- Grupo e percentual de desconto
- Limite de crédito disponível
- Últimas cotações
- Últimos pedidos
- Valor de compras (30 dias)
- Ações rápidas

### 5. 📝 Formulário de Cadastro B2B

**URL:** `/b2b/register/index`

**Campos:**
- CNPJ (com validação local + API ReceitaWS)
- Razão Social
- Inscrição Estadual
- Telefone Comercial
- Dados de contato (nome, email, senha)

**Fluxo:**
1. Cliente preenche formulário
2. CNPJ é validado via API da Receita
3. Conta é criada no grupo "B2B Pendente"
4. Admin recebe notificação por email
5. Após aprovação, cliente é movido para grupo apropriado

### 6. 💳 Limite de Crédito

**Tabela:** `grupoawamotos_b2b_credit_limit`

**Campos:**
- `credit_limit` - Limite total
- `used_credit` - Crédito utilizado
- `currency_code` - Moeda (padrão BRL)

### 7. 📧 Templates de Email

| Template ID | Descrição |
|-------------|-----------|
| `grupoawamotos_b2b_admin_new_customer` | Notifica admin sobre novo cliente |
| `grupoawamotos_b2b_customer_approved` | Cliente aprovado |
| `grupoawamotos_b2b_customer_rejected` | Cliente rejeitado |
| `grupoawamotos_b2b_admin_new_quote` | Nova cotação recebida |
| `grupoawamotos_b2b_quote_replied` | Cotação respondida |
| `grupoawamotos_b2b_quote_response` | Orçamento enviado (aprovado) |
| `grupoawamotos_b2b_quote_rejected` | Cotação rejeitada |
| `grupoawamotos_b2b_registration_confirmation` | Confirmação de cadastro |
| `grupoawamotos_b2b_registration_admin` | Notifica admin sobre cadastro |

---

## Configuração

### Admin > Stores > Configuration > Grupo Awamotos > B2B Settings

**Seções:**
1. **Configurações Gerais**
   - Habilitar módulo
   - Modo (Mixed/Strict)

2. **Visibilidade de Preços**
   - Ocultar para visitantes
   - Ocultar botão comprar
   - Mensagem customizada

3. **Aprovação de Clientes**
   - Exigir aprovação
   - Grupos auto-aprovados
   - Notificações por email

4. **Quantidade Mínima**
   - Qtd mínima global
   - Valor mínimo do pedido

5. **Sistema de Cotação**
   - Habilitar RFQ
   - Validade (dias)
   - Permitir visitantes

6. **Grupos de Clientes**
   - Configurar grupos B2B
   - Descontos por grupo

---

## Estrutura de Arquivos

```
app/code/GrupoAwamotos/B2B/
├── Api/
│   ├── CustomerApprovalInterface.php
│   ├── PriceVisibilityInterface.php
│   └── QuoteRequestRepositoryInterface.php
├── Block/
│   ├── Account/
│   │   └── Dashboard.php
│   ├── Adminhtml/
│   │   ├── Customer/
│   │   │   └── Approval.php
│   │   └── Quote/
│   │       └── Respond.php
│   └── Register/
│       └── Form.php
├── Controller/
│   ├── Account/
│   │   └── Dashboard.php
│   ├── Adminhtml/
│   │   ├── Customer/
│   │   │   └── Approve.php
│   │   └── Quote/
│   │       ├── Respond.php
│   │       └── Save.php
│   ├── Quote/
│   │   ├── Request.php
│   │   └── Submit.php
│   └── Register/
│       ├── Index.php
│       └── Save.php
├── Helper/
│   ├── CnpjValidator.php
│   └── Data.php
├── Model/
│   ├── CreditLimit.php
│   ├── CustomerApproval.php
│   ├── PriceVisibility.php
│   ├── QuoteRequest.php
│   ├── QuoteRequestRepository.php
│   ├── Config/
│   │   └── Source/
│   └── ResourceModel/
│       ├── CreditLimit.php
│       ├── QuoteRequest.php
│       └── CreditLimit/
│           └── Collection.php
├── Plugin/
│   ├── GroupPricePlugin.php
│   ├── HideFinalPricePlugin.php
│   └── HidePricePlugin.php
├── Setup/
│   └── Patch/
│       └── Data/
│           └── CreateCustomerGroups.php
├── etc/
│   ├── acl.xml
│   ├── adminhtml/
│   │   ├── menu.xml
│   │   ├── routes.xml
│   │   └── system.xml
│   ├── config.xml
│   ├── db_schema.xml
│   ├── di.xml
│   ├── email_templates.xml
│   ├── frontend/
│   │   └── routes.xml
│   └── module.xml
└── view/
    ├── adminhtml/
    │   ├── email/
    │   ├── layout/
    │   └── templates/
    │       └── quote/
    │           └── respond.phtml
    └── frontend/
        ├── email/
        ├── layout/
        ├── templates/
        │   ├── account/
        │   │   └── dashboard.phtml
        │   └── register/
        │       └── form.phtml
        └── web/
            └── css/
                ├── b2b-dashboard.css
                └── b2b-register.css
```

---

## Tabelas do Banco de Dados

1. **grupoawamotos_b2b_quote_request** - Solicitações de cotação
2. **grupoawamotos_b2b_quote_request_item** - Itens das cotações
3. **grupoawamotos_b2b_customer_approval_log** - Log de aprovações
4. **grupoawamotos_b2b_credit_limit** - Limites de crédito

---

## Comandos CLI

```bash
# Verificar status do módulo
php bin/magento module:status GrupoAwamotos_B2B

# Recompilar
php bin/magento setup:di:compile

# Limpar cache
php bin/magento cache:flush

# Limpar cache de consultas de CNPJ (todas)
php bin/magento b2b:cnpj-cache:clear

# Limpar cache de consulta de um CNPJ específico
php bin/magento b2b:cnpj-cache:clear --cnpj=11222333000181

# Testar funcionalidades
php scripts/test_b2b_module.php
php scripts/test_b2b_enhancements.php
```

---

## URLs Principais

| Rota | Descrição |
|------|-----------|
| `/b2b/register/index` | Formulário de cadastro B2B |
| `/b2b/account/dashboard` | Dashboard do cliente B2B |
| `/b2b/quote/index` | Solicitar cotação |
| `/b2b/quote/history` | Histórico de cotações |
| `/admin/grupoawamotos_b2b/quote/` | Admin - Listagem de cotações |

---

## Integração com ReceitaWS

O módulo utiliza a API gratuita da ReceitaWS para validação de CNPJ:
- Endpoint: `https://receitaws.com.br/v1/cnpj/{cnpj}`
- Retorna: Razão Social, Situação, Endereço, etc.
- Rate limit: Respeitar limites da API
- Consulta externa, timeout, exigência de status ATIVA e cache são configuráveis no Admin:
  `Stores > Configuration > Grupo Awamotos > B2B > Consulta CNPJ`

---

## Changelog

### v1.2.0 (Atual)
- Adicionado GroupPricePlugin para descontos automáticos
- Implementado CnpjValidator com validação API
- Criado Dashboard B2B completo
- Criado Formulário de Cadastro B2B
- Sistema de Resposta a Cotações no Admin
- Novos templates de email
- Model/ResourceModel para CreditLimit
- CSS responsivo para frontend

### v1.1.0
- Sistema de cotações (RFQ)
- Aprovação de clientes
- Visibilidade de preços
- Quantidade mínima

### v1.0.0
- Estrutura inicial
- Grupos de clientes B2B
- Atributos de cliente
- ACL e menu admin

---

## Suporte

Para problemas ou melhorias, verifique os logs:
- `var/log/system.log`
- `var/log/exception.log`

Execute os scripts de teste para diagnóstico:
```bash
php scripts/test_b2b_module.php
php scripts/test_b2b_enhancements.php
```
