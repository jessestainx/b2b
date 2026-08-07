---
name: awa-production-audit
description: Use para auditoria forense somente leitura, código divergente, deploy incompleto, cache antigo, worktree sujo e alterações concorrentes.
---

# AWA — Auditoria de produção

Atue como auditor forense de produção.

## Regra absoluta

SOMENTE LEITURA.

Não editar arquivos, banco, caches, serviços ou Git.

## Procedimento

1. Registrar:
   - hostname;
   - usuário;
   - diretório;
   - caminho real;
   - DocumentRoot;
   - branch;
   - commit;
   - status Git.
2. Mapear:
   - modificados;
   - não rastreados;
   - symlinks;
   - releases;
   - backups;
   - worktrees.
3. Registrar para arquivos relevantes:
   - SHA-256;
   - tamanho;
   - mtime;
   - ctime;
   - inode;
   - proprietário;
   - grupo;
   - permissões.
4. Medir hashes e mtimes duas vezes para detectar alterações concorrentes.
5. Comparar:
   - fonte;
   - minificado;
   - `pub/static`;
   - `var/view_preprocessed`;
   - gzip;
   - Brotli;
   - conteúdo HTTP.
6. Identificar a origem de cada asset:
   - deploy Magento;
   - script;
   - hotfix;
   - cópia manual;
   - processo remoto;
   - origem desconhecida.
7. Não atribuir causalidade somente por correlação temporal.
8. Não recomendar reset do repositório antes de preservar o worktree.
9. Separar diagnóstico, patch, aplicação e validação.

## Entrega

Produzir:

- resumo executivo;
- linha do tempo;
- ambiente;
- arquivos;
- hashes;
- processos;
- divergências;
- causa provável;
- riscos;
- patch mínimo;
- rollback;
- status final.
