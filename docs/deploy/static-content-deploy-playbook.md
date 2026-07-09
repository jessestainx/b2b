# Playbook — Static Content Deploy (CSS/LESS/JS)

> Documento canônico para deploy de estáticos no tema `AWA_Custom/ayo_home5_child`.
> Referenciado por `AGENTS.md`, `CLAUDE.md` e `.cursor/rules/awa-css-governance.mdc`.
> Não duplicar este procedimento em outros arquivos — apenas linkar para cá.

**Atualizado:** 2026-07-09 · **Origem:** BUG-OPS-STATIC-018 / sessão de debug `ad5a4e`

---

## Por que `setup:static-content:deploy -f` sozinho não basta

Em produção, a estratégia padrão do Magento é **`quick`**. Ela:

1. **Processa** o arquivo-fonte (aparece no log verboso).
2. **Não sobrescreve** o destino em `pub/static/...` se esse destino **já existir**, mesmo com `-f`.

Isso vale para:

- Bundles LESS compilados: `styles-m.css`, `styles-l.css`, `themes.css`
- CSS simples do tema/módulo: ex. `awa-b2b-status-panel.css`, `status-panel.css`

Além disso:

- `cache:clean` / `cache:flush` **não** limpam `var/view_preprocessed` (cópias stale de `.less`).
- nginx usa `brotli_static on` + `gzip_static on` e serve sidecars `.br`/`.gz` **pré-gerados** sem checar se o `.css`/`.js` original mudou.

Sintoma típico: correção na fonte “invisível” em produção até apagar o destino e regenerar sidecars.

---

## Variáveis (não hardcodear segredos)

Defina no shell antes de rodar (valores reais vêm do ambiente / `app/etc/env.php` — **nunca** commitá-los):

```bash
export MAGENTO_ROOT="${MAGENTO_ROOT:-$(pwd)}"
export WEB_USER="${WEB_USER:-www-data}"
export THEME="${THEME:-AWA_Custom/ayo_home5_child}"
export LOCALES="${LOCALES:-pt_BR en_US}"
export STATIC_THEME_DIR="pub/static/frontend/${THEME}"

# Redis — host/auth via env (não colar senha no playbook)
export REDIS_HOST="${REDIS_HOST:-::1}"
export REDIS_AUTH="${REDIS_AUTH:?defina REDIS_AUTH}"
export REDIS_CACHE_DB="${REDIS_CACHE_DB:-1}"   # cache Magento
export REDIS_FPC_DB="${REDIS_FPC_DB:-2}"       # Full Page Cache
```

Helper Redis (sem expor a senha no histórico de docs):

```bash
redis_flush() {
  local db="$1"
  redis-cli -h "$REDIS_HOST" -a "$REDIS_AUTH" -n "$db" FLUSHDB
}
```

---

## Fluxo obrigatório após editar CSS / LESS / JS do tema filho

### 1. Apagar destinos afetados

A estratégia `quick` só regenera o que **não existe** em `pub/static`. Apague os arquivos (e sidecars) que você alterou.

Exemplo — bundles LESS + um CSS de módulo/tema:

```bash
cd "$MAGENTO_ROOT"

# Bundles LESS compilados (quase sempre necessários se o .less entra via _extend / midgame)
for locale in $LOCALES; do
  rm -f \
    "$STATIC_THEME_DIR/$locale/css/styles-m.css" \
    "$STATIC_THEME_DIR/$locale/css/styles-m.css.br" \
    "$STATIC_THEME_DIR/$locale/css/styles-m.css.gz" \
    "$STATIC_THEME_DIR/$locale/css/styles-l.css" \
    "$STATIC_THEME_DIR/$locale/css/styles-l.css.br" \
    "$STATIC_THEME_DIR/$locale/css/styles-l.css.gz" \
    "$STATIC_THEME_DIR/$locale/css/themes.css" \
    "$STATIC_THEME_DIR/$locale/css/themes.css.br" \
    "$STATIC_THEME_DIR/$locale/css/themes.css.gz"
done

# CSS/JS específicos editados — ajuste os caminhos:
# for locale in $LOCALES; do
#   rm -f "$STATIC_THEME_DIR/$locale/css/SEU-ARQUIVO.css"{,.br,.gz}
#   rm -f "$STATIC_THEME_DIR/$locale/Vendor_Module/css/caminho/arquivo.css"{,.br,.gz}
# done
```

> Dica: liste o que mudou com `git diff --name-only` e apague só os destinos correspondentes em `pub/static/...`.

### 2. Limpar `var/view_preprocessed`

```bash
rm -rf "$MAGENTO_ROOT/var/view_preprocessed/"*
```

### 3. Redeploy

```bash
sudo -u "$WEB_USER" php bin/magento setup:static-content:deploy $LOCALES -f --theme "$THEME"
```

### 4. Regenerar sidecars `.br` / `.gz`

```bash
bash scripts/precompress-static.sh --pub-only
```

Sem este passo, o browser com `Accept-Encoding: br` continua recebendo CSS/JS antigo.

### 5. Flush de caches + reload nginx

```bash
sudo -u "$WEB_USER" php bin/magento cache:flush
redis_flush "$REDIS_CACHE_DB"
redis_flush "$REDIS_FPC_DB"
sudo nginx -t && sudo systemctl reload nginx
```

---

## Apenas PHTML (sem CSS/JS)

```bash
# Preferível: limpar o cache de bloco/página
sudo -u "$WEB_USER" php bin/magento cache:clean block_html full_page

# Se var/view_preprocessed estiver stale para o template:
sudo -u "$WEB_USER" cp \
  "app/design/frontend/${THEME}/[Vendor_Module]/templates/[file].phtml" \
  "var/view_preprocessed/pub/static/app/design/frontend/${THEME}/[Vendor_Module]/templates/[file].phtml"
sudo -u "$WEB_USER" php bin/magento cache:clean block_html full_page
```

Com `opcache.validate_timestamps=0`, mudanças em PHP/PHTML podem exigir restart do PHP-FPM além do passo acima.

---

## Checklist rápido

- [ ] Destinos em `pub/static` dos arquivos editados **apagados** (incluindo `.br`/`.gz`)
- [ ] `var/view_preprocessed` limpo (ou pelo menos as cópias `.less` afetadas)
- [ ] `setup:static-content:deploy … -f --theme AWA_Custom/ayo_home5_child` executado
- [ ] `scripts/precompress-static.sh --pub-only` executado
- [ ] Cache Magento + Redis (cache + FPC) limpos
- [ ] `nginx -t` ok e reload feito
- [ ] Validação no browser com cache desabilitado / SW unregistered
- [ ] `tail var/log/exception.log` sem entradas novas

---

## O que **não** fazer

- ❌ Confiar só em `setup:static-content:deploy -f` com destino já existente
- ❌ Esquecer sidecars `.br`/`.gz` (nginx serve o sidecar, não o arquivo cru)
- ❌ Commitar senhas Redis, hosts internos ou caminhos de usuário em docs/regras
- ❌ Rodar deploy sem `--theme AWA_Custom/ayo_home5_child` para mudanças do tema filho
- ❌ Editar `app/code/Rokanthemes/*` — override no tema filho

---

## Referências

- BUG-OPS-STATIC-018 — causa raiz da estratégia `quick` (ver `docs/PLANO_BUGS_VISUAIS.md` quando presente)
- BUG-B2B-PANEL-017 — exemplo real: backdrop desktop “invisível” até regenerar bundles publicados
- `scripts/precompress-static.sh` — geração de sidecars Brotli/Gzip
