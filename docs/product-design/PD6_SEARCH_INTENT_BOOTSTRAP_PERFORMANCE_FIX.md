# PD6 — Search Intent Bootstrap Performance Fix

Branch: `fix/pd6-search-intent-bootstrap-performance`
Ultima atualizacao: 2026-07-10
Objetivo: reduzir o volume de JavaScript sincrono no bootstrap de intencao de busca em
mobile, sem quebrar o autocomplete Mirasvit, sem alterar comportamento desktop e sem
criar workaround artificial para o Playwright. Base: PD5 (ver
`PD5_MOBILE_SEARCH_HEADLESS_CRASH_INVESTIGATION.md`), classificacao Caso 2 (bug/limitacao
do Chromium headless, com fator agravante real de produto).

## Descoberta crítica antes da correção: `awa-home-bootstrap-defer.js` está inativo

Antes de aplicar qualquer correção, foi encontrado um trabalho **não commitado** já presente
no working tree (branch já existia com edições pendentes) em
`app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-home-bootstrap-defer.js` e
`app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php` (versão bumped para
`20260710-pd6-search-intent-defer-v1`). A implementação em si era correta e bem construída
(defere `appendMerged()` + `initRokanTheme()` + hero sliders via `requestIdleCallback` quando
o toque/foco vem exclusivamente do campo de busca), **mas foi confirmado por investigação
direta que esse arquivo não está sendo carregado em produção agora**:

- `grep`/`curl` na Home real: nenhuma ocorrência de `awa-home-bootstrap-defer` em nenhum lugar
  do HTML.
- Verificação via Playwright real (`page.on('request')`): **zero requisições** para
  `bootstrap-defer`/`bootstrap-merged`/`require-stub` ao carregar a Home.
- O elemento `#awa-home-bootstrap-merged` (que o script espera encontrar) **não existe** no
  DOM renderizado.
- Causa raiz: `DeferHomeScriptsPlugin::beforeSendResponse()` só injeta o script via regex
  fallback quando encontra um padrão de bundle merge do Magento (`_cache/merged/*.js`) no
  HTML — mas `dev/js/merge_files` e `dev/js/enable_js_bundling` estão **desabilitados** neste
  ambiente (`config:show` confirma `0`/`0`), então esse padrão nunca existe e a injeção nunca
  dispara.

**Decisão**: manter a implementação já existente (é correta, defensiva e inofensiva — se o
merge-js for reativado no futuro, o defer funcionará automaticamente), mas **não contar com
ela como a correção real do PD6**, já que não tem efeito algum na produção atual. Documentado
aqui para não repetir esse mesmo engano em investigações futuras.

## Mecanismo real identificado: `awa-custom-js-loader.phtml`

Busca por quem realmente controla os ~14 scripts identificados na PD5 levou a
`app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-custom-js-loader.phtml`
(783 linhas) — o orquestrador central de scripts comportamentais do tema. Achados:

1. **Dois scripts realmente escopados para busca** via helper compartilhado
   `$renderIntentScriptLoader()`: `awa-header-minicart-ui-v2.js` e
   `awa-header-a11y-performance.min.js`, ambos com seletores de intenção incluindo `#search`,
   `.header .top-search`, `.header .block-search`. O listener (`pointerdown`/`touchstart`/
   `click`/`focusin`/`keydown` no `document`, capture phase) verifica `matchesIntentTarget()`
   e, se corresponder, chama `appendScript()` **de forma síncrona, no mesmo tick do evento**.
2. **Múltiplos outros listeners genéricos** (não escopados para busca, mas disparados por
   `pointerdown`/`keydown`/`touchstart` em **qualquer lugar** do documento) que também competem
   pelo main thread na Home: `awaCustomCompatBootstrap` (condicional), `awa-toast` +
   `awa-messages-interceptor` (sempre ativo na Home), `awa-scroll-reveal` (sempre ativo na
   Home) — cada um com seu próprio `require(['mage/apply/main'])`/`require(['js/awa-scroll-reveal'])`
   executado **sincronamente** dentro do handler do evento.
3. Como todos esses listeners escutam os mesmos tipos de evento no `document` (capture phase),
   um único toque no campo de busca dispara **todos simultaneamente** — os dois scoped para
   busca (por `matchesIntentTarget`) mais os genéricos (que não fazem checagem de alvo) — todos
   injetando `<script>`/chamando `require()` no mesmo tick de JavaScript, exatamente quando o
   Chromium ainda está processando o próprio dispatch do evento de toque (mecanismo do crash
   confirmado na PD5).

## Correção aplicada (fonte canônica, cirúrgica)

**Arquivo**: `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-custom-js-loader.phtml`

Padrão aplicado em **todos os pontos afetados que disparam no toque/tecla da Home**: manter
`done = true` e a remoção dos listeners (`cleanup()`) **imediatos e síncronos** (nenhuma
mudança na decisão "o script vai carregar" nem no momento em que os listeners somem), mas
**adiar apenas o trabalho pesado** (`appendScript()` / `require()` / criação do
`x-magento-init`) via `requestIdleCallback` (fallback `setTimeout(fn, 0)` apenas quando
`requestIdleCallback` não existe — não é um "setTimeout aleatório", é o mesmo fallback padrão
já usado no restante do projeto):

1. `$renderIntentScriptLoader()` (função compartilhada, usada pelos dois scripts escopados
   para busca): novo `scheduleAppendScript()` substitui a chamada direta a `appendScript()`
   dentro de `trigger()`.
2. Bloco `awaCustomCompatBootstrap` (Home): novo `scheduleBootWork()` substitui a criação do
   `<script type="text/x-magento-init">` + `require(['mage/apply/main'])` inline.
3. Bloco `awa-toast` + `awa-messages-interceptor` (Home): mesmo padrão (`scheduleBootWork()`).
4. Bloco `awa-scroll-reveal` (Home): novo `scheduleLoadScrollReveal()` substitui o
   `require(['js/awa-scroll-reveal'], ...)` direto.

**Não alterado** (fora de escopo, conforme regras da PD6): `$renderIdleScriptLoader()`
(compartilhada entre `awa-home-hero-tabs-ui.js` e `awa-footer-ux.js` — tocar nela afetaria o
footer, explicitamente fora de escopo); o bloco `awa-scroll-reveal` de rotas não-Home (linha
~565, usado por catálogo/PLP/PDP, fora de escopo); `awa-keyboard-shortcuts`; checkout/carrinho;
Mirasvit (`awa-mirasvit-autocomplete-init.js`, já corrigido na PD2, autocomplete continua
funcionando via seu próprio bootstrap independente, intocado).

`app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php`: a constante
`HOME_BOOTSTRAP_VERSION` já havia sido bumped (trabalho pré-existente) para
`20260710-pd6-search-intent-defer-v1` — mantida (cache-busting do `awa-home-bootstrap-defer.js`,
mesmo estando confirmado inativo, ver seção acima).

## Deploy realizado

- `terser` para regerar `.min.js` de `awa-home-bootstrap-defer.js` (mesma ferramenta do
  projeto).
- `setup:static-content:deploy pt_BR -f --theme AWA_Custom/ayo_home5_child` (não atualizou os
  arquivos em `pub/static` — mesmo com `-f` — timestamps continuaram antigos; causa não
  totalmente diagnosticada nesta fase, possivelmente hash/signature cache do deploy quick
  strategy). Corrigido com sincronização manual (`cp` fonte → `pub/static/.../pt_BR/js/` e
  `.../en_US/js/`, regeneração de `.br`/`.gz`, `chown www-data:www-data`) — mesmo padrão já
  documentado como necessário neste projeto.
- `awa-custom-js-loader.phtml`: copiado manualmente para
  `var/view_preprocessed/pub/static/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/`
  (cache de template PHTML, não limpo por restart de PHP-FPM sozinho).
- `cache:clean block_html full_page`, restart `php8.4-fpm` (OPcache,
  `opcache.validate_timestamps=0`), Redis `FLUSHDB` (DB1 cache + DB2 FPC), `PURGE` Varnish
  (`X-Magento-Tags-Pattern: .*`), restart `nginx`.
- Confirmado via `curl`/Playwright reais que o HTML de produção reflete as mudanças
  (`scheduleAppendScript`, `scheduleBootWork`, `scheduleLoadScrollReveal` presentes no HTML
  servido).

## Validação funcional (smoke, produção real)

- Autocomplete desktop: dropdown Mirasvit abre corretamente para "bagageiro" (`isVisible: true`).
- Minicart: painel abre corretamente ao clicar no trigger.
- Menu vertical (Departamentos): abre corretamente ao clicar no trigger.
- Zero console errors, zero page errors em todas as execuções de validação.
- `var/log/exception.log` e `var/log/system.log`: sem novas entradas após deploy.

## Medição de taxa de crash (antes vs depois)

Reexecução do cenário exato da PD5 (mobile 390x844, `devices['iPhone 14']`, tap + 
`pressSequentially('bagageiro')`), browser novo por tentativa:

| Momento | Amostras | Crashes | Taxa |
|---|---|---|---|
| PD5 (antes da correção, baseline) | 19 | 4 | ~21% |
| PD6 pós-correção (1ª rodada, só `renderIntentScriptLoader`) | 10 | 1 | 10% |
| PD6 pós-correção (2ª rodada, + blocos genéricos Home) | 10 | 1 | 10% |

**Resultado honesto**: a correção **reduziu a taxa de crash observada de ~21% para ~10%**
(redução relativa de ~50%), mas **não eliminou o crash** (meta declarada de 0% não atingida
nesta fase). Isso é consistente com a classificação da PD5 (Caso 2 — o crash é uma limitação
do Chromium headless no dispatch de toque sob main thread ocupado; reduzir o volume de JS
reduz a *probabilidade* da corrida de tempo acontecer, mas não elimina a fragilidade do
Chromium em si). Amostra pequena (N=10 por rodada) — a diferença entre 10% e 21% é indicativa
mas não estatisticamente robusta; recomenda-se amostra maior (N=30+) em fase futura/CI para
confirmar com mais confiança.

## Classificação final desta fase

Não é possível marcar `PD2-002`/`PD5-001` como `CLOSED` — a causa raiz (limitação do Chromium
headless) não foi eliminada, apenas mitigada. Esta fase (PD6) é registrada como `TESTED_LOCAL`
(correção aplicada, testada localmente, redução real e mensurável, mas incompleta).

## Arquivos alterados

- `app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-custom-js-loader.phtml`
  (correção principal desta fase)
- `app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-home-bootstrap-defer.js` +
  `.min.js` (trabalho pré-existente, mantido, confirmado inativo em produção — ver seção
  "Descoberta crítica" acima)
- `app/code/GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php` (trabalho
  pré-existente, apenas version bump, mantido)

Nenhum arquivo de `vendor/`, `pub/static`/`var/view_preprocessed` como fonte, checkout/
pagamento, CSS morto/quarentena, product cards, footer, menu vertical ou minicart foi alterado
como fonte (apenas a timing de scripts que também afetam esses componentes, sem alterar seu
comportamento funcional — validado no smoke test).

## Próximo passo recomendado

1. Reexecutar a matriz completa da PD5 (31 experimentos) contra esta correção para uma medição
   mais completa e comparável experimento-a-experimento (não apenas o cenário baseline).
2. Investigar por que `setup:static-content:deploy -f` não atualizou `pub/static` mesmo com
   force — pode indicar um problema de cache de deploy mais amplo, fora do escopo desta fase,
   mas que vale uma issue dedicada.
3. Considerar consolidar os múltiplos listeners `pointerdown`/`keydown`/`touchstart` da Home
   (hoje ainda são ~4-5 listeners independentes, cada um com seu próprio `once:true`) em um
   único dispatcher compartilhado — reduziria ainda mais a sobrecarga por evento, mas é uma
   mudança de escopo maior que a aplicada nesta fase (risco maior, requer mais validação).
4. Avaliar (fora desta fase) se `awa-home-bootstrap-defer.js`/`DeferHomeScriptsPlugin` devem
   ser removidos/simplificados já que estão confirmadamente inativos, ou se o merge-js deveria
   ser reabilitado para que a lógica passe a ter efeito real.
