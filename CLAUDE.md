# Grupo Awamotos — Instruções para agentes

## Ambiente

- Plataforma: Magento 2.4.x
- Site: awamotos.com
- Diretório remoto: /home/user/htdocs/srv1113343.hstgr.cloud
- Este diretório deve ser tratado como PRODUÇÃO.

## Regra principal

Trabalhe em modo somente leitura por padrão.

Permissão técnica para executar comandos não significa autorização para
alterar produção.

Antes de qualquer alteração:

1. Apresente o diagnóstico e as evidências.
2. Liste os arquivos e comandos envolvidos.
3. Mostre o diff proposto.
4. Explique riscos e efeitos colaterais.
5. Defina backup, rollback e critérios de sucesso.
6. Aguarde autorização explícita.

Nunca execute automaticamente:

- alteração de arquivos ou banco;
- cache:clean ou cache:flush;
- setup:upgrade, di:compile ou static-content:deploy;
- composer install, update ou require;
- git commit, push, pull, reset, clean, checkout ou revert;
- reload ou restart de serviços;
- purge ou ban de Varnish;
- FLUSHDB ou FLUSHALL;
- edição de vendor/ ou pub/static.

Siga também as Rules em .cursor/rules e carregue as Skills adequadas em
.cursor/skills.

Classifique conclusões como:

- CONFIRMADA;
- FORTE EVIDÊNCIA;
- HIPÓTESE;
- NÃO IDENTIFICADA.
