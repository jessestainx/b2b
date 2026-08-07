# Etapa 1.5 — Plano de janelas em produção (DRAFT)

**Status:** RASCUNHO — executar **somente** após HML verde + autorização humana explícita  
**Produção hoje:** NO-GO  
**Data:** 2026-07-22

Este documento **não** autoriza execução. É o plano separado pedido na decisão humana.

---

## Pré-condições (todas obrigatórias)

- [ ] HML aceita (runbook §6)
- [ ] T1 + T2 + rollback ensaiados na HML com evidências
- [ ] Branch `feature/admin-menu-unification` revisada (somente navegação)
- [ ] Comunicação interna (atendentes/supervisores) sobre “Meu Desempenho”
- [ ] Janela de manutenção / responsável de rollback definidos
- [ ] Backup/snapshot de produção recente

---

## Janela 1 — Deploy só do item Meu Desempenho

**Objetivo:** publicar o item de menu sem ocultar o legado.

### Escopo permitido

- Merge/deploy do diff de `menu_platform.xml` (e só o necessário para o item).
- Manter `grupoawamotos_b2b/platform/legacy_menu_visible` = **1** (não alterar).
- Manter `unified_menu_enabled` como está (já 1) — **não regravar**.

### Escopo proibido

- Qualquer `config:set` de menu/flags
- `cache:flush`
- Mudança de ACL, controller, template, KPI
- Alteração de integrações / cron / filas / usuários

### Passos (quando autorizado)

1. Deploy código (procedimento padrão do time) a partir da branch aprovada.
2. Limpar **somente** caches de menu/config necessários ao admin (preferir `cache:clean config` / `full_page` admin se aplicável — **evitar flush global**).
3. Logout/login Admin.
4. Validar:
   - Item **Meu Desempenho** visível para persona com `attendant_self`
   - Invisível para persona sem a ACL
   - URL direta do dashboard responde
   - Menus legados **ainda** presentes (AWA Comercial + Grupo Awamotos→B2B)
5. Monitorar `var/log/exception.log` / `system.log` por 30–60 min.

### Rollback Janela 1

- Reverter deploy do commit do menu (código anterior).
- `cache:clean config` (sem flush, se possível).
- Confirmar ausência do item e estabilidade do admin.

### Critério de saída Janela 1

Aceite humano explícito de que o item funciona com legado ainda visível.

---

## Janela 2 — Ocultar legado (`legacy_menu_visible=0`)

**Objetivo:** ativar a Fase A da unificação de menu.

### Pré-condição extra

- [ ] Janela 1 estável ≥ período acordado (ex.: 24–72 h)
- [ ] Aceite humano para ocultar legado

### Escopo permitido

```bash
# SOMENTE PRODUÇÃO após GO explícito — NÃO rodar agora
# Registrar valor anterior:
php bin/magento config:show grupoawamotos_b2b/platform/legacy_menu_visible

# Alterar somente esta flag, scope default:
php bin/magento config:set --scope=default --scope-code=0 \
  grupoawamotos_b2b/platform/legacy_menu_visible 0

php bin/magento cache:clean config
# compiled_config somente se habilitado e menu stale após re-login
```

- **Não** regravar `unified_menu_enabled` se já for 1.
- **Não** usar `cache:flush`.
- Logout/login obrigatório.

### Matriz de validação (produção)

| Persona | Esperado |
|---|---|
| Admin geral | Raiz **B2B** OK; sem AWA Comercial; sem Grupo Awamotos→B2B |
| Com `attendant_self` | **Meu Desempenho** OK; URL direta OK |
| Comercial (Vendedora) | Painel/carteira OK; sem Meu Desempenho se sem ACL |
| ERP/Sectra | Fila Sectra OK |
| TI | System/Log OK |

### Rollback Janela 2

```bash
php bin/magento config:set --scope=default --scope-code=0 \
  grupoawamotos_b2b/platform/legacy_menu_visible <valor_anterior>
php bin/magento cache:clean config
# logout/login — comprovar retorno dos menus legados
```

---

## Declaração

**Parar antes de qualquer execução em produção.**  
Este arquivo é planejamento. Sem GO humano datado, permanece NO-GO.
