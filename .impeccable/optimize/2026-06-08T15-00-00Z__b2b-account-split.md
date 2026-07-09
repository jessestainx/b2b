---
target: b2b/account
timestamp: 2026-06-08T15:00:00Z
slug: b2b-account
prior: 2026-06-08T12-00-00Z (§69 bundle → pages.css)
---

# Optimize §70 — Split `pages.css` por rota

## Mudança

`pages.css` (~51KB) dividido em 8 folhas. `customer_account.xml` carrega só `pages-core.css` (~4KB).

| Arquivo | Tamanho | Rotas |
|---------|---------|-------|
| `pages-core.css` | 4 KB | Todas (global) |
| `pages-reorder.css` | 11 KB | `/b2b/reorder/history` |
| `pages-credit.css` | 3 KB | `/b2b/credit` |
| `pages-approval.css` | 3 KB | `/b2b/approval` |
| `pages-company.css` | 4 KB | `/b2b/company` |
| `pages-quote.css` | 10 KB | cotações |
| `pages-shoppinglist.css` | 10 KB | listas de compras |
| `pages-quickorder.css` | 5 KB | pedido rápido |

## CSS bloqueante estimado (módulo B2B, sem account-b2b.css)

| Rota | §69 | §70 |
|------|-----|-----|
| Crédito | 51 KB | **7 KB** |
| Repetir Pedido | 51 KB | **15 KB** |
| Aprovações | 51 KB | **7 KB** |
| Dashboard (só core) | 51 KB | **4 KB** |

## Manutenção

Editar `pages-*.css` por rota. Rebuild bundle legado:

```bash
scripts/build-b2b-pages-css.sh
```

## Validar

Network logado: `/b2b/credit` não deve carregar `pages-reorder.css` nem `pages.css` monolito.
