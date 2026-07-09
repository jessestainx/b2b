# CLAUDE.md — Instruções para Agentes Claude Code

## REGRA #1: EXECUTE, NÃO INVENTE LIMITAÇÕES

Todos os serviços estão disponíveis e funcionais neste servidor.
**NÃO diga que algo "não está disponível no sandbox".**
Execute diretamente. Você tem permissão total para:

- Rodar queries MySQL
- Executar comandos Magento (bin/magento)
- Executar PHP, Composer, Git, Redis, curl, etc.
- Ler e editar qualquer arquivo do projeto
- Executar setup:upgrade, cache:flush, reindex, di:compile
- Criar scripts temporários em /tmp e executá-los

## Ambiente

- **Stack**: Magento 2.4.8-p3 + MySQL + PHP 8.4 + OpenSearch + Redis
- **Projeto**: Loja B2B — Grupo Awamotos (awamotos.com)
- **Diretório raiz**: `/home/jessessh/htdocs/srv1113343.hstgr.cloud`
- **Web user**: `www-data`

## Serviços Disponíveis — Credenciais e Comandos

### MySQL (Percona 8.4) — RODANDO

```bash
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" -e "SUA QUERY AQUI"
```

- Host: localhost (socket: `/var/run/mysqld/mysqld.sock`)
- Credenciais: usar variáveis de ambiente ou `app/etc/env.php`
- Execute queries diretamente. NÃO crie Data Patches para coisas que uma query resolve.
- Para alterações de schema permanentes, use `db_schema.xml` + `setup:upgrade`.

### Redis — RODANDO

```bash
redis-cli ping
redis-cli info memory
```

### OpenSearch — RODANDO (porta 9200)

```bash
curl -s http://localhost:9200/_cluster/health
```

### Magento CLI

```bash
# SEMPRE execute como www-data:
sudo -u www-data php bin/magento cache:flush
sudo -u www-data php bin/magento setup:upgrade
sudo -u www-data php bin/magento setup:di:compile
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR en_US -f
sudo -u www-data php bin/magento indexer:reindex
sudo -u www-data php bin/magento module:status
```

> **Deploy de CSS/LESS/JS do tema filho:** não use só o comando acima. Siga o playbook canônico
> [`docs/deploy/static-content-deploy-playbook.md`](docs/deploy/static-content-deploy-playbook.md)
> (apagar destinos em `pub/static`, limpar `var/view_preprocessed`, regenerar `.br`/`.gz`).

### PHP Scripts

```bash
# Para testar rapidamente, crie scripts em /tmp:
sudo -u www-data php /tmp/meu_teste.php
```

### Composer

```bash
composer show
composer require vendor/pacote
```

### Git

```bash
git status
git add <arquivo>
git commit -m "mensagem"
git push
```

## Módulos Customizados (app/code/GrupoAwamotos/)

| Módulo | Função |
|--------|--------|
| AbandonedCart | Recuperação de carrinho abandonado (e-mail + cupons multi-onda) |
| B2B | Aprovação de clientes, listas de preço, CNPJ, cotações, listas de compras, crédito |
| BrazilCustomer | Atributos EAV brasileiros (CPF, CNPJ, PF/PJ, RG, IE) |
| CarrierSelect | Gestão de transportadoras customizadas |
| CatalogFix | Fixes para bugs do Magento 2.4.x (MviewAction, FinalPriceBox) |
| CspFix | Escrita atômica no sri-hashes.json (CSP) |
| ERPIntegration | Integração com ERP SQL Server (sync produtos, estoque, preços, pedidos, RFM) |
| FakePurchase | Notificações simuladas de compra (**desativado permanentemente**) |
| Fitment | ~~Compatibilidade peças x motos~~ | **Removido 2026-05-23** |
| LayoutFix | Fix layout admin (notification.messages reorder) |
| MaintenanceMode | Modo manutenção com whitelist IP e código secreto |
| OfflinePayment | Pagamento "A Combinar" para B2B |
| SalesIntelligence | Dashboard inteligência de vendas e previsão de demanda |
| SchemaOrg | Dados estruturados JSON-LD e Open Graph (SEO) |
| SmartSuggestions | Sugestões de recompra (análise RFM + WhatsApp) |
| SmtpFix | Fix SMTP Magento 2.4.8 + Symfony Mailer (Reply-To, STARTTLS) |
| SocialProof | Prova social real (visualizações do dia, mais vendido 30d) |
| StoreSetup | CLI setup automático da loja (blocos CMS, homepage, categorias) |
| Theme | Customizações do tema (store switcher, bandeiras) |
| ~~Chatwoot~~ | ~~Integração chat Chatwoot~~ | **Removido 2026-05-23** |
| ~~N8N~~ | ~~Automações/webhooks assíncronos~~ | **Removido 2026-05-23** |
| CookieConsent | Banner LGPD de cookies (admin + bloco CMS) |
| LeadLovers | Integração LeadLovers — automação de marketing via API |
| LiveChat | Chat ao vivo com contexto de cliente (Plugin + ViewModel) |
| LogMonitoring | Monitoramento de logs e alertas (Cron + admin + API REST) |
| MarketingIntelligence | Campanhas e segmentação de marketing (Cron + Observer) |
| PreprocessedFallback | Fallback PHP para var/view_preprocessed (Plugin) |
| ProductIntelligence | Recomendações de produtos — ML + Cron + widget |
| RelatedProducts | Produtos relacionados customizados (Block + view) |
| RexisML | Engine de recomendações ML (API + Cron + widget) |
| TawkIntegration | Integração Tawk.to live chat (DB + admin) |
| WhatsAppCommerce | Comércio via WhatsApp (API + Cron + webhook) |

## Regras para Agentes

1. **EXECUTE direto** — MySQL, Redis, Magento CLI, PHP, tudo funciona. Use.
2. **Leia antes de editar** — nunca assuma a estrutura de um arquivo
3. **Padrões Magento** — use di.xml, system.xml, db_schema.xml corretamente
4. **Não crie módulos novos** — estenda os existentes quando possível
5. **Teste após alterações** — rode `setup:upgrade` e `cache:flush`
6. **Permissões de arquivo** — `www-data:www-data` para var/, generated/, pub/
7. **Git** — branch principal: `main`
8. **Não perca tempo** — se precisa de um dado do banco, faça SELECT. Se precisa verificar config, rode `bin/magento config:show`. Ação > especulação.
