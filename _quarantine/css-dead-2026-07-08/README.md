# Quarentena de CSS mortos — 2026-07-08

Estes 33 arquivos foram movidos de
`app/design/frontend/AWA_Custom/ayo_home5_child/web/css/` para cá na
**Fase 6A** da limpeza de CSS, após auditoria completa documentada em
[`docs/visual-qa/dead-css-manifest-2026-07-08.md`](../../docs/visual-qa/dead-css-manifest-2026-07-08.md).

## Por que estão aqui

Cada um destes arquivos passou por:

1. Busca de referências em todo o repositório (XML, PHTML, JS, LESS, CSS, PHP).
2. Verificação ao vivo em 8 rotas de produção (home, PLP, PDP, carrinho,
   login B2B, checkout, contato, busca).
3. Leitura de contexto do código para confirmar que a única evidência
   restante é comentário/docstring histórico, código já comentado/desativado,
   ou confirmação explícita de depreciação (`REMOVIDO`, `<remove src>`,
   `substituído por`, etc.) — nunca apenas ausência de evidência.

Nenhum destes 33 arquivos tem qualquer referência ativa conhecida.

## O que NÃO está aqui

Os outros 28 arquivos da auditoria original de 61 **não** foram movidos:

- **9 arquivos MANTER** — permanecem em `web/css/` porque são shims oficiais
  (`LEGACY_STATIC_SHIMS.md`) ou foram confirmados ativos em produção durante
  esta auditoria (3 falsos positivos da lista original, ativos na rota de
  busca `/catalogsearch/result`).
- **19 arquivos INVESTIGAR** — permanecem em `web/css/` porque têm evidência
  de código (PHP dinâmico, JS interaction-gated, rotas de checkout/conta não
  testáveis sem sessão autenticada) que impede uma classificação segura como
  morto. Ver detalhes no manifesto.

## Regras desta quarentena

- **Reversível**: todos os moves foram feitos com `git mv`, preservando
  histórico. Restaurar um arquivo é `git mv _quarantine/css-dead-2026-07-08/<arquivo> app/design/frontend/AWA_Custom/ayo_home5_child/web/css/<arquivo>`.
  Rota mais simples: `git revert` do commit desta quarentena.
- **Não é deleção**: nada foi apagado. A remoção definitiva é uma etapa
  futura, separada, após revisão humana e validação por CI (GitHub Actions +
  Playwright).
- **Não editar** os arquivos aqui. Se precisar reaproveitar algum trecho de
  CSS, copie o conteúdo para o bundle ativo correspondente — não restaure o
  arquivo morto em si.

## Próximos passos

1. CI (GitHub Actions + Playwright) valida visualmente que a quarentena não
   quebrou nenhuma rota.
2. Revisão humana do PR.
3. Só então: remoção definitiva em uma PR separada.
