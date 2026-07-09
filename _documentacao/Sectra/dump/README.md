# Scripts de Extração Cirúrgica — Sectra SQL Server

**Data:** 2026-06-29
**Objetivo:** Gerar output de **5-20 MB** a partir de um dump de **84 GB** para análise de integração Magento ↔ Sectra

---

## Escolha o script conforme sua ferramenta

### 🅰️ `SECTRA_DUMP_FAST.sql` — RECOMENDADO para começar
- Usa **PRINT statements** (texto puro)
- Cada resultado aparece em uma seção rotulada
- Você **cola os resultados aqui no chat** ou salva em `.txt`
- **Tempo:** 1-3 minutos
- **Tamanho do output:** 200 KB - 2 MB
- **Ideal para:** SSMS, Azure Data Studio, mssql-cli, qualquer ferramenta com "Results to Text"

### 🅱️ `SECTRA_DUMP_LIGHT.sql` — Extração completa
- Usa **FOR XML PATH** para resultados estruturados
- Inclui **schema completo** de 8 tabelas
- **Tempo:** 3-10 minutos
- **Tamanho do output:** 5-20 MB (compactado: 1-3 MB)
- **Ideal para:** `sqlcmd` com saída em arquivo

---

## Como rodar (Windows + SSMS)

### Opção 1 — Copiar/colar resultado no chat

1. Abra o **SQL Server Management Studio** (SSMS)
2. Conecte no servidor Sectra
3. Abra o arquivo `SECTRA_DUMP_FAST.sql` (Ctrl+O)
4. **Mude para "Results to Text"** (Ctrl+T ou menu Query → Results To → Results To Text)
5. Execute (F5)
6. **Selecione todo o output** (Ctrl+A) e **copie** (Ctrl+C)
7. **Cole aqui no chat** — vou processar

### Opção 2 — sqlcmd com arquivo de saída

Abra **PowerShell** ou **CMD** e rode:

```cmd
sqlcmd -S "NOME_OU_IP_SERVIDOR" -d "NOME_BANCO" -U "usuario" -P "senha" ^
       -i "C:\caminho\SECTRA_DUMP_LIGHT.sql" ^
       -o "C:\saida\sectra_dump.txt" ^
       -f 65001 -W -h-1
```

Parâmetros:
- `-S` servidor (ex: `SECTRA01` ou `201.33.193.193,1433`)
- `-d` banco (ex: `INDUSTRIAL`)
- `-U -P` credenciais (precisa ser leitura!)
- `-i` script de entrada
- `-o` arquivo de saída
- `-f 65001` UTF-8 (preserva acentos)
- `-W` remove espaços trailing
- `-h-1` remove cabeçalho de coluna no batch

---

## Como rodar (Linux/WSL)

```bash
sqlcmd -S servidor -d banco -U user -P pass \
       -i SECTRA_DUMP_FAST.sql \
       -f 65001 -W -h-1 \
       | tee sectra_dump_output.txt
```

---

## Permissões necessárias

Mínima: `db_datareader` no banco do Sectra.

**NÃO precisa:**
- ❌ `db_owner`
- ❌ `db_ddladmin`
- ❌ `sysadmin`

---

## O que cada query vai me dar

| Query | Pergunta que responde | Insight crítico |
|-------|----------------------|------------------|
| **Q1** | Volume de cada tabela | Confirmar estimativas de performance |
| **Q2** | Quantos GUIDs `INTEGRACAOORIGEM` existem | Magento só conhece 4, dump mostra todos |
| **Q3** | Distribuição `CKCLIENTE × CKPROSPECT` | Quantos clientes nativos tem |
| **Q4** | Cliente nativo SEM validador tem pedido web? | **DESCOBRE se Magento está superprotegido** |
| **Q5** | Quais status de pedido existem | Validar mapeamento `A→pending`, `P→processing`... |
| **Q6** | Formato de `PEDIDOWEB` | Confirmar se offset 200000 ainda é o contrato |
| **Q7** | Listas de preço ativas | Validar FATORPRECO 24 e descobrir outras |
| **Q8** | SPs/Views que usam tabelas críticas | Pode revelar regras internas do Sectra |
| **Q9** | Colunas que Magento NÃO conhece | Descobrir campos com info relevante |
| **Q10** | Amostra de hashes `VALIDADOR` | Reverter o algoritmo do hash |
| **Q11** | Colunas numéricas em `FN_FORNECEDORES` | Validar tipos assumidos pelo Magento |
| **Q12** | Triggers | Verificar regras automáticas do SQL Server |

---

## O que fazer com o output

1. **Salve em `.txt`** se for grande
2. **Cole no chat** se for pequeno (< 100 KB)
3. **Comprima com 7-Zip** se for gigante (> 5 MB):
   ```cmd
   "C:\Program Files\7-Zip\7z.exe" a sectra_dump.7z sectra_dump.txt
   ```
4. **Me diga** qual cenário corresponde ao resultado da Q4:
   - `SIM, X clientes nativos sem validador têm pedido web` → **encontrei o bug do gate** (Magento pode liberar sem validador)
   - `NÃO, 0 clientes nativos sem validador têm pedido web` → gate está correto, precisamos achar outro caminho

---

## ⚠️ Garantias de segurança

| Garantia | Como |
|----------|------|
| Não modifica dados | Só `SELECT` e `PRINT` |
| Não modifica schema | Sem `CREATE/ALTER/DROP` |
| Não trava tabelas | `READ UNCOMMITTED` |
| Não loga credenciais | Não aparece nada de senha no output |
| Idempotente | Pode rodar várias vezes |

---

## Se algo der errado

| Erro | Causa provável | Solução |
|------|----------------|---------|
| `Cannot open database` | Banco errado ou sem permissão | Verificar nome do banco e acesso |
| `Invalid object name 'GR_INTEGRACAOVALIDADOR'` | Schema diferente (não dbo) | Trocar `GR_INTEGRACAOVALIDADOR` por `schema.GR_INTEGRACAOVALIDADOR` |
| `Permission denied` | Usuário não é datareader | Pedir acesso de leitura |
| `Login timeout` | Rede bloqueando porta 1433 | Verificar VPN/firewall |
| Query demora horas | Tabela `VE_PEDIDO` muito grande | A Q4 faz scan — substituir por `TOP 50000` se necessário |

---

## Próximo passo após extração

Com o output em mãos, eu vou:

1. **Cruzar Q4 com o gate Magento** → decidir se gate pode ser afrouxado
2. **Cruzar Q2 com código** → descobrir GUIDs não-documentados
3. **Cruzar Q10 com `B2BClientRegistration::buildValidatorHash`** → validar algoritmo do hash
4. **Cruzar Q9 com campos Magento** → identificar gaps
5. **Gerar patches PHP** específicos para o módulo `B2B/Model/Sectra/`

Sem contato com a equipe Sectra. Sem acesso admin ao SQL Server.