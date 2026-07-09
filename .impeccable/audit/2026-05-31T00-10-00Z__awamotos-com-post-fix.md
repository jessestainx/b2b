---
parent: 2026-05-30T23-45-00Z__awamotos-com.md
status: verified-backend-2026-05-31
---

# Pós-correção — awamotos.com

## Hipóteses e evidência

| ID | Hipótese | Resultado | Evidência |
|----|----------|-----------|-----------|
| H-express-empty | Redirect express → cart é carrinho vazio | **CONFIRMADO** | `OnePageCheckout/Controller/Index/Index.php` L125-126; notice `awa-cart-empty__notice` no HTML após sessão |
| H-promax | Promax global no carrinho via `awa-head-preload.phtml` | **CONFIRMADO (causa)** | `curl` cart: `awa-ui-promax-bundle.min.css` presente |
| H-promax-gate | Gate PHP não ativo em produção (FPC/OPcache) | **CONFIRMADO** | `AWA_CART_DEPLOY_MARKER` no `noItems.phtml` não aparece no HTML após `cache:flush` + `redis FLUSHALL` |
| H-refine-live | Refine v4 não no HTML global | **PARCIAL** | Cart: refine blocking no `<head>` via layout; home: falta link async terminal até invalidar bloco |

## Alterações aplicadas (código)

1. **`awa-defer-promax-bundle.phtml`** + sub-bloco em `awa-head-preload.phtml` — gate promax (home/carrinho/skip layout).
2. **`checkout_cart_index.xml`** — `awa_skip_promax_bundle`, `cacheable="false"` na page, promax skip via `setData`.
3. **`awa-commerce-impeccable-refine.css` v4** — site-wide (overflow, H2, PLP, menu side-stripe, empty cart notice).
4. **`awa-impeccable-audit-terminal-css.phtml`** — refine global `?v=4`; bloco `cacheable="false"`.
5. **`noItems.phtml`** — marker de deploy + notice express (já existia).
6. **`ExpressCheckoutEmptyCartNoticePlugin`** — log em `var/log/awa-debug-2f476f.log`.

## Validação pendente (ops)

Executar no servidor:

```bash
php bin/magento cache:flush
redis-cli FLUSHALL   # se Redis for backend de cache
varnishadm ban 'req.http.host ~ awamotos'   # se Varnish ativo
sudo systemctl reload php8.2-fpm   # ou versão do pool — invalida OPcache do preload monolítico
```

Critérios de sucesso:

- Carrinho: `rg -c awa-ui-promax-bundle` → **0**
- Carrinho vazio após `/expresscheckout.html`: comentário `AWA_CART_DEPLOY_MARKER` + `.awa-cart-empty__notice`
- Home: link `awa-commerce-impeccable-refine.css?v=4` com `data-awa-impeccable-terminal="refine"`

## P0 checkout

Comportamento **esperado** com carrinho vazio. UX: mensagem em `EmptyCartContext::getExpressCheckoutNotice()` + estilos refine no carrinho vazio.
