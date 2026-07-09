## ⚠️ Segurança — Ação Obrigatória (2026-07-02)

O `config.json` real deste diretório continha um token Magento em texto puro
versionado no Git (histórico do repositório). Esse token dá acesso a APIs
sensíveis de clientes B2B e pedidos (`/V1/erp/customers/b2b`).

**Ações obrigatórias:**
1. Em **Admin → System → Extensions → Integrations → SECTRA ERP**, desative e
   reative (ou "Reauthorize") a integração para gerar um novo token.
2. Atualize `C:\SectraSync\config.json` **no servidor Sectra** com o token novo.
   **Nunca** coloque o token real no `config.json` deste repositório — o arquivo
   agora está no `.gitignore` e foi desrastreado do Git.
3. Considere o token antigo comprometido; ele deve parar de funcionar assim que
   a integração for reautorizada.

Também foi removido `Find-SqlCredentials.ps1` deste diretório — o script fazia
varredura de credenciais e tentativas de força bruta contra o SQL Server de
produção, sem qualquer utilidade operacional legítima.

---

# Sync Magento B2B → Sectra (Substituicao do OpenCardB2B)

## O que faz

Este script substitui a funcao "Cadastro de Cliente" do antigo **OpenCardB2B** que parou em 01/11/2024.

Ele registra automaticamente os clientes B2B do Magento na tabela `GR_INTEGRACAOVALIDADOR` do Sectra,
permitindo que o processo "Importar Pedidos AWA" reconheca os clientes e importe pedidos sem erro.

**Antes (OpenCart):**
```
Sectra Desktop → GET OpenCart API → Sectra grava SQL Server
```

**Agora (Magento):**
```
Task Scheduler → PowerShell → GET Magento API → sqlcmd → SQL Server
```

Mesmo fluxo, mesma logica. O Magento gera o T-SQL pronto, o script so executa localmente.

---

## Requisitos no Servidor Sectra

1. **PowerShell 5.1+** (ja incluso no Windows Server 2016+)
2. **sqlcmd** (SQL Server Command Line Utilities)
3. **Acesso de rede** a `https://awamotos.com` (porta 443)
4. **Windows Authentication** com permissao de INSERT em `GR_INTEGRACAOVALIDADOR`

---

## Instalacao

### 1. Copiar arquivos

Copie a pasta inteira para o servidor Sectra:

```
C:\SectraSync\
  ├── Sync-MagentoB2BClients.ps1   (registra clientes no GR_INTEGRACAOVALIDADOR)
  ├── Sync-MagentoOrders.ps1        (importa pedidos para VE_PEDIDO + VE_PEDIDOITENS)
  ├── config.json                    (configuracoes — compartilhado pelos dois scripts)
  ├── sync-b2b.bat                   (wrapper Task Scheduler para clientes)
  ├── sync-orders.bat                (wrapper Task Scheduler para pedidos)
  └── logs\                          (criado automaticamente)
```

### 2. Editar config.json

```json
{
    "api_url": "https://awamotos.com",
    "token": "SUBSTITUIR_PELO_TOKEN_DA_INTEGRACAO_SECTRA",
    "sql_instance": "localhost",
    "database": "INDUSTRIAL",
    "log_dir": "C:\\SectraSync\\logs",
    "limit": 500
}
```

| Campo | Descricao |
|-------|-----------|
| `api_url` | URL da loja Magento (nao mude) |
| `token` | Token de integracao Magento (Bearer) |
| `sql_instance` | Instancia SQL Server local (ou `SERVIDOR\INSTANCIA`) |
| `database` | Banco Sectra (normalmente `INDUSTRIAL`) |
| `log_dir` | Onde salvar logs e SQLs executados |
| `limit` | Max clientes por execucao (500 recomendado) |

### 3. Teste manual (dry-run)

Abra PowerShell como Administrador no servidor Sectra:

```powershell
cd C:\SectraSync
.\Sync-MagentoB2BClients.ps1 -DryRun -Verbose
```

Isso vai:
- Chamar a API do Magento
- Gerar o arquivo SQL em `C:\SectraSync\logs\`
- **NAO executar** o SQL (modo dry-run)

Abra o arquivo `.sql` gerado e revise antes de prosseguir.

### 4. Teste real (primeiro sync)

```powershell
.\Sync-MagentoB2BClients.ps1 -Limit 10
```

Sincroniza apenas 10 clientes para validar. Verifique no Sectra:

```sql
SELECT TOP 10 * FROM GR_INTEGRACAOVALIDADOR
WHERE DTSINCRONIZACAO >= DATEADD(MINUTE, -5, GETDATE())
ORDER BY DTSINCRONIZACAO DESC
```

### 5. Agendar no Task Scheduler

**Tarefa 1 — Sync Clientes (sync-b2b.bat):**

1. Abrir **Task Scheduler** → Create Task
2. **General:** Nome `Sync Magento B2B Clients` | Run with highest privileges
3. **Triggers:** Diário 06:00 + Diário 18:00
4. **Actions:** Program `C:\SectraSync\sync-b2b.bat`
5. **Settings:** Stop if runs longer than 10 minutes

**Tarefa 2 — Importar Pedidos (sync-orders.bat):**

1. Abrir **Task Scheduler** → Create Task
2. **General:** Nome `Importar Pedidos AWA` | Run with highest privileges
3. **Triggers:** Repetir a cada **30 minutos** por 24h (ou conforme demanda)
4. **Actions:** Program `C:\SectraSync\sync-orders.bat`
5. **Settings:** Stop if runs longer than 5 minutes | Do not start new instance if already running

---

## Status: 11 Pedidos Liberados (2026-06-19 — RESOLVIDO)

**Causa raiz:** o OpenCardB2B parou em 01/11/2024 e nunca registrou os clientes em
`GR_INTEGRACAOVALIDADOR`. A conexão de escrita JESS configurada no admin falhou
("Adaptive Server connection failed") — senha incorreta no cadastro do admin Magento.

**Solução aplicada (sem acesso ao servidor Sectra):**
`B2BClientRegistration::isClientRegistered()` agora aceita clientes que existem em
`FN_FORNECEDORES` com `CKCLIENTE='S'` e `CKPROSPECT='N'` como fallback, eliminando
a dependência do GR_INTEGRACAOVALIDADOR para clientes ERP já estabelecidos.

**Resultado:** todos os 11 pedidos movidos para `ready_for_import` às 18:00 de 2026-06-19.

```
#078 #079 #084 #085 → cliente 2541  (MAM DISTRIBUIDORA)   R$ 19.265
#088               → cliente 18202 (GABRIEL DE ASSIS)     R$    276
#090 #093 #096     → cliente 11134 (PANTERA E-COMMERCE)   R$  1.362
#091               → cliente 17421 (SARAH NICOLY AMORIM)  R$    314
#084               → cliente 18771 (BORNANCIN MOTOS)      R$     28
```

**Próximo passo obrigatório:** configurar `config.json` com token real e rodar
`Sync-MagentoOrders.ps1` no servidor Sectra para importar os pedidos para VE_PEDIDO.

**Pendência:** corrigir senha do usuário JESS em Admin → Stores → Configuration →
GrupoAwamotos ERP → Write Connection. Se a senha for correta, o Magento auto-registra
clientes futuros em GR_INTEGRACAOVALIDADOR sem depender dos scripts PS.

---

## Prevenção: Como Funciona Agora

Com o fix aplicado, o fluxo para clientes ERP estabelecidos é:

```
Pedido colocado → awaiting_customer_validation
    ↓ (cron SyncOpenCartBridge, a cada 5 min)
    ↓ isClientRegistered() verifica FN_FORNECEDORES (fallback nativo)
    ↓ confirma em oc_customer_b2b_confirmed
    → ready_for_import  (sem precisar de GR_INTEGRACAOVALIDADOR)
    ↓ Sync-MagentoOrders.ps1 (quando configurado com token)
    → imported
```

Para novos clientes (nunca no ERP), o fluxo ainda requer:
1. Sync-MagentoB2BClients.ps1 registra em GR_INTEGRACAOVALIDADOR
2. Cron confirma e libera o pedido

---

## Monitoramento

### Logs
Cada execucao gera dois arquivos em `C:\SectraSync\logs\`:
- `sync_YYYY-MM-DD_HH-mm-ss.log` — log de execucao
- `sync_YYYY-MM-DD_HH-mm-ss.sql` — SQL executado (para auditoria)

### Verificar no Sectra
```sql
-- Ultimos clientes sincronizados
SELECT TOP 20 *
FROM GR_INTEGRACAOVALIDADOR
WHERE INTEGRACAOORIGEM = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7'
ORDER BY DTSINCRONIZACAO DESC;

-- Total registrados
SELECT COUNT(*) AS total
FROM GR_INTEGRACAOVALIDADOR
WHERE INTEGRACAOORIGEM = '7D4C6FBD-62CF-427F-A0ED-3C06602F05D7';
```

### Verificar via API Magento
```bash
# Quantos faltam registrar
curl -H "Authorization: Bearer TOKEN" \
  "https://awamotos.com/rest/V1/erp/customers/b2b/sync-sql?limit=1"
# Se count=0, todos sincronizados
```

---

## Troubleshooting

| Problema | Solucao |
|----------|---------|
| `Unable to connect to remote server` | Verificar firewall: porta 443 para awamotos.com |
| `401 Unauthorized` | Token expirado. Gerar novo em Magento Admin → System → Integrations |
| `sqlcmd not recognized` | Instalar SQL Server Command Line Utilities |
| `INSERT duplicate key` | Normal — cliente ja registrado. Script ignora automaticamente |
| `Todos ja registrados` | Nada a fazer — sync esta em dia |

---

## Comparativo OpenCardB2B vs Magento Sync

| Aspecto | OpenCardB2B (antigo) | Magento Sync (novo) |
|---------|---------------------|---------------------|
| Plataforma | Desktop C# | PowerShell + API REST |
| Frequencia | Manual ou agendado | Task Scheduler (2x/dia) |
| Direcao | Sectra puxa de OpenCart | Sectra puxa de Magento |
| SQL gerado | Pelo app desktop | Pela API Magento |
| Execucao SQL | Local (sqlcmd equiv.) | Local (sqlcmd) |
| Logs | Nenhum | Completo em C:\SectraSync\logs |
| Credenciais SQL | Windows Auth | Windows Auth (mesmo) |
