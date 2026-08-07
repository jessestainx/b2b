# Plano de correção por fases — Cascata CSS do storefront

**Projeto:** AWA Motos — Magento 2
**Tema:** `AWA_Custom/ayo_home5_child`
**Ambiente:** Produção
**Criado em:** 2026-08-06
**Atualizado em:** 2026-08-07
**Branch de referência:** `rescue/production-20260712`
**Commit de referência:** `37c0821926941ab12a41c5b49736634753451521`

### Progresso

| Fase | Título | Estado |
|------|--------|--------|
| 0 | Estabilização visual e baseline | CONCLUÍDA |
| 1 | Versionamento e publicação dos bundles | CONCLUÍDA |
| 2 | Drift do `awa-super-global` | CONCLUÍDA |
| 3 | CLS tardio da home | CONCLUÍDA |
| 4 | Testes de contrato da cascata | CONCLUÍDA |
| 5 | Builds e cache-busting | CONCLUÍDA |
| 6 | Pipeline Magento (layout/PageConfig) | EM ANDAMENTO (fatia 1) |
| 7 | LESS autoridade visual canônica | PENDENTE |
| 8 | Consolidar footer | PENDENTE |
| 9 | Remover CSS home inline | PENDENTE |
| 10 | Reduzir `align-grid` / `!important` | PENDENTE |
| 11 | Limpar assets mortos e governança | PENDENTE |
| 12 | Git, ownership e permissões | PENDENTE |
| 13 | Retirar locks de compatibilidade | PENDENTE |
| 14 | Auditoria final e encerramento | PENDENTE |

**Resumo CWV (home, lab 2026-08-07):** CLS desktop `0,037` · CLS mobile `0,032` (orçamento ≤ `0,1`).

## 1. Objetivo

Migrar a cascata atual para uma arquitetura Magento 2 previsível, versionada,
testável e reversível, mantendo:

- superfícies brancas, sem tonalidade rosada;
- header com 116 px no desktop e 96 px no mobile;
- fluxo B2B guest/customer;
- avaliações ocultas para visitantes;
- footer estável;
- URLs estáticas imutáveis;
- LCP, CLS e TBT dentro dos orçamentos definidos no projeto.

Este documento é um plano operacional. Ele **não autoriza alterações em
produção**. Cada fase que envolva escrita exige diagnóstico, diff proposto,
backup, rollback e autorização explícita.

## 2. Regra para atualizar os estados

Estados permitidos:

- `[x] CONCLUÍDA`: todos os critérios de aceite foram validados e as evidências
  foram registradas.
- `[ ] EM ANDAMENTO`: mudança autorizada e execução iniciada.
- `[ ] PENDENTE`: ainda não iniciada.
- `[ ] BLOQUEADA`: depende de decisão, acesso ou correção anterior.

Uma fase só pode ser marcada como concluída quando:

- [ ] todas as tarefas da fase estiverem concluídas;
- [ ] hashes e arquivos publicados estiverem registrados;
- [ ] não houver escrita concorrente;
- [ ] testes funcionais e visuais passarem;
- [ ] logs não apresentarem nova exceção relacionada;
- [ ] rollback estiver disponível;
- [ ] revisão final não tiver achados pendentes.

Ao concluir uma fase, substituir `[ ]` por `[x]`, alterar o estado para
`CONCLUÍDA` e preencher o registro de evidências da própria fase.

## 3. Guardrails globais

- Nunca editar `vendor/`, core Magento ou `app/code/Rokanthemes/`.
- Nunca usar `pub/static` como fonte.
- Alterar fontes somente no tema filho ou em `app/code/GrupoAwamotos/`.
- Publicar CSS pelo `setup:static-content:deploy` direcionado ao tema.
- Gerar `.br` e `.gz` somente depois do deploy.
- Validar que Brotli e Gzip descomprimem byte a byte para o asset publicado.
- Usar tokens `var(--awa-*)`; não introduzir novos hexadecimais sem justificativa.
- Não remover `!important` em massa.
- Não limpar Redis, Varnish, caches ou reiniciar serviços sem autorização.
- Não avançar uma fase quando LCP, CLS, B2B ou navegação por teclado regredirem.

---

## [x] Fase 0 — Estabilização visual e baseline — CONCLUÍDA

### Escopo entregue

- [x] baseline forense, visual e de CWV capturado;
- [x] superfícies rosadas consolidadas para branco;
- [x] avaliações guest removidas do autocomplete e ocultadas no storefront;
- [x] contratos do header confirmados em 116 px desktop e 96 px mobile;
- [x] autocomplete, PLP, PDP, carrinho e login B2B validados;
- [x] fallback `<noscript>` do bundle home restaurado;
- [x] lock cross-page removido de páginas sem `.awa-site-header`;
- [x] PHP, PHTML, CSS, rede e logs validados;
- [x] revisão independente concluída sem achados pendentes.

### Evidências

- Header desktop: `116px`.
- Header mobile: `96px`.
- Fundo: `rgb(255, 255, 255)`.
- Avaliações visíveis para guest: `0`.
- Rotas críticas: HTTP `200`.
- Exceções Magento novas relacionadas: `0`.

### Rollback

Backups externos em:

`/var/backups/awa-css-cascade-20260806T173600Z`

---

## [x] Fase 1 — Versionamento e publicação dos bundles consolidados — CONCLUÍDA

### Escopo entregue

- [x] nomes e queries dos bundles principais centralizados;
- [x] `align-grid` publicado como `grid-contract-r3`;
- [x] `awa-m2-visual-ssot` publicado como autoridade visual;
- [x] footer, refine e home deferred regenerados a partir das fontes;
- [x] home deferred buildado de forma determinística e sem escrever diretamente
  em `pub/static`;
- [x] assets publicados em `pt_BR` e `en_US`;
- [x] Brotli e Gzip validados byte a byte;
- [x] resposta HTTP confirmada com `Cache-Control: immutable`.

### Hashes publicados

- `awa-align-grid-terminal-2026-06-11.min.css`:
  `696a4f424479052081d02ce47b55cc1ef797d2aa426b40369015ab54ab6e0197`
- `awa-m2-visual-ssot.min.css`:
  `f4e09118737d8ee3bfd2ebb55d8a4b45ddec63a0a22a7db91692705070814de9`
- `awa-footer-terminal-lock-v1.min.css`:
  `d72a7f03319214fed5e616832e72122c4810fec2112080e5a9865d8fb7065140`
- `awa-home-deferred-stack.min.css`:
  `6a3d93d97d6c2a077c4d31bc1e082c318e8cdc372653da6f91fc92ffeccde8cb`
- `awa-commerce-impeccable-refine.min.css`:
  `4a18e0d41a1fcb7faedfecabe68e274fc105e8644ecc36c57466011464937737`

---

## [x] Fase 2 — Reconciliar drift do `awa-super-global` — CONCLUÍDA

### Problema confirmado

O arquivo fonte e o asset servido possuíam conteúdo diferente:

- fonte (SSOT):
  `04033c253ee920e319d9d8228d2a69121dbc0b850d168313212469f71349ecbf`
- produção antiga `pt_BR`/`en_US`:
  `c82e9a280b5c2dba35e20502d04bdb6b17b7f8ed8a5a867eb008466a0180dadf`
- delta semântico principal: FAB `bottom:15px` → `bottom:84px` (+ tokens).

### Tarefas

- [x] comparar semanticamente as duas versões;
- [x] identificar SSOT = fonte do tema;
- [x] confirmar fonte CSS regenera o `.min` (`cleancss`);
- [x] definir `SUPER_GLOBAL_QUERY=?v=20260806-super-global-ssot-r1`;
- [x] ligar a query em `awa-head-preload.phtml`;
- [x] publicar via static content deploy (após mover stale de `pub/static`);
- [x] gerar e validar `.br`/`.gz` byte a byte;
- [x] comprovar igualdade fonte → publicado → HTTP (`04033c25…`);
- [x] revalidar HTML PLP/home após recuperação do OpenSearch (bloqueio infra 503).

### Critérios de aceite

- [x] fonte e `pub/static` com o mesmo SHA-256;
- [x] `pt_BR` e `en_US` idênticos;
- [x] URL nova com query imutável;
- [x] asset HTTP 200 com hash SSOT;
- [x] HTML de rotas críticas 200 (OpenSearch em `:9201`; falha anterior por nó indisponível transitório).

### Evidências (2026-08-06)

- Query ao vivo:
  `.../awa-super-global-20260611m.min.css?v=20260806-super-global-ssot-r1`
- Hash HTTP/pub/fonte: `04033c253ee920e319d9d8228d2a69121dbc0b850d168313212469f71349ecbf`
- Bottoms no CSS publicado: `[25px, 84px, 10px]` (sem `15px`)
- Backup: `/var/backups/awa-css-cascade-phase2-super-global-20260806T155500Z`

### Risco e rollback

Rollback byte a byte do backup externo + reverter query/`awa-head-preload.phtml`.
Nota: após `cache:clean full_page`, HTML falhou com `NoNodesAvailableException`
enquanto o OpenSearch reiniciava. Porta correta do Magento: `:9201` (não `:9200`).
Recuperado automaticamente; home/PLP/login HTTP 200 revalidados.

---

## [x] Fase 3 — Corrigir CLS tardio da home — CONCLUÍDA

### Problema confirmado (baseline)

CLS desktop (1366×900, 45s): `0,39721` (orçamento `≤ 0,1`).
CLS mobile (390×812, 25s): `0,09776` (próximo do orçamento).

Buckets desktop (baseline):
- early (&lt;2s): `0,22493`
- mid (2–10s): `0,12342`
- late (&gt;10s): `0,04885`

Fontes dominantes (baseline):
- `.awa-carousel--product` / `.awa-owl-nav` (maior shift `0,224` @ ~1651ms)
- `.awa-section-header__left` (altura 60→18)
- `#maincontent` / `.content-top-home` (deslocamento vertical)
- sticky header desktop estável em `116px` (não era a causa principal)

### Escopo entregue

- [x] capturar medições desktop 45s e mobile 25s;
- [x] registrar buckets early/mid/late e top sources;
- [x] alinhar critical CSS à geometria final dos carrosséis/headers de seção;
- [x] evitar tornar bundles de centenas de KB render-blocking;
- [x] estabilizar nav do carrossel sem colapsar para 0×0 após o paint
  (`data-awa-nav-anchor="viewport"` no SSR + faixa `height:0` no first-paint);
- [x] travar hero/banner (H12), `.page.messages` vazio (H13) e
  `#maincontent` padding-block (H14) no first-paint;
- [x] validar runs desktop + mobile após o patch;
- [x] revalidar pós-patch (2026-08-07) sem instrumentação de debug.

### Critérios de aceite

- [x] CLS home `≤ 0,1` em execuções pós-fix;
- [x] sticky header 116 px desktop / 96 px mobile preservado;
- [x] carrosséis com nav SSR acessível (`data-awa-nav-anchor="viewport"`);
- [x] instrumentação de debug removida.

### Evidências finais

- Baseline desktop CLS: `0,397` → pós-fix lab: `~0,025`.
- Revalidação 2026-08-07 (browser lab):
  - desktop 1366×900 / 45s: **CLS `0,037`**;
  - mobile 390×812 / 25s: **CLS `0,032`**.
- Sticky: `116px` / `96px`.
- Home HTTP `200`.

### Arquivos alterados (Fase 3)

- `app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-hero-preload.phtml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-shelf-carousel-chrome.phtml`

### Rollback

Backups externos:

- `/var/backups/awa-css-cascade-phase3-cls-20260806T193710Z`
- `/var/backups/awa-css-cascade-phase3-h12-20260806T225620Z`

```text
Fase: 3
Status: CONCLUÍDA
Data/hora: 2026-08-07
Responsável: agente Cursor (continuidade pós-lab)
Autorização: continuar aonde parou (encerrar Fase 3 no plano)
Arquivos alterados: OptimizeHeadStylesPlugin.php; awa-head-preload.phtml;
  awa-hero-preload.phtml; awa-shelf-carousel-chrome.phtml
Backup: /var/backups/awa-css-cascade-phase3-cls-20260806T193710Z ;
  /var/backups/awa-css-cascade-phase3-h12-20260806T225620Z
Desktop CLS: 0,037 (revalidação)
Mobile CLS: 0,032 (revalidação)
Observações: plano sincronizado com evidência runtime; Fase 4 também CONCLUÍDA.
```

---

## [x] Fase 4 — Criar testes de contrato da cascata — CONCLUÍDA

### Escopo entregue

- [x] testar que fragmentos não aparecem em listas de destino incompatíveis
  (auth: refine/promax/plp-polish = 0);
- [x] testar geração do HTML do `OptimizeHeadStylesPlugin` (unit PHP:
  strip, SSOT único, normalize align-grid, constantes gate/auth);
- [x] testar exatamente uma ocorrência de align-grid e visual SSOT
  (home 390/768/1366/1920 + PLP/PDP);
- [x] testar exatamente uma requisição do footer terminal;
- [x] testar fallback com JavaScript desativado (noscript deferred-stack);
- [x] cobertura guest `customer-data` (bundle na rede; `section/load` permanece
  idle-deferred na home — documentado no spec);
- [x] B2B login/dashboard/pedidos/cotações: mantido via `test:b2b-regression`
  no smoke CI (sem duplicar suite);
- [x] viewports 390 / 768 / 1366 / **1920** (`desktop-1920` no Playwright;
  contrato DOM nesses breakpoints — baselines PNG 1920 ficam para
  `--update-snapshots` em staging);
- [x] Lighthouse: LCP ≤ 2800 ms, TBT ≤ 300 ms, CLS ≤ 0,1 (desktop + mobile);
- [x] CI smoke sem produção como padrão (`vars.E2E_BASE_URL` + guard).

### Critérios de aceite

- [x] testes executáveis (`npm run test:cascade-contract` + PHPUnit);
- [x] falha do contrato entra no `e2e-pr-smoke` (bloqueia PR quando BASE_URL
  configurada);
- [x] produção não é alvo padrão (`resolveBaseUrl` + guard do workflow);
- [x] validação lab: Playwright **9 passed** (notebook-1366, opt-in prod);
  PHPUnit **7 passed**.

### Arquivos

- `tests/e2e/specs/css-cascade-contract.spec.ts` (novo)
- `tests/e2e/playwright.config.ts` (`desktop-1920`)
- `tests/e2e/package.json` (`test:cascade-contract`)
- `app/code/GrupoAwamotos/Theme/Test/Unit/Plugin/Response/OptimizeHeadStylesPluginTest.php` (novo)
- `.github/lighthouse/lighthouserc.desktop.cjs`
- `.github/lighthouse/lighthouserc.mobile.cjs`
- `.github/workflows/e2e-pr-smoke.yml`

### Rollback

Backup: `/home/deploy/backups/awa-css-cascade-phase4-tests-20260807T012700Z`

```text
Fase: 4
Status: CONCLUÍDA
Data/hora: 2026-08-07
Autorização: AUTORIZADO escrever Fase 4
PHPUnit: 7 passed
Playwright cascade (notebook-1366, opt-in prod): 9 passed / 36.4s
Observações: definir vars.E2E_BASE_URL (staging) no GitHub; produção só com
  vars.ALLOW_PRODUCTION_VALIDATION=true. Próxima = Fase 5.
```

### Pós-inspeção 2026-08-07 (profunda)

Inspeção forense home/PLP/B2B login + hashes fonte/pub/HTTP:

| Achado | Classificação | Ação |
|--------|---------------|------|
| Bundles SSOT fonte = pub = HTTP | CONFIRMADA OK | nenhuma |
| `.br`/`.gz` dos bundles principais byte-a-byte | CONFIRMADA OK | nenhuma |
| Duplos `link` critical/polish/deferred | REJEITADA (são `<noscript>` fallback) | nenhuma |
| mtime unmin > min (super-global/ssot) | REJEITADA (regen cleancss = mesmo hash) | nenhuma |
| exception.log vazio; HTTP 200 rotas críticas | CONFIRMADA OK | nenhuma |
| CLS/sticky/bg home lab estáveis | CONFIRMADA OK | nenhuma |
| `e2e-pr-smoke` falhava PR sem `E2E_BASE_URL` | CONFIRMADA (regressão Fase 4) | **corrigido**: skip seguro |
| Auth Magento `/customer/account/login` → `/b2b/account/login` | CONFIRMADA | e2e aponta B2B direto |
| `.br`/`.gz` dentro de `web/` do tema | DÍVIDA (Fase 5/11) | não patch agora |
| CSS density B2B ainda inline (~12 KB) | DÍVIDA (Fase 9) | não patch agora |

Patch mínimo aplicado:

- `.github/workflows/e2e-pr-smoke.yml` — skip quando `E2E_BASE_URL` ausente
- `tests/e2e/specs/css-cascade-contract.spec.ts` — URL auth B2B canônica

Validação: guard skip/block OK; Playwright subset **3 passed**.

Backup: `/home/deploy/backups/awa-css-cascade-phase4b-ci-skip-20260807T015000Z`

---

## [x] Fase 5 — Automatizar builds e cache-busting restantes — CONCLUÍDA

### Registro de evidências (Fase 5)
- Data: 2026-08-07
- Status: CONCLUÍDA
- Scripts: `build-awa-footer-terminal`, `build-awa-css-min-pair`, `build-awa-cascade-versions`,
  `build-awa-cascade-terminals`, `check-awa-static-sidecars`, `precompress-static --check`
- PHPUnit OptimizeHeadStylesPluginTest: 7 passed
- HTTP home: queries cascata = hash12; tokens manuais antigos = 0 hits
- Backup versions: `/home/deploy/backups/awa-css-cascade-phase5-versions-20260807T024500Z`


### Diagnóstico (somente leitura + build --check, 2026-08-07)

- `footerTerminalRules()` → cleancss -O1 ≡ disco `awa-footer-terminal-lock-v1.min.css`
  (sha256 `d72a7f0331…`, 106769 B) — **sem drift atual**.
- Lacuna **CONFIRMADA**: não existia build com `--check`/`--write` para o footer
  (risco de drift silencioso em edições futuras de `footerTerminalRules()`).
- Cascata SSOT: `?v=` = hash12; tema `web/` sem `.br`/`.gz`; precompress `--pub-only`/`--check`.

### Entrega parcial

- [x] `scripts/build-awa-footer-terminal.sh` (`--check` / `--write`) a partir de
  `footerTerminalRules()`.
- [x] `scripts/build-awa-css-min-pair.sh` — align-grid + refine (`cleancss -O1`).
- [x] `scripts/build-awa-cascade-terminals.sh` — orquestra footer + align-grid + refine.
- Evidência `--check` (2026-08-07): footer / align-grid / refine **match=true** (sem drift).
- [x] Removidos 40 sidecars `.br`/`.gz` de `web/` do tema (backup em
  `/home/deploy/backups/awa-css-cascade-phase5-theme-precompress-20260807T023800Z`).
- [x] `scripts/check-awa-static-sidecars.sh` + `precompress-static.sh --check`.
- [x] Queries cascata SSOT por hash (`CascadeAssetVersionConsts`); residual gate/PDP/minicart opcional.

### Tarefas

- [x] criar build determinístico do footer a partir de
  `footerTerminalRules()`;
- [x] criar build determinístico para align-grid e refine;
- [x] falhar o build quando fonte e minificado divergirem;
- [x] gerar versão baseada em conteúdo (`CascadeAssetVersionConsts`);
- [x] eliminar queries livres duplicadas (cascata SSOT → hash conteúdo);
- [x] usar `scripts/precompress-static.sh --pub-only` após o deploy;
- [x] validar sidecars e conteúdo HTTP automaticamente.

### Critérios de aceite

- [x] nenhum `.min.css` da cascata SSOT depende de cópia manual;
- [x] versões da cascata SSOT geradas num único lugar (build versions);
- [x] builds são idempotentes;
- [x] `--check` detecta artefato stale sem escrever.

---

## [ ] Fase 6 — Migrar decisões de assets para o pipeline Magento — EM ANDAMENTO

### Objetivo

Substituir pós-processamento de HTML por layout handles e PageConfig.

### Diagnóstico (somente leitura, 2026-08-07 — pós Fase 5)

- Plugin `OptimizeHeadStylesPlugin.php`: **8020** LOC, **187** `preg_replace`, **100** métodos,
  **8** calls a `stripStylesheetFragments`.
- Constantes de fragmento CSS relevantes: `HOME_GATE` (30), `HOME_DEFERRED_STACK` (15),
  `HOME_DEFER` (11), `NOROUTE_DEFER` (13), `AUTH_STRIP` (9), `CATALOG_*`, `CART_STRIP`, etc.
- Tema já tem **113** `<remove>` em 14 layouts; home (`cms_index_index.xml`) já remove 28 assets —
  mas gate/defer/strip da home ainda vivem no `beforeSendResponse`.
- 1ª migração candidata (menor risco mensurável): espelhar `HOME_GATE_CSS_FRAGMENTS` +
  remoções já cobertas por layout, com teste de paridade HTML, **sem** apagar regex até 2 medições.

### Fatia 1 — home gate/layout parity (AUTORIZADA 2026-08-07)

**Backup:** `/var/backups/awa-css-cascade-phase6-fatia1-20260807T123134Z`
**Status:** fatia 1 **CONCLUÍDA** (medições #1 e #2 OK; instrumentação debug removida).

#### Inventário live (baseline pré-deploy)

| Item | Valor |
|------|--------|
| HTML home | ~746 KB, HTTP 200 |
| `<link>` CSS | 24 |
| Fila `awa-css-gate-queue` | **6** URLs |
| Fragmentos HOME_GATE ausentes do HTML | 10+ (só safety-net) |

Fila gate ativa: `align-grid-terminal`, `impeccable-refine`, `custom_default.css`,
`login-to-cart`, `status-panel`, `awa-b2b-status-panel`.

#### Entregue

- [x] modelo `HomeCssGateParity` (`LAYOUT_REMOVE_SRCS` + `ACTIVE_GATE_FRAGMENTS` + `PRELOAD_SEED_FRAGMENTS`);
- [x] espelhar remoções PageConfig em `cms_index_index.xml` (19 `<remove>` de paridade);
- [x] seed explícito da fila gate em `awa-head-preload.phtml` (3 URLs B2B/L2C; align-grid/refine/custom_default via plugin);
- [x] testes PHPUnit de paridade layout + merge gate (OK — 10 tests);
- [x] instrumentação debug `f85cea` removida após medições #1 e #2;
- [x] medição HTML #1 pós-deploy: gate **6** bases únicas, ACTIVE sem missing;
- [x] medição HTML #2 (2026-08-07): `gateN=6`, `uniqueBases=6`, `cssLinks=24`, HTML 746108 B, sem dup align-grid;
- [x] **não** apagar `HOME_GATE_CSS_FRAGMENTS` / regex do plugin (permanece safety-net);

**Evidência runtime (m2):** `PARITY_OK` — mesmas 6 bases do baseline.
**Backup:** `/var/backups/awa-css-cascade-phase6-fatia1-20260807T123134Z`
**Nota:** OPcache/FPM precisou de `systemctl reload php8.4-fpm` na 1ª medição.

#### Arquivos alterados

- `app/code/GrupoAwamotos/Theme/Model/HomeCssGateParity.php` (novo)
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/layout/cms_index_index.xml`
- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml`
- `app/code/GrupoAwamotos/Theme/Plugin/Response/OptimizeHeadStylesPlugin.php` (log debug only)
- `app/code/GrupoAwamotos/Theme/Test/Unit/Plugin/Response/OptimizeHeadStylesPluginTest.php`

### Tarefas

- [x] inventariar cada regex que remove ou injeta `<link>`/`<style>` (home gate — fatia 1);
- [x] mapear cada regra para seu layout handle (home → `cms_index_index.xml`);
- [ ] migrar uma rota por vez;
- [x] mover home para `cms_index_index.xml` (fatia 1: remoções + seed; regex permanece);
- [ ] mover PLP para `catalog_category_view.xml`;
- [ ] mover busca para `catalogsearch_result_index.xml`;
- [ ] mover PDP para `catalog_product_view.xml`;
- [ ] preservar shells específicos de carrinho, checkout e B2B;
- [ ] remover regex somente após duas medições estáveis.

### Critérios de aceite

- [ ] output HTML equivalente antes da retirada do código antigo;
- [ ] nenhum link duplicado;
- [ ] nenhuma dependência de ordem acidental do `beforeSendResponse`;
- [ ] redução mensurável de métodos de pós-processamento.

---

## [ ] Fase 7 — Tornar LESS a autoridade visual canônica — PENDENTE

### Tarefas

- [ ] consolidar tokens em `_awa-variables.less`;
- [ ] migrar componentes estáveis para partials importados por `_extend.less`;
- [ ] eliminar fallback cromático duplicado;
- [ ] substituir hexadecimais por tokens;
- [ ] manter critical CSS apenas para geometria above-fold;
- [ ] documentar exceções que precisam permanecer standalone.

### Critérios de aceite

- [ ] tokens possuem uma única definição;
- [ ] alteração no LESS aparece após static deploy;
- [ ] PHP não contém regras visuais estáveis que pertencem ao tema;
- [ ] visual SSOT não depende de repetição de `#html-body`.

---

## [ ] Fase 8 — Consolidar footer — PENDENTE

### Problemas conhecidos

- regras divididas entre PHP, arquivo terminal e `footer.phtml`;
- JavaScript duplicado no template;
- possibilidade de sobreposição entre refine, terminal e patches finais;
- ausência de teste que detecte requisição duplicada.

### Tarefas

- [ ] criar teste de rede para o footer terminal;
- [ ] confirmar ou refutar duplo-fetch na home;
- [ ] extrair JavaScript duplicado do `footer.phtml`;
- [ ] remover CSS inline coberto pelo arquivo terminal;
- [ ] preservar critical anti-CLS;
- [ ] validar footer desktop/mobile e sem JavaScript.

### Critérios de aceite

- [ ] uma autoridade por propriedade do footer;
- [ ] uma requisição do stylesheet terminal;
- [ ] zero shift relevante quando o footer CSS entra;
- [ ] navegação e accordions acessíveis por teclado.

---

## [ ] Fase 9 — Remover CSS home retransmitido inline — PENDENTE

### Problema confirmado

`awa-home-b2b-ops-density-20260805.css` é injetado inline na home, aumentando o
HTML em aproximadamente 12 KB por resposta.

### Tarefas

- [ ] medir tamanho atual do HTML;
- [ ] gerar versão minificada externa;
- [ ] preservar a ordem terminal efetiva;
- [ ] evitar novo CLS ao externalizar;
- [ ] validar home em quatro viewports;
- [ ] comprovar redução do HTML.

### Critérios de aceite

- [ ] CSS não aparece inline;
- [ ] asset externo possui cache imutável;
- [ ] HTML reduzido;
- [ ] CLS não piora;
- [ ] espaçamento e densidade B2B permanecem idênticos.

---

## [ ] Fase 10 — Reduzir `align-grid`, especificidade e `!important` — PENDENTE

### Problema confirmado

O align-grid possui aproximadamente 900 KB de fonte e 696 KB minificado, além
de regras de múltiplos domínios visuais.

### Tarefas

- [ ] criar inventário por seção/componente;
- [ ] classificar cada `!important` como obrigatório, temporário ou removível;
- [ ] identificar regras repetidas;
- [ ] remover seletores com IDs repetidos quando a ordem da cascata for suficiente;
- [ ] mover regras visuais para os componentes corretos;
- [ ] manter no align-grid somente o contrato terminal de grid;
- [ ] medir bytes removidos por fase.

### Critérios de aceite

- [ ] redução mensurável do arquivo;
- [ ] ausência de hotfixes de cor/rating no align-grid;
- [ ] nenhuma regressão de layout;
- [ ] nenhum aumento de especificidade.

---

## [ ] Fase 11 — Limpar assets mortos e atualizar governança — PENDENTE

### Tarefas

- [ ] revalidar o inventário de CSS/JS sem referências;
- [ ] verificar shims que ainda podem estar em cache;
- [ ] remover backups `.bak.*` das fontes ativas;
- [ ] retirar bundles mortos dos scripts de build;
- [ ] atualizar `CSS_INVENTORY.md`;
- [ ] corrigir regras que recomendam cópia manual para `pub/static`;
- [ ] documentar static deploy como único fluxo de publicação;
- [ ] monitorar 404 de assets antes e depois.

### Critérios de aceite

- [ ] zero referência ativa para arquivos removidos;
- [ ] zero 404 novo;
- [ ] documentação corresponde ao pipeline real;
- [ ] `pub/static` permanece somente artefato.

---

## [ ] Fase 12 — Normalizar Git, ownership e permissões — PENDENTE

### Problemas confirmados

- worktree contém muitas alterações não relacionadas;
- arquivos relevantes possuem owners diferentes (`root`, `deploy`,
  `jessessh`, `www-data`);
- permissões variam entre arquivos equivalentes.

### Tarefas

- [ ] preservar integralmente o worktree atual;
- [ ] separar alterações relacionadas por escopo;
- [ ] definir usuário canônico de deploy;
- [ ] registrar política de owner/grupo/permissão;
- [ ] detectar arquivos root-owned em áreas graváveis pelo Magento;
- [ ] normalizar somente após autorização e backup;
- [ ] impedir deploy futuro como root.

### Critérios de aceite

- [ ] alterações da cascata são auditáveis isoladamente;
- [ ] diretórios runtime são graváveis pelo PHP-FPM;
- [ ] arquivos-fonte seguem política uniforme;
- [ ] deploy não produz novos arquivos root-owned.

---

## [ ] Fase 13 — Retirar locks de compatibilidade — PENDENTE

### Objetivo

Reduzir progressivamente:

- `OptimizeHeadStylesPlugin.php`;
- `HeaderImpeccableCascadeLockCss.php`;
- injeções terminal-wins;
- guards e regex legados.

### Tarefas

- [ ] listar cada lock e seu substituto canônico;
- [ ] comprovar equivalência visual;
- [ ] medir duas execuções estáveis;
- [ ] remover um domínio visual por vez;
- [ ] revisar o diff completo;
- [ ] manter rollback byte a byte.

### Critérios de aceite

- [ ] nenhuma regra sem proprietário;
- [ ] uma autoridade por componente/propriedade;
- [ ] redução mensurável de PHP/CSS inline;
- [ ] home, PLP, PDP, carrinho, checkout e B2B aprovados.

---

## [ ] Fase 14 — Auditoria final e encerramento — PENDENTE

### Tarefas

- [ ] repetir hashes e mtimes após 15 segundos;
- [ ] comparar fonte, minificado, `pub/static`, Brotli, Gzip e HTTP;
- [ ] executar PHP lint, build checks e diff checks;
- [ ] validar rotas críticas desktop/mobile;
- [ ] validar teclado, menus, autocomplete e minicart;
- [ ] validar guest/customer B2B;
- [ ] executar Lighthouse e Playwright aprovados;
- [ ] revisar logs Magento;
- [ ] executar revisão defect-first;
- [ ] registrar arquivos, comandos, caches e serviços afetados.

### Critérios finais

- [ ] CLS `≤ 0,1`;
- [ ] LCP `≤ 2,8 s`;
- [ ] TBT `≤ 300 ms`;
- [ ] zero divergência fonte/publicado;
- [ ] zero asset duplicado;
- [ ] zero exceção Magento relacionada;
- [ ] zero achado pendente na revisão;
- [ ] rollback testado e documentado.

---

## 4. Ordem obrigatória de execução

1. ~~Fase 2 — drift do `super-global`.~~ **CONCLUÍDA**
2. ~~Fase 3 — CLS da home.~~ **CONCLUÍDA**
3. ~~Fase 4 — testes de contrato.~~ **CONCLUÍDA**
4. ~~Fase 5 — builds e versionamento.~~ **CONCLUÍDA**
5. Fase 6 — pipeline Magento. ← **próxima**
5. Fase 6 — pipeline Magento.
6. Fase 7 — LESS canônico.
7. Fases 8 e 9 — footer e home inline.
8. Fase 10 — redução do align-grid.
9. Fases 11 e 12 — higiene e operação.
10. Fase 13 — retirada dos locks.
11. Fase 14 — auditoria final.

Não avançar para a próxima fase com regressão aberta na fase atual.

### Backups de fases concluídas

| Fase | Backup |
|------|--------|
| 0–1 | `/var/backups/awa-css-cascade-20260806T173600Z` |
| 2 | `/var/backups/awa-css-cascade-phase2-super-global-20260806T155500Z` |
| 3 | `/var/backups/awa-css-cascade-phase3-cls-20260806T193710Z` · `...-phase3-h12-20260806T225620Z` |
| 4 | `/home/deploy/backups/awa-css-cascade-phase4-tests-20260807T012700Z` |

## 5. Registro de execução

Copiar o bloco abaixo ao final de cada fase concluída:

```text
Fase:
Status: CONCLUÍDA
Data/hora:
Responsável:
Autorização:
Arquivos alterados:
Hash antes:
Hash depois:
Backup:
Rollback:
Comandos executados:
Caches afetados:
Serviços afetados:
Rotas testadas:
Desktop:
Tablet:
Mobile:
LCP:
CLS:
TBT:
Logs:
Revisão:
Observações:
```

