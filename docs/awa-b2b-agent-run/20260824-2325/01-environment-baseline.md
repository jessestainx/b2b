# Linha de base do ambiente

**Classificação: CONFIRMADA**

## Host desta sessão

| Campo | Valor |
|---|---|
| hostname | `awamotos.com` |
| usuário | `deploy` |
| pwd real | `/home/user/htdocs/srv1113343.hstgr.cloud` |
| DocumentRoot | `/home/jessessh/htdocs/srv1113343.hstgr.cloud/pub` e `/home/user/htdocs/srv1113343.hstgr.cloud/pub` |
| Magento | 2.4.8-p3, modo **production** |
| PHP CLI | 8.4.17 |
| Composer | 2.10.2 |
| Node | 20.20.2 |
| Banco | Percona 8.4.7, schema `magento`, host `localhost` |
| Base URL | `https://awamotos.com/` |
| APP_ENV | unset |
| Branch implantada | `rescue/production-20260712` |
| HEAD | `bad08baeec0a7d54542e17e05e6862581086a382` |
| Working tree produção | **sujo** (324 modificados, 91 untracked) — **preservado** |

## Serviços (portas)

- Nginx 80/443
- Varnish 6081
- Redis 6379 (127.0.0.1 / docker bridges)
- MySQL 3306
- OpenSearch transport 9300 (HTTP 9200 não respondeu em 127.0.0.1)

## Worktrees Git

| Caminho | Branch | SHA |
|---|---|---|
| DocumentRoot | `rescue/production-20260712` | `bad08baee` (sujo) |
| `/home/deploy/.cursor/worktrees/awa-b2b-e2e-20260824-2325` | `agent/awa-b2b-e2e-20260824-2325` | `bad08baee` (esta execução) |
| `/home/deploy/.cursor/worktrees/.../xvly` | `cursor/2b433e31` | `d4ba93e52` (não tocada) |

## Ambientes

| Ambiente | Identificado | Notas |
|---|---|---|
| Produção | SIM | Este host, `https://awamotos.com/` |
| Staging | NÃO | Nenhum DocumentRoot/base URL de staging neste servidor |
| Local | NÃO neste host | Worktree de código apenas; sem `env.php` copiado |

## Proteções aplicadas

- Nenhuma edição no DocumentRoot de produção.
- Worktree isolado para código/testes/docs.
- Playwright: `TARGET_ENV` obrigatório; escrita em `awamotos.com` só com `pilot`.
- Pedido piloto bloqueado: `PILOT_*` não preenchidos.

## Versões de módulos GrupoAwamotos (habilitados)

30 módulos, incluindo `GrupoAwamotos_B2B`, `GrupoAwamotos_ERPIntegration`, `GrupoAwamotos_AiAssistant`, `GrupoAwamotos_SchemaOrg`.
