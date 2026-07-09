# AWA Motos — PWA Studio

> Migração gradual do tema AYO/AYO Home5 para PWA Studio + Venia UI

## 🎯 Objetivo

Substituir a stack Luma + jQuery + Bootstrap por **PWA Studio + Venia UI + React + GraphQL**.

**Benefícios esperados:**
- ⚡ Lighthouse 95+ (vs ~50 atual)
- 📱 PWA installable no celular
- 🔌 Funciona offline
- 💰 +15-30% conversão mobile

## 📁 Estrutura

```
pwa-studio/
├── package.json              # Dependências Venia + React
├── .env.example              # Template de config
├── src/
│   ├── components/           # Componentes React customizados
│   ├── pages/                # Páginas da SPA
│   └── queries/               # GraphQL queries/mutations
└── ...
```

## 🚀 Roadmap de Migração

### Fase 1: Setup (1-2 semanas)
- [x] Criar scaffold PWA Studio ✅
- [x] Configurar GraphQL endpoint (Magento já tem)
- [ ] Setup CI/CD paralelo
- [ ] Configurar nginx para servir em `/pwa/*`

### Fase 2: Catálogo (2-3 semanas)
- [ ] Homepage
- [ ] Listagem de categorias
- [ ] Página de produto
- [ ] Busca

### Fase 3: Auth + Cart (2-3 semanas)
- [ ] Login B2B custom (mantém GrupoAwamotos_B2B)
- [ ] Carrinho
- [ ] Checkout one-page
- [ ] Integração ERP

### Fase 4: Cutover gradual
- [ ] A/B test Luma vs PWA
- [ ] Feature flag para migração
- [ ] Lighthouse CI/CD

## ⚠️ Considerações

### Compatibilidade B2B
- AWA tem B2B custom (`GrupoAwamotos_B2B`) com CNPJ/Razão Social
- **Necessário**: criar GraphQL schemas custom para B2B
- **Alternativa**: iframe para páginas B2B críticas (manter Luma)

### Vertical Menu
- O tema atual tem vertical menu (mega menu) customizado
- Venia tem header fixo diferente
- **Necessário**: desenvolver componente custom para manter identidade

### Performance do Magento Backend
- GraphQL adiciona ~50ms por request
- Magento deve ter Varnish ou FPC ativo
- **Já configurado**: FPC Magento ativo ✅

## 📚 Documentação

- [PWA Studio Docs](https://developer.adobe.com/commerce/pwa-studio/)
- [Venia UI](https://venia.magento.com/)
- [Magento GraphQL](https://developer.adobe.com/commerce/webapi/graphql/)
- [Guia interno AWA: docs/PLANO_BUGS_VISUAIS.md]

## 🔗 Links Úteis

- Backend atual: https://awamotos.com
- GraphQL: https://awamotos.com/graphql
- Manifest: https://awamotos.com/manifest.json
- Service Worker: https://awamotos.com/sw.js
