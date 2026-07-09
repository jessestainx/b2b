# 🔐 Migração B2B (AWA Motos → PWA Studio)

> Documento técnico para preservar lógica B2B customizada durante migração.

## ⚠️ Desafios específicos

A AWA Motos tem **funcionalidades B2B customizadas** que NÃO existem no Magento padrão:

| Feature | Magento padrão | AWA customizado |
|---------|----------------|-----------------|
| Login | email + senha | **CNPJ + Razão Social + senha** |
| Preço | igual para todos | **por grupo de atendimento** |
| Catálogo | padrão | **margem mínima por categoria** |
| Pedido | simples | **cotação + aprovação** |
| Pagamento | cartão, PIX | **boleto 30/60/90, depósito** |
| Estoque | básico | **multi-CDN com previsão** |

## 🎯 Estratégia de migração

### Opção A: Migração completa (recomendado para longo prazo)
- Criar módulos GraphQL custom para extender schema Magento
- Reescrever UI B2B em React usando Venia UI
- Tempo: 4-6 semanas adicionais
- Custo: alto, mas resultado final é PWA moderno

### Opção B: Migração híbrida (curto prazo)
- PWA Studio para visitantes (catálogo, busca)
- Luma mantido para área B2B (login, dashboard, cotação)
- **Iframe** para área logada B2B dentro do PWA
- Tempo: 2-3 semanas
- Custo: baixo, mantém compatibilidade 100%

### Opção C: Wrapper API B2B (intermediário)
- Continuar usando REST API do Magento para B2B
- PWA Studio usa GraphQL para público, REST para B2B
- Componentes B2B fazem fetch direto para endpoints REST
- Tempo: 3-4 semanas
- Custo: médio, melhor dos dois mundos

## 📁 Estrutura criada para migração

```
pwa-studio/src/
├── components/
│   └── B2BAuth/
│       └── B2BLoginForm.jsx          # Login com CNPJ (preserva AWA)
├── queries/
│   └── b2b.js                       # GraphQL/REST queries B2B customizadas
└── lib/
    └── (a criar)                    # Utilitários (CNPJ validator, etc)
```

## 🚀 Quando ativar?

A migração B2B só deve começar **DEPOIS** da migração de:
1. ✅ Catálogo público (homepage + category + product)
2. ✅ Carrinho
3. ✅ Checkout básico
4. ⏳ **DEPOIS:** B2B Login + Dashboard
5. ⏳ **DEPOIS:** Quick Order
6. ⏳ **DEPOIS:** Cotação online

**Ordem importa**: cada migração precisa de 2-4 semanas para estabilizar.

## 📚 Módulos Magento que precisam de schema GraphQL custom

Criar `app/code/GrupoAwamotos/B2BGraphQL/` com:

```graphql
type Customer {
    awa_b2b_customer: B2BCustomer @resolver(class: "\\GrupoAwamotos\\B2BGraphQL\\Model\\Resolver\\B2BCustomer")
}

type B2BCustomer {
    cnpj: String
    razao_social: String
    grupo_atendimento: String
    credit_limit: Float
    credit_used: Float
    credit_available: Float
}

type Mutation {
    awaB2BLogin(cnpj: String!, password: String!): B2BLoginResult
    awaRequestQuote(items: [QuoteItemInput!]!): QuoteResult
}
```

## 🎯 Componente React de exemplo (já criado)

`src/components/B2BAuth/B2BLoginForm.jsx` contém:
- ✅ Validação de CNPJ (módulo 11)
- ✅ Máscara automática
- ✅ Autocomplete de Razão Social
- ✅ Mutation GraphQL
- ✅ Acessibilidade (aria-*)

## ✅ Checklist de migração B2B

- [ ] Schema GraphQL custom criado
- [ ] Componente B2BLoginForm integrado com Venia
- [ ] Tokens B2B isolados dos tokens de visitantes
- [ ] Dashboard B2B com widgets customizados
- [ ] Quick Order (busca por SKU com autocomplete)
- [ ] Cotação online (upload de planilha + carrinho)
- [ ] Aprovação de cotação por gerente
- [ ] Relatórios B2B (vendas por grupo, inadimplência)

## 📞 Contato técnico

Para dúvidas sobre a lógica B2B existente, consultar:
- `app/code/GrupoAwamotos/B2B/` (módulo Magento atual)
- `docs/PLANO_BUGS_VISUAIS.md` (histórico)
- Time: dev@awamotos.com
