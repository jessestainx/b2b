# Etapa 1.5 — Plano de Homologação e Fechamento do Gap Funcional

**Status geral:** NO-GO em produção — **mantido**
**Host analisado:** `awamotos.com` (`72.61.94.22`) — produção; leituras apenas
**Commit de referência:** `37c082192` (`rescue/production-20260712`)
**Branch de proposta (somente código/navegação):** `feature/admin-menu-unification`  
**Worktree (fora do docroot de produção):** `/home/jessessh/worktrees/feature-admin-menu-unification`
**Data:** 2026-07-22  
**Atualização:** decisão humana A-PREP + correções de relatório (mesma data)

---

## 0. Decisão humana registrada (obrigatória)

### 0.1 Gap do atendente → **A-PREP TEMPORÁRIO**

- `grupoawamotos_b2b/attendant/dashboard` **não** será descontinuada agora.
- Em branch (depois HML): item de menu no unificado:
  - **Título:** Meu Desempenho
  - **Action:** `grupoawamotos_b2b/attendant/dashboard`
  - **ACL:** `GrupoAwamotos_B2B::attendant_self`
  - **Somente navegação** — sem alterar controller, template, service, KPI ou ACL existente.
- Parent escolhido após auditoria de ACL (ver §2.5): `Magento_Backend::admin`  
  (não `GrupoAwamotos_B2B::platform`, que exige `::commercial`).

### 0.2 Homologação

| Decisão | Status |
|---|---|
| Arquitetura em **VM/host separado** | **Aprovada** |
| Instalação no **mesmo VPS** da produção | **Não aprovada** |
| Recurso pago | **Proibido** sem aprovação de custo **e** sem hostname/IP HML formal |

Isolamento obrigatório HML: docroot próprio; banco próprio; PHP-FPM pool próprio; file-system owner próprio; Redis/Valkey próprio; OpenSearch próprio ou credencial estritamente limitada; RabbitMQ/vhost próprio se aplicável; mail catcher; cron inicialmente off; consumers off; bloqueio de egress (ERP, WhatsApp, pagamentos, SMTP real, webhooks); Auth HTTP / VPN / allowlist; Admin exclusivo + 2FA; noindex; logs próprios.

### 0.3 Produção

**NO-GO.** Não alterar configuração, código, menu, cache, banco, cron, filas, usuários, permissões, integrações, branch ou filesystem de produção (docroot vivo).

Após HML verde, apresentar plano separado em **duas janelas** (sem executar):

1. **Janela 1:** deploy somente do item Meu Desempenho (legado ainda visível).
2. **Janela 2:** após validação humana, `legacy_menu_visible=0`.

---

## 1. CLI / ownership (corrigido)

### Evidências

| Path | Owner:Group | Papel |
|---|---|---|
| Código (`bin/magento`, `app/`, etc.) | **`jessessh:www-data`** | **Provável file-system owner** do ambiente atual |
| `var/` | `www-data:www-data` | Runtime Magento (cache, log, session files) |
| PHP-FPM 8.4 | **`www-data`** | Usuário de **runtime** |
| Crontab Magento | **`www-data`** | Runtime |

### Regras

| Ambiente | Regra |
|---|---|
| Produção (atual) | **Não alterar permissões.** `jessessh` = FS owner do código; `www-data` = PHP-FPM/runtime. Não declarar `www-data` genericamente como file-system owner. |
| HML | Criar **usuário file-system owner dedicado** (não reutilizar o de produção). Comandos manuais Magento na HML devem rodar **como o FS owner da HML** (alinhado ao pool FPM da HML). |

---

## 2. Gap — `grupoawamotos_b2b/attendant/dashboard`

### Identificação técnica

| Item | Valor |
|---|---|
| Action | `grupoawamotos_b2b/attendant/dashboard` |
| Controller | `GrupoAwamotos\B2B\Controller\Adminhtml\Attendant\Dashboard` |
| ACL | `GrupoAwamotos_B2B::attendant_self` |
| Menu clássico | `menu.xml` → “Meu Painel” sob `GrupoAwamotos_B2B::b2b` |
| Menu unificado (proposta A-PREP) | `menu_platform.xml` → **Meu Desempenho** (`platform_meu_desempenho`) |

### Finalidade vs Painel Comercial

São telas **diferentes**. Startup (`AttendantStartupPagePlugin`) já redireciona atendentes para o Painel Comercial.

### Evidências de uso e `ui_bookmark` (corrigido)

| Fonte | Interpretação |
|---|---|
| **`ui_bookmark`** | **Não representa contagem de acessos.** Armazena estado de grids (filtros, ordenação, colunas, paginação, visualizações salvas). Contagens nessa tabela são no máximo sinal fraco de que alguém interagiu com um grid em algum momento. |
| Access logs / system.log | Sinais fracos / nulos no recorte analisado |

### Classificação do uso histórico

## **C) INCONCLUSIVO** — mantida

Produto distinto ainda no código; evidência de uso recente fraca; persona de atendente linkado existe.

### 2.5 Escolha do parent (ACL)

| Parent candidato | ACL do parent | Role **Atendente** (`attendant_self` + `b2b`, sem `commercial`) | Role **AWA Comercial Vendedora** (8 atendentes linkados ativos) |
|---|---|---|---|
| `GrupoAwamotos_B2B::platform` | `::commercial` | **Bloqueado** (não vê a árvore B2B) | Vê a árvore, mas **não** tem `attendant_self` → não veria o item |
| `Magento_Backend::admin` | admin raiz | **Visível** se tiver `attendant_self` | Sem `attendant_self` → item oculto (correto) |

**Decisão de navegação:** parent = `Magento_Backend::admin`, id = `GrupoAwamotos_B2B::platform_meu_desempenho` (prefixo `platform_` para permanecer sob o gate `unified_menu_enabled` do `PlatformMenuVisibilityPlugin`), resource = `::attendant_self`.

Nota operacional: os 8 atendentes ativos linkados estão na role Vendedora **sem** `attendant_self`; o item só aparece para usuários de fato autorizados nessa ACL (ex.: roles Atendente/Mkt ou `Magento_Backend::all`).

---

## 3. Homologação — arquitetura aprovada (host separado)

Preferência humana: **VM/host separado**. Co-localizar no VPS de `awamotos.com` = **rejeitado**.

Checklist (resumo): docroot, DB, FPM, FS owner, Redis/Valkey, OpenSearch, Rabbit (se houver), mailcatcher, cron/consumers off, bloqueio de egress, Auth/VPN/allowlist, Admin HML + 2FA, noindex, logs próprios.

**Não provisionar** recurso pago sem custo aprovado + hostname/IP HML formal.

### 3.1 Banco e chave criptográfica (decisão humana)

No **primeiro boot** da cópia do banco:

1. Manter a crypt/key **correspondente** ao dump — somente em **secret store** protegido.
2. **Não** gravar a chave no repositório.
3. **Não** registrar a chave neste relatório.
4. Bloquear integrações externas **antes** do boot.
5. Substituir credenciais reais; sanitizar dados; desativar usuários copiados.
6. **Depois** rotacionar a chave **somente na HML** pelo procedimento Magento suportado.
7. **Não** criar crypt/key nova imediatamente após importar o banco.

### 3.2 Inventário prévio — configs criptografadas (módulos custom `GrupoAwamotos`)

Paths `core_config_data` com `backend_model` Encrypted (sem valores):

| Path |
|---|
| `grupoawamotos_b2b/whatsapp/api_key` |
| `grupoawamotos_b2b/whatsapp/client_token` |
| `grupoawamotos_erp/connection/password` |
| `grupoawamotos_erp/write_connection/password` |
| `grupoawamotos_erp/whatsapp/zapi_token` |
| `grupoawamotos_erp/whatsapp/zapi_client_token` |
| `leadlovers/general/api_token` |
| `marketing_intelligence/prospect_api/api_token` |
| `marketing_intelligence/lead_ads/webhook_token` |
| `marketing_intelligence/meta_audiences/system_user_token` |
| `marketing_intelligence/competitors/ad_library_token` |
| `smart_suggestions/whatsapp/api_token` |
| `smart_suggestions/whatsapp/zapi_token` |
| `smart_suggestions/whatsapp/zapi_client_token` |
| `whatsapp_commerce/app_builder_offload/shared_secret` |
| `whatsapp_commerce/meta_description/groq_api_key` |

Além disso: `crypt/key` em `env.php` (segredo de deployment — não listar valor); hashes de senha Magento (`admin_user`, `customer_entity`) dependem do encryptor.

---

## 4. Matriz de versões (produção, somente leitura) × requisitos oficiais

**Edição Magento identificada:** Magento Open Source (**Community**) **`2.4.8-p3`**  
(`magento/product-community-edition` + `magento/magento2-base` = `2.4.8-p3`; CLI: `Magento CLI 2.4.8-p3`)

Referência de requisitos: [Adobe Commerce System Requirements](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements) (linha 2.4.8 / patches) e magento.watch 2.4.8-p3.

| Componente | Produção (coletado) | Oficial p/ 2.4.8-p3 (resumo) | Avaliação |
|---|---|---|---|
| Magento | Open Source **2.4.8-p3** | 2.4.8-p3 | Baseline HML |
| Patch level | **p3** | p3 | OK |
| PHP CLI | **8.4.17** | **8.3 ou 8.4** (para 2.4.8) | **Compatível** *após* identificar 2.4.8-p3 |
| PHP-FPM | **8.4.17** (fcgi) | idem | OK |
| Composer | **2.10.2** | 2.9.3+ / 2.10 (docs recentes) | OK |
| `composer.lock` sha256 | `78655729841eefd45e968fa27052eadb7026a003086b0a8038143dcfe5c6d565` | — | Pin HML neste lock |
| `app/etc/config.php` sha256 | `2a62390eb443a294398eaa5b5af648be94f29d316af078b0cd16d737ef1276a8` | — | Comparar no bootstrap HML |
| MySQL | **Percona/MySQL 8.4.7-7** | MySQL **8.4** | OK |
| Search | **Elasticsearch 7.17.29** (cluster name `elasticsearch`, node `awamotos.com`) | OpenSearch **2/3** (docs 2.4.8); ES 8 em algumas matrizes recentes | **Desvio** — HML deve espelhar prod *ou* planejar migração documentada; não assumir OpenSearch 3 sem decisão |
| Redis | **7.0.15** standalone (`::1`) | Redis 7.2 / Valkey 8 (matrizes Adobe variam) | **Atenção** — 7.0.15 < 7.2 tipicamente listado; espelhar prod na HML até decisão |
| RabbitMQ | **Não detectado** / sem `rabbitmqctl`; `env.php` sem brokers AMQP explícitos | RabbitMQ 4.x se filas AMQP | N/A se não usado |
| nginx | **1.28.0** | 1.28 (p3) / 1.30 (patches mais novos) | OK p/ espelho p3 |
| Extensões PHP CLI | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, mysqli, pdo_mysql, soap, sockets, sodium, xsl, zip, zlib, redis, imagick, opcache, … | Extensões Magento padrão | Inventário CLI coletado |
| Extensões PHP-FPM | Conjunto alinhado ao CLI (sem `pcntl` no FPM, esperado) | idem | OK |

**Regra aplicada:** não se concluiu compatibilidade de PHP 8.4 *a priori*; só após confirmar Magento **2.4.8-p3**, cuja matriz oficial lista PHP 8.3/8.4.

---

## 5. Guarda fail-fast (HML)

Script versionado na branch: `dev/hml/hml-env-guard.sh`

Abortar escrita se:

- hostname for `awamotos.com`;
- base URL contiver `awamotos.com` sem o subdomínio HML aprovado (`HML_APPROVED_HOST`);
- banco for o de produção (`magento` / lista `PROD_DB_NAMES`);
- Redis apontar DBs 0/1/2 de produção (salvo override explícito documentado);
- houver chaves `erp` / `sectra` / `whatsapp` / `smtp` / `payment` cruas em `env.php`.

Uso: `export HML_APPROVED_HOST=…` → `./dev/hml/hml-env-guard.sh` **antes** de qualquer `config:set` / `cache:clean` na HML.

---

## 6. Plano de teste na HML (ordem humana)

Pré-requisito: HML verde + branch com Meu Desempenho deployada + guard OK.

### Fase T1 — legado ainda visível

1. Confirmar `legacy_menu_visible=1`.
2. Validar item **Meu Desempenho** (ACL, URL, render).

### Fase T2 — ocultar legado

1. Confirmar `unified_menu_enabled=1` — **não regravar** se já for 1.
2. Registrar valor anterior de `legacy_menu_visible`.
3. Alterar **somente** `legacy_menu_visible` → `0`, scope **`default`** explícito.
4. `cache:clean config` apenas; `compiled_config` só se habilitado e necessário; **não** `cache:flush`.
5. Logout/login.
6. Validar personas, URL direta do painel, ACL, logs.
7. Restaurar valor anterior de `legacy_menu_visible`.
8. Comprovar retorno dos menus legados.

---

## 7. Branch e artefatos (sem tocar docroot vivo)

| Item | Valor |
|---|---|
| Base | `37c082192` |
| Branch | `feature/admin-menu-unification` |
| Worktree | `/home/jessessh/worktrees/feature-admin-menu-unification` |
| Mudança de código | somente `menu_platform.xml` (+ guard + este relatório) |
| Branch `rescue/production-20260712` | **não editada** para o menu |
| Docroot produção | **sem** alteração de menu/código Magento |

---

## 8. Plano produtivo futuro (somente após HML verde — não executar)

### Janela 1

- Deploy do item **Meu Desempenho** (branch).
- Manter `legacy_menu_visible=1`.
- Validação humana do item com legado ainda visível.

### Janela 2

- Após aceite humano: `legacy_menu_visible=0` (scope default), `cache:clean config`, logout/login, matriz de personas, rollback ensaiado.

**Parar antes de qualquer execução em produção.**

---

## 9. Declaração de não execução (produção)

Nenhuma alteração feita em produção neste ciclo:

- flags Magento / cache / banco / cron / filas / integrações / usuários / permissões
- código ou menu no **docroot** `/home/jessessh/htdocs/srv1113343.hstgr.cloud`
- crypt/key (valor não lido para este relatório)

Alterações versionadas apenas no worktree da branch `feature/admin-menu-unification`.

**Status: NO-GO em produção — mantido.**

---

## 10. Continuação (2026-07-22) — artefatos e bloqueio

Próximo passo **bloqueado** por decisão humana de hostname/IP + custo.

| Documento | Função |
|---|---|
| `ETAPA_1_5_GATE_HML_APROVACAO_2026-07-22.md` | Gate obrigatório antes de qualquer provisionamento |
| `ETAPA_1_5_HML_RUNBOOK_PROVISIONAMENTO_2026-07-22.md` | Runbook HML (não executar sem gate) |
| `ETAPA_1_5_JANELAS_PRODUCAO_DRAFT_2026-07-22.md` | Plano Janela 1/2 produção (rascunho; NO-GO) |
| `dev/hml/hml-env-guard.sh` | Fail-fast pré-escrita |
| `dev/hml/hml-test-menu-flags.sh` | Roteiro T1→T2→rollback (só HML) |

Catálogo/preços Hostinger via MCP: autenticação de sessão incompleta no momento da coleta (`Unauthenticated` após `mcp_auth`). **Não** foi criado VPS. Preencher custo manualmente no gate (hPanel) ou reautenticar MCP e recolocar preços.

**Ação humana imediata:** preencher o gate (hostname/IP/SKU/custo) e devolver GO de orçamento.
