# WhatsAppCommerce — Plano de Correção por Fases

> Documento gerado a partir da auditoria profunda do módulo `GrupoAwamotos_WhatsAppCommerce` (06/07/2026).
> Achados validados diretamente no banco de produção (`magento`), não apenas por leitura de código.

## Como usar este documento

Cada fase é independente e deve ser fechada com `setup:upgrade` + `cache:flush` + validação antes de seguir para a próxima. As fases estão ordenadas por **risco de regressão crescente** (a Fase 1 é a mais segura de aplicar isoladamente).

---

## Fase 1 — Bugs funcionais críticos (features quebradas em produção)

**Objetivo:** consertar duas features de marketing que nunca funcionaram desde o deploy, sem alterar comportamento de nada que já funciona.

**Risco:** baixo (mudanças isoladas em queries SQL, nenhuma mudança de contrato de API).

### 1.1 — `WhatsAppCampaign`: broadcast e segmentos sempre vazios

**Causa raiz:**
- `whatsapp_optin` é atributo `int` (`customer_entity_int`), mas o código consulta `customer_entity_varchar`.
- `getPhoneAttributeId()` procura o atributo `telephone` no customer (`entity_type_id=1`), que não existe — `telephone` só existe em `customer_address` (`entity_type_id=2`).

**Arquivo:** `Model/WhatsAppCampaign.php`

**Correção:**
1. Trocar a tabela de consulta do opt-in de `customer_entity_varchar` para `customer_entity_int` em `getSegmentStats()` (3 subqueries) e `getPhonesForSegment()`.
2. Trocar a origem do telefone: parar de procurar atributo `telephone` no customer e usar `customer_address_entity` via `default_billing`, no mesmo padrão já usado corretamente em `ERPIntegration/Cron/CustomerBirthdayWish.php`:
   ```sql
   LEFT JOIN customer_address_entity ca ON ca.entity_id = ce.default_billing
   ```
3. Remover `getPhoneAttributeId()` (fica morto) e ajustar `getPhonesForSegment()` para não depender mais dele.

**Validação:**
```bash
sudo -u www-data php bin/magento cache:flush
mysql -e "SELECT ce.entity_id FROM customer_entity ce WHERE ce.entity_id IN (SELECT entity_id FROM customer_entity_int cei JOIN eav_attribute ea ON ea.attribute_id=cei.attribute_id WHERE ea.attribute_code='whatsapp_optin' AND cei.value=1);" magento
```
Chamar `GET /V1/awa-whatsapp/campaign/segments` com token válido e confirmar que `all_optin` bate com a contagem acima (hoje: 2 clientes).

### 1.2 — `RetargetingCampaign`: cron nunca envia mensagem

**Causa raiz:** `PHONE_ATTRIBUTE = 'telefone_celular'` — atributo que não existe em nenhuma entidade do sistema.

**Arquivo:** `Cron/RetargetingCampaign.php`

**Correção:**
1. Remover a constante `PHONE_ATTRIBUTE` e o join em `customer_entity_varchar` por esse atributo fantasma.
2. Buscar telefone via `customer_address_entity.telephone` pelo `default_billing` (mesmo padrão da correção 1.1), tanto em `getOptedInCustomersWithOrders()` quanto em `getHighValueInactiveCustomers()`.
3. `optin` já está correto (usa `customer_entity_int`) — não mexer.

**Validação:**
```bash
sudo -u www-data php bin/magento cron:run --group default
tail -50 var/log/whatsapp_commerce.log | grep Retargeting
```
Confirmar que `[Retargeting] Completed` aponta `eligible > 0` quando existirem clientes elegíveis (testar com `retargeting/enabled=1` em ambiente de homologação antes de habilitar em produção).

### 1.3 — Robustez: `WhatsAppOptin::normalizePhone`

**Arquivo:** `Model/WhatsAppOptin.php`

**Correção:** adicionar fallback `?? ''` para alinhar com o restante do módulo:
```php
private function normalizePhone(string $phone): string
{
    return preg_replace('/\D/', '', $phone) ?? '';
}
```

**Validação:** `php -l app/code/GrupoAwamotos/WhatsAppCommerce/Model/WhatsAppOptin.php`

### 1.4 — `WhatsAppCart::addItem`: carrinho "zumbi" pós-checkout

**Arquivo:** `Model/WhatsAppCart.php`

**Correção:** antes de reaproveitar `$maskedId` cacheado, checar `$quote->getIsActive()` como já é feito em `createCart()`/`viewCart()`; se inativo, criar carrinho novo e sobrescrever o cache.

**Validação:** teste manual — finalizar um pedido via `checkout-link`, depois chamar `POST /V1/awa-whatsapp/cart/add` com o mesmo telefone e confirmar que um carrinho **novo** é criado (não o pedido antigo sendo reaberto).

**Checklist de saída da Fase 1:**
- [ ] `php -l` limpo em todos os arquivos alterados
- [ ] `setup:upgrade` sem erros
- [ ] `bin/magento cache:flush`
- [ ] Teste manual dos 4 itens acima
- [ ] `tail -50 var/log/exception.log` sem novas entradas

---

## Fase 2 — Schema e deploy (risco de ambiente novo)

**Objetivo:** garantir que uma instalação nova (staging, DR, CI) recrie a tabela `awa_whatsapp_consent_log` com os mesmos índices/FK do ambiente atual.

**Risco:** médio — mexe em declarative schema, exige cuidado para não gerar diff destrutivo em produção.

### 2.1 — Sincronizar `db_schema_whitelist.json`

**Causa raiz:** `db_schema.xml` foi editado (índices renomeados para `AWA_WPP_CONSENT_CUSTOMER`, `AWA_WPP_CONSENT_PHONE`, `AWA_WPP_CONSENT_CUSTOMER_FK`) depois que o whitelist foi gerado — o whitelist ainda tem os nomes antigos (`AWA_WHATSAPP_CONSENT_LOG_*`), que são os nomes realmente aplicados no banco hoje.

**Duas opções — escolher UMA:**

**Opção A (recomendada, zero downtime):** reverter os `referenceId` no `db_schema.xml` para os nomes que já existem no banco (`AWA_WHATSAPP_CONSENT_LOG_CUSTOMER_ID`, `AWA_WHATSAPP_CONSENT_LOG_PHONE`, `AWA_WHATSAPP_CONSENT_LOG_CUSTOMER_ID_CUSTOMER_ENTITY_ENTITY_ID`). Não precisa alterar o banco, só o XML volta a refletir a realidade.

**Opção B:** manter os nomes novos no XML e regenerar o whitelist:
```bash
sudo -u www-data php bin/magento setup:db-declaration:generate-whitelist --module-name=GrupoAwamotos_WhatsAppCommerce
```
Isso fará o Magento **renomear** os índices/FK em produção no próximo `setup:upgrade` (DROP + CREATE) — testar antes em homologação com uma cópia do banco.

**Validação:**
```bash
sudo -u www-data php bin/magento setup:upgrade
mysql -e "SHOW INDEX FROM awa_whatsapp_consent_log;" magento
mysql -e "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='awa_whatsapp_consent_log' AND REFERENCED_TABLE_NAME IS NOT NULL;" magento
```

**Checklist de saída da Fase 2:**
- [ ] `setup:upgrade` roda sem diff pendente (`bin/magento setup:db-declaration:generate-whitelist --dry-run` se disponível, ou revisar log)
- [ ] Índices/FK conferem entre XML, whitelist e banco real

---

## Fase 3 — Refatoração: eliminar duplicação de `findCustomerByPhone`

**Objetivo:** consolidar as ~6 implementações quase idênticas de busca de cliente por telefone em um único serviço, reduzindo risco de bugs futuros por correção parcial.

**Risco:** médio-alto — toca em 6 classes que já estão em produção; exigir cobertura de teste manual de cada fluxo antes/depois.

### 3.1 — Criar serviço único

Criar `Model/CustomerPhoneLocator.php`:
```php
class CustomerPhoneLocator
{
    public function findByPhone(string $phone): ?CustomerInterface { ... }
}
```
Consolidar a melhor versão de cada regra hoje espalhada:
- Normalização de DDI (55) com limiar único e testado (hoje varia entre 12 e 13 dígitos nos diferentes arquivos — definir o correto e documentar por quê).
- Busca em `customer_address_entity.telephone` (via `REGEXP_REPLACE`, mais legível que a cascata de `REPLACE` aninhado usada em 4 arquivos).
- Fallback em `b2b_phone` (atributo EAV do customer).

### 3.2 — Migrar consumidores

Substituir os métodos privados duplicados por injeção do novo serviço em:
- `Model/WhatsAppOptin.php`
- `Model/WhatsAppAttendant.php` (manter a lógica adicional de ERP/round-robin, só trocar a etapa 1)
- `Model/WhatsAppB2BRegistration.php`
- `Model/WhatsAppB2BQuote.php`
- `Model/WhatsAppB2BReorder.php`
- `Model/WhatsAppReview.php`

**Validação:** repetir manualmente os testes de: opt-in por telefone, atendimento (attendant), cadastro B2B, cotação B2B (submit/list/detail/accept), recompra B2B, review via WhatsApp — comparando resposta antes/depois para os mesmos números de telefone de teste.

**Checklist de saída da Fase 3:**
- [ ] Nenhuma classe listada acima ainda tem `findCustomerByPhone` privado duplicado
- [ ] Todos os fluxos testados manualmente sem mudança de comportamento observável
- [ ] `php -l` limpo em todos os arquivos tocados

---

## Fase 4 — Segurança: segregação de ACL

**Objetivo:** impedir que um único token de integração tenha acesso simultâneo a rotas públicas do bot, rotas administrativas e disparo de campanhas.

**Risco:** alto — muda contrato de autorização; exige coordenar rotação de token com quem opera o bot (Typebot/n8n) para não causar downtime do atendimento via WhatsApp.

### 4.1 — Novos recursos ACL

**Arquivo:** `etc/acl.xml`
```xml
<resource id="GrupoAwamotos_WhatsAppCommerce::api_public" title="WhatsApp Commerce API - Público (bot)" sortOrder="102"/>
<resource id="GrupoAwamotos_WhatsAppCommerce::api_admin" title="WhatsApp Commerce API - Admin Dashboard" sortOrder="103"/>
<resource id="GrupoAwamotos_WhatsAppCommerce::api_campaign" title="WhatsApp Commerce API - Campanhas" sortOrder="104"/>
```
Manter `::api` como resource pai/legado só até a migração completa (ou remover direto se aceitável quebrar compatibilidade).

### 4.2 — Reclassificar rotas em `etc/webapi.xml`

| Grupo | Rotas |
|---|---|
| `api_public` | catalog/*, cart/*, tracking/*, attendant/*, optin/*, b2b/validate-cnpj, b2b/register, b2b/status, b2b/quote/* (exceto accept), b2b/reorder*, review |
| `api_admin` | admin/sales-today, admin/stock/:sku, admin/new-customers, admin/order/:incrementId, admin/top-selling, health |
| `api_campaign` | campaign/broadcast, campaign/segments |

Discutir com o time se `b2b/quote/accept` (cria pedido real) deveria ter um recurso próprio (`api_public_write` vs `api_public_read`), já que tem efeito colateral financeiro maior que os demais endpoints públicos.

### 4.3 — Migração operacional

1. Criar **duas novas integrações** no admin: uma para o bot (escopo `api_public`) e outra para automações internas de admin/marketing (`api_admin` + `api_campaign`), se esses fluxos existirem fora do admin do Magento.
2. Atualizar a configuração do bot (Typebot/backend do WhatsApp) com o novo token de escopo restrito.
3. Revogar/desativar a integração antiga "WhatsApp Commerce" só depois de confirmar que o bot está usando o novo token em produção por pelo menos alguns dias sem erro 403.

**Validação:**
```bash
# com token do bot (api_public) — deve falhar:
curl -H "Authorization: Bearer <token_bot>" https://awamotos.com/rest/V1/awa-whatsapp/admin/sales-today
# esperado: 401/403

# com token do bot — deve funcionar:
curl -H "Authorization: Bearer <token_bot>" https://awamotos.com/rest/V1/awa-whatsapp/catalog/search?q=pastilha
```

**Checklist de saída da Fase 4:**
- [ ] Rotas administrativas retornam 403 para o token do bot
- [ ] Rotas públicas continuam funcionando para o token do bot
- [ ] Time de atendimento avisado antes da rotação de token (evitar downtime do bot)
- [ ] Integração antiga desativada só após confirmação em produção

### 4.4 (opcional, complementar) — Allowlist de IP

Se o backend do bot roda em IP fixo (ex.: VPS do Typebot/Evolution API), considerar bloquear `/rest/V1/awa-whatsapp/*` por IP de origem no nginx, como camada adicional além do token — reduz o impacto de um vazamento de token isolado.

---

## Ordem recomendada de execução

```
Fase 1 (bugs funcionais) → Fase 2 (schema) → Fase 3 (refactor) → Fase 4 (ACL)
```

Fases 1 e 2 podem ser feitas na mesma janela de deploy. Fase 3 deve ser uma janela dedicada com testes manuais de regressão. Fase 4 exige coordenação com quem opera o bot de WhatsApp e deve ser planejada com antecedência (não é um "hotfix").

## Rollback

- **Fase 1:** reverter commit; nenhuma mudança de schema, rollback trivial.
- **Fase 2:** se optar pela Opção B (renomear no banco), ter backup do `awa_whatsapp_consent_log` antes do `setup:upgrade` (`mysqldump` da tabela).
- **Fase 3:** reverter commit; comportamento externo (API) não muda, só a implementação interna.
- **Fase 4:** manter a integração antiga ativa em paralelo até confirmar a nova funcionando; rollback = reverter o bot para o token antigo enquanto `::api` legado ainda existir.
