# Registro de lacunas

Status: `corrigido` = na branch isolada, **não implantado**.

| ID | Área | Cenário | Esperado | Atual (produção HEAD) | Sev | Evidência | Causa raiz | Arquivos | Correção | Status | Teste | Commit |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| G01 | Preço/API | Guest GraphQL `products.price_range` | Sem valor comercial | `F340` retornou `22.99` | P0 | curl `/graphql` 2026-08-24 | Sem plugin GraphQL B2B | `Plugin/GraphQl/*`, `etc/graphql/di.xml` | Redigir price_range/price/special_price | corrigido | `HideGraphQlPricePluginsTest` | temático preço |
| G02 | Preço/HTML | Exceção em `canViewPrices` | Ocultar | Fail-open devolvia HTML do preço | P0 | `HidePricePlugin` / `HideFinalPricePlugin` | Comentário fail-open | plugins HTML | Fail-closed + mensagem segura | corrigido | `HidePricePluginTest`, `HideFinalPricePluginTest` | temático preço |
| G03 | Preço | Logado sem `b2b_approval_status` | Não ver tabela | Tratava como aprovado | P0 | `PriceVisibility` L120-125 | Compatibilidade fail-open | `PriceVisibility.php` | Fail-closed | corrigido | `PriceVisibilityTest` | temático preço |
| G04 | SEO | JSON-LD Product.offers.price | Sem preço se guest | Incluía `finalPrice > 0` | P1 | `SchemaOrg/Block/ProductSchema.php` | Sem checagem PriceVisibility | plugin SchemaOrg | Unset price | corrigido | `HideProductSchemaPricePluginTest` | temático preço |
| G05 | Admin | Solicitar informações | Transição + histórico | `RequestReview` chama `requestDataReview()` inexistente | P0 | grep sem `function requestDataReview` | Método nunca implementado | `CustomerApproval.php` + interface | Implementar + evento | corrigido | `CustomerApprovalTest` | temático admin |
| G06 | Cadastro | Protocolo legível | Exibir protocolo | Só mensagem genérica | P1 | `success.phtml` | Não gerava protocolo | Save + Success block | `AWA-YYYYMMDD-#####` | corrigido | manual/success template | temático cadastro |
| G07 | Cadastro | Aceites | Termos, privacidade e WhatsApp separados | Um checkbox termos+privacidade; sem WhatsApp | P1 | HTML `/b2b/register/` | Template único | form.phtml + Save | Checkboxes + validação server | corrigido | validação Save | temático cadastro |
| G08 | Cadastro | IE isento | IE ou isento | IE não obrigatória no server | P1 | `validateData()` | Faltava regra | Save.php | Exigir IE ou ISENTO | corrigido | validação Save | temático cadastro |
| G09 | Cadastro | Duplo envio | Um cadastro | Só lock JS | P1 | Save sem fingerprint | Sem idempotência server | Save.php + token hidden | Token sessão + fingerprint 1h | corrigido | código Save | temático cadastro |
| G10 | Testes | Playwright vs produção | TARGET_ENV + bloqueio escrita | Só `ALLOW_PRODUCTION_VALIDATION` | P1 | `resolve-base-url.ts` | Flag única | helpers Playwright + CI | TARGET_ENV obrigatório | corrigido | guard node + spec | temático testes |
| G11 | IA | Cadastro campo a campo | Mensagens por etapa | Coach genérico guest | P2 | `guided-coach.js` | Sem cenário register | guided-coach.js | Mensagens etapas 1–4 | corrigido | revisão JS | temático IA |
| G12 | Piloto | Pedido real Magento→Sectra→NF-e | 1 pedido interno | Placeholders PILOT_* | P0 | briefing | Allowlist vazia | n/a | Não executar | bloqueado | n/a | n/a |
| G13 | Notificações | Fila idempotente | Queue + retry | E-mail síncrono em Save/CustomerApproval | P2 | `sendConfirmationEmail` no controller | Observer pesado + transport direto | — | Documentado; não refatorado nesta onda | aberto | — | — |
| G14 | Estados | under_review / blocked | Nomes do briefing | data_review / suspended | P2 | EAV produção | Vocabulário já em uso | ApprovalStatus aliases | Aliases sem migração | mitigado | — | temático admin |
| G15 | Staging | Testes de escrita | Ambiente não prod | Inexistente neste host | P1 | hostname + base_url | Topologia single-prod | — | Worktree só código | bloqueado | — | — |

Smoke produção (somente GET, sem PII):

- Home 200, 0 `R$` visível, CTA `/b2b/register` presente, gate `b2b-login-to-see-price`.
- Categoria bauletos 200, 51 gates, 0 `R$`.
- `/b2b/register/` 200, 4 etapas, `ie_isento` sim, `whatsapp_consent` não (antes da correção).
- GraphQL guest vazou preço (G01).
- REST `/rest/V1/products/F340` exige ACL de catálogo (não vazou por REST anônimo).
