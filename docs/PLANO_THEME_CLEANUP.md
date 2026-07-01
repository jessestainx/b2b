# Plano de Correção e Modernização do Tema — AWA Motos (AYO Child)

> **Objetivo:** Eliminar o débito técnico acumulado no tema filho `AWA_Custom/ayo_home5_child` e estabelecer uma arquitetura CSS/LESS sustentável, previsível e sem conflitos de especificidade.
>
> **Versão:** 2.0 · Jun/2026 · Magento 2.4.8-p3

---

## 1. Diagnóstico — Estado Atual

### 1.1 Métricas Reais (baseline Jun/2026)

| Indicador | Valor | Referência saudável | Risco |
|-----------|------:|--------------------:|-------|
| Arquivos CSS em `web/css/` | **229** | ≤ 10 | 🔴 Crítico |
| Partials LESS em `source/` | **361** | ≤ 30 | 🔴 Crítico |
| Partials `_awa-*.less` | **296** | ≤ 20 | 🔴 Crítico |
| Declarações `!important` | **32.826** | ≤ 50 | 🔴 Crítico |
| `@import` no `_extend.less` | **115 ativos** | ≤ 15 | 🔴 Crítico |
| Linhas no `_extend.less` | **2.386** | ≤ 200 | 🔴 Crítico |
| Templates PHTML em `html/` | **109** | ≤ 20 | 🟡 Alto |
| Arquivos `.bak`/`.orig` no source | **≥ 5** | 0 | 🟡 Alto |

### 1.2 Causa Raiz

O anti-padrão central é o **modelo "append-only"**: cada fix ou melhoria adicionou um novo arquivo LESS/CSS em vez de editar o existente. Isso gerou uma espiral:

```
Conflito CSS
  → Adiciona novo arquivo com !important para ganhar
    → Novo conflito
      → Adiciona outro arquivo com !important mais específico
        → ...
```

**Resultado:** 32.826 `!important` formam uma guerra de especificidade onde nenhuma regra tem precedência confiável — qualquer mudança pode quebrar 3 outras áreas.

### 1.3 Impactos Operacionais

- **Deploy imprevisível:** `setup:static-content:deploy` processa 500+ arquivos LESS; ordem de compilação pode variar entre deploys.
- **Debugging impossível:** Para encontrar qual regra está ativa num elemento é necessário varrer centenas de arquivos.
- **Performance:** Mesmo com CSS minificado, o peso agregado e a fragmentação de bundles aumenta o tempo de parse.
- **Regressões frequentes:** Qualquer edição pode afetar seletores em 10+ arquivos sem rastreamento claro.
- **Onboarding zero:** Nenhum dev externo consegue entender a cascata sem semanas de estudo.

---

## 2. Bugs Visuais Ativos — Diagnóstico e Causa Raiz

> Esta seção documenta bugs observados em produção com análise técnica da causa real — não apenas sintoma.

---

### BUG-01 — Header / Layout Reverte para Versão Antiga após Deploy

**Sintoma:** Após qualquer `setup:static-content:deploy` completo ou flush de cache, o header volta ao estado anterior (versão antiga de estilos), mesmo que o CSS tenha sido editado corretamente.

**Gravidade:** 🔴 Crítico — afeta a credibilidade de todos os deploys

**Causa raiz — 4 camadas em cascata:**

#### Camada 1 — FPC com HTML desatualizado (Redis DB2)

O Full Page Cache armazena o HTML completo, incluindo todas as tags `<link>` e `<style>` inline. Quando o CSS é atualizado mas o FPC **não é limpo**, o browser recebe HTML antigo que referencia CSS antigas ou CSS que já não existe no mesmo nome.

```bash
# Diagnóstico: verificar se FPC está populado com versão antiga
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 DBSIZE
# Se retornar > 0 após um deploy sem flush, o FPC está servindo versão antiga
```

**Fix obrigatório após qualquer deploy CSS:**
```bash
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
```

#### Camada 2 — `var/view_preprocessed` com arquivos `.orig` stale

Existem arquivos stale confirmados em produção:
```
var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/
├── awa-align-grid-terminal-2026-06-11.min.orig.min.css  ← STALE
└── awa-impeccable-layout-2026-06-16.orig.min.css        ← STALE
```

O módulo `GrupoAwamotos_PreprocessedFallback` intercepta erros de template e serve arquivos de `var/view_preprocessed` quando o arquivo não existe em `pub/static`. Se o fallback pegar um `.orig`, a versão antiga é servida silenciosamente.

**Fix imediato:**
```bash
# Remover arquivos stale do preprocessed
sudo -u www-data rm -f var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/*.orig*
sudo -u www-data rm -f var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/*.bak*
```

**Fix estrutural:** limpar `var/view_preprocessed` sempre que o tema for modificado:
```bash
sudo -u www-data rm -rf var/view_preprocessed/pub/static/frontend/AWA_Custom/
```

#### Camada 3 — `OptimizeHeadStylesPlugin` com nomes de arquivo hardcoded

O plugin `GrupoAwamotos\Theme\Plugin\Response\OptimizeHeadStylesPlugin` manipula o HTML da resposta HTTP procurando por nomes de arquivo CSS específicos no body:

```php
private const HOME_DENSITY_GRID_FILE = 'awa-home-density-grid-20260611.min.css';
private const CATALOG_DENSITY_GRID_FILE = 'awa-catalog-density-grid-20260611.min.css';
```

Quando um arquivo CSS é renomeado (ex: durante consolidação de bundles), o plugin não encontra a string esperada e pode omitir a injeção correta — resultando em CSS ausente ou versão antiga servida.

**Regra crítica:** qualquer renomeação de bundle CSS exige atualização correspondente nas constantes deste plugin.

#### Camada 4 — `PatchHomeHeaderHtmlPlugin` com detecção frágil

O plugin `GrupoAwamotos\Theme\Plugin\Response\PatchHomeHeaderHtmlPlugin` verifica se o header existe no HTML usando `HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)`. Se a marcação HTML do header mudar (ex: alteração de classe ou estrutura no template do Rokanthemes), a detecção falha e o plugin pode injetar CSS de header desatualizado na resposta.

---

**Protocolo de deploy CORRETO para evitar BUG-01:**

```bash
# PASSO 1 — Limpar preprocessed do tema
sudo -u www-data rm -rf var/view_preprocessed/pub/static/frontend/AWA_Custom/

# PASSO 2 — Compilar CSS
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR en_US -f \
  --theme AWA_Custom/ayo_home5_child

# PASSO 3 — Flush Magento cache (layout, block, config)
sudo -u www-data php bin/magento cache:flush

# PASSO 4 — Flush Redis (obrigatório — o cache:flush NÃO faz isso)
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 1 FLUSHDB   # cache Magento
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB   # FPC — HTML completo

# PASSO 5 — Verificar
tail -5 var/log/exception.log
```

**Correção estrutural (Fase 7):** substituir os dois plugins de manipulação de HTML por uma estratégia baseada em layout XML puro, eliminando a dependência de nomes de arquivo hardcoded e de parsing de HTML no response.

---

### BUG-02 — CSS da Home Diferente de PLP/PDP após Deploy Parcial

**Sintoma:** Home carrega corretamente, mas PLP ou PDP mostram header/nav desalinhado ou sem estilos.

**Causa:** O `OptimizeHeadStylesPlugin` remove bundles diferentes por tipo de página (`CATALOG_STACK_ACTIONS`, `CHECKOUT_FOCUS_ACTIONS`). Se o FPC do tipo de página não foi limpo ou o plugin está com referência incorreta, cada tipo de página recebe um conjunto diferente de CSS.

**Fix:** sempre flush completo do DB2 após qualquer alteração de CSS. Nunca fazer flush parcial por tipo de página.

---

### BUG-03 — FOUT (Flash of Unstyled Text) nas Fontes

**Sintoma:** Na home, os títulos pisam com font-size diferente por 100–400ms antes de carregar a fonte Rubik.

**Causa:** O `head.phtml` preloada `rubik-600.woff2` somente quando a configuração de fonte do Themeoption é `Montserrat` (detecção indireta). Se a configuração mudar ou se a condição falhar, o preload não acontece.

```php
// head.phtml — condição frágil
$isMontserrat = (strtolower(trim($googleFontFamily)) === 'montserrat');
if ($isMontserrat):  // ← o preload da Rubik depende desta condição
```

**Fix:** mover o preload de fontes críticas (`rubik-600.woff2`, `source-sans-3-400.woff2`) para `default_head_blocks.xml` de forma incondicional, removendo a dependência da configuração de admin.

---

### BUG-04 — Layout Cache Desatualizado Após Alteração de `default.xml`

**Sintoma:** Após editar `default.xml` ou qualquer layout XML, as mudanças não aparecem mesmo após `cache:flush`.

**Causa:** O Magento compila os layouts em `generated/code/` e armazena o resultado compilado no cache de layout. O comando `cache:flush` limpa o cache, mas se `generated/` tiver artefatos de DI desatualizados apontando para classes antigas, o layout antigo pode ser servido.

**Fix:**
```bash
sudo -u www-data php bin/magento cache:clean layout block_html full_page
# Se persistir:
sudo -u www-data rm -rf generated/code/
sudo -u www-data php bin/magento setup:di:compile
sudo -u www-data php bin/magento cache:flush
```

---

### BUG-05 — Service Worker Servindo CSS Obsoleto

**Sintoma:** Em visitas repetidas, o browser mostra estilos antigos mesmo após deploy e flush.

**Causa:** Magento registra um Service Worker em modo de produção. O SW pode ter cachado assets estáticos (CSS, JS) de versões anteriores e servir o cache mesmo quando os arquivos foram atualizados no servidor.

**Fix para debug:**
```
DevTools → Application → Service Workers → Unregister
DevTools → Network → Disable cache (marcar durante testes)
```

**Fix em código:** usar cache-busting via query string nos assets críticos (o Magento já faz isso via `?version=` na URL, mas os plugins de manipulação de HTML podem remover ou não propagar esses parâmetros).

---

### Mapa de Dependências dos Bugs

```
setup:static-content:deploy
  ↓
  ├─ Não limpou var/view_preprocessed → PreprocessedFallback serve .orig → BUG-01 Camada 2
  ├─ Não limpou Redis DB2 (FPC) → HTML antigo com CSS antigas → BUG-01 Camada 1
  │
  └─ Limpou tudo corretamente
       ↓
       ├─ OptimizeHeadStylesPlugin não encontra nome hardcoded → CSS ausente → BUG-01 Camada 3
       ├─ PatchHomeHeaderHtmlPlugin detecção falha → header CSS injetado errado → BUG-01 Camada 4
       ├─ Service Worker com cache antigo → browser serve versão stale → BUG-05
       └─ Layout cache não limpo → default.xml antigo em uso → BUG-04
```

---

## 3. Análise Visual da Homepage — Diagnóstico por Zona

> Análise baseada em screenshot da homepage (Jun/2026) cruzada com dados reais do banco.

---

### 3.1 Métricas do Catálogo (dados reais)

| Indicador | Valor | Status |
|-----------|------:|-------|
| Total de produtos | 692 | — |
| Produtos **sem** `small_image` / `thumbnail` | **207** | 🔴 Crítico |
| Produtos **sem** `meta_description` | **687** | 🔴 Crítico |
| Produtos **sem** `meta_title` | **687** | 🔴 Crítico |
| Imagens JPG em `pub/media` | 5.756 | — |
| Imagens WebP em `pub/media` | 1.418 | 🟡 Cobertura parcial |
| Tamanho total de `pub/media/catalog` | **681MB** | 🟡 Alto |
| Adapter de imagem | **GD2** | 🟡 Subótimo |
| Pedidos últimos 30 dias | 39 | — |
| Carrinhos ativos 30 dias | 80 | — |
| Clientes novos 30 dias | 82 | 🟡 Baixo |

---

### 3.2 Bugs Identificados por Zona da Homepage

#### ZONA HEADER

| ID | Problema | Causa | Severidade |
|----|----------|-------|-----------|
| H-01 | Header muito compacto — logo pequeno em monitores wide | CSS `max-width` do container header subdimensionado | 🟡 Médio |
| H-02 | Navegação de categorias ilegível em mobile | Fonte pequena + `overflow: hidden` sem breakpoint correto | 🟡 Médio |

#### ZONA HERO / SLIDER

| ID | Problema | Causa | Severidade |
|----|----------|-------|-----------|
| SL-01 | Miniaturas de slide muito pequenas (< 40px) | Template Rokanthemes sem override de tamanho | 🟡 Médio |
| SL-02 | Badge "2026" com tipografia inconsistente com o Design System | CSS inline hardcoded no bloco CMS | 🟢 Baixo |
| SL-03 | Texto "GUIDAO" sem acento — erro de conteúdo | Conteúdo do bloco CMS `home_slider` | 🟢 Baixo |

#### ZONA "COMPRE POR CATEGORIA"

| ID | Problema | Causa | Severidade |
|----|----------|-------|-----------|
| CAT-01 | Imagens de categoria com fundos inconsistentes (branco vs transparente) | Imagens uploadadas sem padrão | 🟡 Médio |
| CAT-02 | Scroll horizontal não sinalizado ao usuário | Falta de indicador visual / shadow de borda | 🟢 Baixo |

#### ZONA "MAIS VENDIDAS" / "LANÇAMENTOS"

| ID | Problema | Causa Raiz | Severidade |
|----|----------|-----------|-----------|
| IMG-01 | **207 produtos com imagem quebrada** nos carrosséis | `small_image` e `thumbnail` = `'no_selection'` no banco. O `ImageSync.php` define `IMAGE_ROLES = ['image', 'small_image', 'thumbnail']` corretamente, mas produtos importados antes de 2025 não foram remapeados | 🔴 Crítico |
| IMG-02 | **179 produtos sem nenhuma imagem** (`image` principal ausente) | Produtos chegaram do ERP sem arquivo de imagem correspondente | 🔴 Crítico |
| IMG-03 | Proporção de imagens inconsistente entre cards | Resize do Magento (GD2) não mantém aspect ratio uniforme | 🟡 Médio |
| IMG-04 | **1.418 WebP** de 5.756 JPG = apenas 25% convertidos | Pipeline de conversão WebP parcial ou não rodou para todo o catálogo | 🟡 Médio |

**Fix imediato — IMG-01 (query segura):**
```sql
-- Copiar base_image para small_image e thumbnail onde está 'no_selection'
-- EXECUÇÃO: mysql segura, apenas UPDATE de atributos de imagem
UPDATE catalog_product_entity_varchar cpev_dest
JOIN catalog_product_entity_varchar cpev_src
  ON cpev_dest.entity_id = cpev_src.entity_id
  AND cpev_src.store_id = 0
  AND cpev_src.attribute_id = (
    SELECT attribute_id FROM eav_attribute
    WHERE attribute_code = 'image' AND entity_type_id = 4
  )
SET cpev_dest.value = cpev_src.value
WHERE cpev_dest.attribute_id IN (
  SELECT attribute_id FROM eav_attribute
  WHERE attribute_code IN ('small_image', 'thumbnail') AND entity_type_id = 4
)
AND cpev_dest.store_id = 0
AND (cpev_dest.value = 'no_selection' OR cpev_dest.value = '' OR cpev_dest.value IS NULL)
AND cpev_src.value IS NOT NULL
AND cpev_src.value != 'no_selection'
AND cpev_src.value != '';
```

**Após a query:**
```bash
sudo -u www-data php bin/magento catalog:images:resize
sudo -u www-data php bin/magento indexer:reindex catalog_product_flat catalog_product_attribute
sudo -u www-data php bin/magento cache:flush
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
```

#### ZONA "DESTAQUES"

| ID | Problema | Causa | Severidade |
|----|----------|-------|-----------|
| DEST-01 | Texto claro sobre fundo vermelho com contraste insuficiente | Paleta de cor sem verificação WCAG AA | 🟡 Médio |
| DEST-02 | Altura dos 4 cards inconsistente | CSS `height: auto` sem `min-height` unificado | 🟢 Baixo |

#### ZONA FOOTER

| ID | Problema | Causa | Severidade |
|----|----------|-------|-----------|
| FOOT-01 | Coluna direita com hierarquia visual confusa (newsletter + ícones sociais + pagamento) | Muitos elementos sem separação clara | 🟡 Médio |

---

### 3.3 Bugs Adicionais — Catálogo e SEO

#### BUG-06 — 687/692 Produtos sem Meta Description e Meta Title

**Sintoma:** Páginas de produto aparecem no Google com título e descrição gerados automaticamente (nome do produto + URL), perdendo CTR orgânico.

**Causa:** O módulo `ERPIntegration` não preenche `meta_title` nem `meta_description` durante a sincronização de produtos. O Magento usa o nome e a descrição do produto como fallback, mas sem formatação SEO otimizada.

**Impacto SEO:** Crítico. Com 687 produtos sem meta, o Google gerará snippets genéricos para quase todo o catálogo.

**Fix estrutural** (no `ERPIntegration`): gerar automaticamente `meta_title` e `meta_description` durante o sync:
```php
// No ProductSync.php — após setar o nome do produto:
$product->setMetaTitle($product->getName() . ' — AWA Motos');
$product->setMetaDescription(
    substr(strip_tags((string)$product->getDescription()), 0, 155)
    ?: $product->getName() . '. Compre na AWA Motos com entrega rápida.'
);
```

---

#### BUG-07 — Adapter de Imagem GD2 (Subótimo)

**Sintoma:** Imagens redimensionadas com artefatos de compressão visíveis, especialmente em produtos com fundo escuro.

**Causa:** O adapter GD2 tem qualidade de redimensionamento inferior ao ImageMagick.

**Fix:**
```bash
# Verificar se ImageMagick está disponível
php -r "echo (extension_loaded('imagick') ? 'OK' : 'AUSENTE');"

# Se disponível, trocar para ImageMagick
sudo -u www-data php bin/magento config:set dev/image/default_adapter ImageMagick
sudo -u www-data php bin/magento cache:flush
# Reprocessar imagens
sudo -u www-data php bin/magento catalog:images:resize
```

---

#### BUG-08 — 75% das Imagens não Convertidas para WebP

**Sintoma:** A maioria das imagens do catálogo serve JPG em vez de WebP, perdendo ~30% de tamanho médio de arquivo.

**Dados:** 5.756 JPG vs 1.418 WebP — 75% do catálogo serve formato legado.

**Fix:** rodar a conversão completa:
```bash
sudo -u www-data php bin/magento catalog:images:resize --skip-hidden-images
# Verificar que o Nginx está servindo WebP via content negotiation
grep -i webp /etc/nginx/sites-enabled/*
```

---

#### BUG-09 — Proporção Pedidos/Cancelamentos Invertida

**Dados reais:**
```
canceled:   38 pedidos (52% do total histórico)
processing: 33 pedidos
complete:    1 pedido
pending:     1 pedido
```

**Situação crítica:** mais de 52% dos pedidos foram cancelados. Causas possíveis:
- Falha no fluxo de pagamento (checkout problemático)
- Produtos saindo de estoque durante o checkout
- Integração ERP rejeitando pedidos por dados de cliente inválidos

**Investigação necessária:**
```bash
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" -e "
SELECT so.cancel_reason, COUNT(*) as qty
FROM sales_order so
WHERE so.status = 'canceled'
GROUP BY so.cancel_reason ORDER BY qty DESC LIMIT 10;"
```

---

#### BUG-10 — 69 Quotes Vazias sem Limpeza

**Dados:** 121 quotes totais, 69 sem itens (`items_count = 0`).

**Impacto:** Acúmulo de dados sem utilidade, carga desnecessária na tabela `quote`.

**Fix:** configurar limpeza automática:
```bash
sudo -u www-data php bin/magento config:set sales/quote/delete_quote_after 30
sudo -u www-data php bin/magento cache:flush
```

---

## 4. Interações, Animações e Padronização Visual

> Esta seção define o padrão de comportamento para todos os componentes interativos do tema — como devem se mover, responder e transicionar. É a base para uma experiência coerente em toda a plataforma.

---

### 4.1 Menu Vertical com Overlay

**Estado atual:** O menu vertical abre mas o overlay (escurecimento do restante da página) é inconsistente — em alguns breakpoints não aparece ou aparece com z-index errado.

**Comportamento esperado (padrão de grandes e-commerces):**
1. Ao abrir o menu vertical → overlay preto semitransparente cobre o restante da página
2. Clicar fora do menu ou no overlay → fecha o menu com animação de saída
3. Pressionar `Esc` → fecha o menu
4. `aria-expanded` é atualizado corretamente para leitores de tela

**Implementação correta do overlay:**
```less
// _awa-vertical-menu.less — overlay do menu vertical

// Overlay: existe no DOM, mas invisível por padrão
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    pointer-events: none;
    z-index: calc(var(--awa-z-overlay, 100) - 1);
    transition: background var(--awa-duration-base, 200ms) var(--awa-ease-default);
}

// Quando o menu está aberto, o overlay aparece
body.awa-vmenu-open::before {
    background: rgba(0, 0, 0, 0.5);
    pointer-events: auto;
    cursor: pointer;
}

// O menu desliza de cima para baixo ao abrir
.navigation.verticalmenu .togge-menu {
    transform: translateY(-8px);
    opacity: 0;
    pointer-events: none;
    transition:
        transform var(--awa-duration-base, 200ms) var(--awa-ease-default),
        opacity var(--awa-duration-fast, 150ms) var(--awa-ease-default);
}

.navigation.verticalmenu .togge-menu.menu-open {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
```

**JS necessário** (`awa-vertical-menu-overlay.js`):
```javascript
// Ao abrir o menu: adicionar body.awa-vmenu-open
// Ao clicar no overlay: fechar menu e remover a classe
document.querySelector('body::before')  // via delegação no body
    .addEventListener('click', closeVerticalMenu);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeVerticalMenu();
});
```

**Regra:** o overlay deve ter z-index sempre **abaixo** do menu e **acima** do conteúdo da página.

---

### 4.2 Minicart como Dropdown

**Estado atual:** O minicart usa `dropdownDialog` do jQuery UI — funciona mas não tem animação de entrada/saída definida.

**Comportamento esperado:**
- Abre com slide-down + fade (não um "teleporte")
- Fecha ao clicar fora (já funciona via `dropdownDialog`)
- Em mobile: abre como drawer lateral (slide da direita)
- Mostra contagem de itens atualizada em tempo real via Knockout.js
- Overlay semitransparente ao abrir em mobile

**CSS de transição:**
```less
// _awa-header-stack.less — transição do dropdown minicart

.minicart-wrapper .mage-dropdown-dialog.ui-dialog {
    // Posicionamento
    top: 100% !important;
    right: 0 !important;
    left: auto !important;

    // Animação entrada
    transform-origin: top right;
    animation: awaMiniCartOpen var(--awa-duration-base, 200ms) var(--awa-ease-default) forwards;
}

@keyframes awaMiniCartOpen {
    from {
        opacity: 0;
        transform: translateY(-8px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

// Mobile: drawer lateral
@media (max-width: @awa-bp-md) {
    .minicart-wrapper .mage-dropdown-dialog.ui-dialog {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        height: 100vh !important;
        max-height: 100vh !important;
        transform-origin: right center;
        animation: awaMiniCartOpenMobile var(--awa-duration-base, 200ms) var(--awa-ease-default) forwards;
    }

    @keyframes awaMiniCartOpenMobile {
        from { transform: translateX(100%); }
        to   { transform: translateX(0); }
    }
}
```

---

### 4.3 Header — Login / Cadastre-se ao Lado do Minicart

**Estado atual:** Os links de conta ficam em posição separada do minicart, sem agrupamento visual claro.

**Padrão de grande porte (referência: Amazon, Shopee, Magazine Luiza):**
```
[Logo]  [Busca                    ]  [Conta ▾]  [Favoritos]  [🛒 2]
```

**Estrutura HTML esperada:**
```html
<div class="awa-header-actions">
    <!-- Conta: mostra nome se logado, ou "Login / Cadastre-se" se visitante -->
    <div class="awa-header-account" data-bind="scope: 'customer'">
        <a href="/b2b/account/login" class="awa-header-account__trigger">
            <svg><!-- ícone usuário --></svg>
            <span class="awa-header-account__label">
                <!-- ko if: customer().fullname -->
                <span data-bind="text: customer().firstname"></span>
                <!-- /ko -->
                <!-- ko ifnot: customer().fullname -->
                <span>Entrar</span>
                <!-- /ko -->
            </span>
        </a>
    </div>

    <!-- Minicart: ao lado imediato do ícone de conta -->
    <div class="minicart-wrapper">
        <!-- Magento minicart padrão -->
    </div>
</div>
```

**CSS de agrupamento:**
```less
.awa-header-actions {
    display: flex;
    align-items: center;
    gap: var(--awa-s-4);  // 16px entre ícones
    flex-shrink: 0;
}

.awa-header-account__trigger {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--awa-s-1);  // 4px entre ícone e label
    font-size: var(--awa-text-xs, 11px);
    color: var(--awa-header-text);
    text-decoration: none;
    
    &:hover {
        color: var(--awa-primary);
    }
}
```

---

### 4.4 Carrosséis — Padrão de Movimento

**Estado atual:** Mix de Swiper (novo) e Owl Carousel (legado). Alguns carrosséis usam Owl com dependência em jQuery. Os efeitos de transição não são consistentes entre seções.

**Padrão único: Swiper.js para todos os carrosséis**

Por quê Swiper:
- Sem dependência de jQuery
- CSS-first (usa `transform` e `will-change` nativamente)
- Suporte a `IntersectionObserver` para lazy init
- Touch/swipe nativo no mobile
- A11y built-in (aria-labels, keyboard navigation)

**Configuração padrão AWA:**
```javascript
// web/js/awa-carousel-config.js — configuração base para todos os carrosséis

const AWA_CAROUSEL_DEFAULTS = {
    // Navegação
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },

    // Movimento — suave e consistente
    speed: 400,
    easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',  // ease-out

    // Touch
    threshold: 10,
    resistance: true,
    resistanceRatio: 0.85,

    // A11y
    a11y: {
        prevSlideMessage: 'Slide anterior',
        nextSlideMessage: 'Próximo slide',
    },

    // Performance
    lazy: { loadPrevNext: true },
    watchSlidesProgress: true,
    observer: true,  // reinit ao redimensionar
};

// Carrossel de produtos (mais vendidos, lançamentos)
const AWA_PRODUCT_CAROUSEL = {
    ...AWA_CAROUSEL_DEFAULTS,
    breakpoints: {
        0:    { slidesPerView: 1.5, spaceBetween: 12 },
        480:  { slidesPerView: 2.2, spaceBetween: 16 },
        768:  { slidesPerView: 3,   spaceBetween: 20 },
        1024: { slidesPerView: 4,   spaceBetween: 24 },
        1280: { slidesPerView: 5,   spaceBetween: 24 },
    },
};

// Hero slider principal
const AWA_HERO_SLIDER = {
    ...AWA_CAROUSEL_DEFAULTS,
    autoplay: { delay: 5000, disableOnInteraction: false, pauseOnMouseEnter: true },
    loop: true,
    effect: 'fade',
    fadeEffect: { crossFade: true },
};
```

**Migração Owl → Swiper:** qualquer template que ainda usa `owl-carousel` deve ser substituído. Identificar:
```bash
grep -r "owl-carousel\|owlCarousel\|owl\.carousel" \
  app/design/frontend/AWA_Custom/ayo_home5_child --include="*.phtml" --include="*.js" -l
```

---

### 4.5 Slider Hero — Boas Práticas

**O que faz diferença no hero de grandes plataformas:**

1. **CLS zero:** a altura do slider deve ser definida via CSS antes do JS carregar
2. **LCP otimizado:** a primeira imagem do slide deve usar `<img loading="eager" fetchpriority="high">`
3. **Autoplay com pause:** parar no hover e retomar ao sair
4. **Indicadores de slide** visíveis (dots ou thumbnails) — o usuário sabe quantos slides existem
5. **Swipe no mobile** funcional sem delay
6. **Texto legível:** sobreposição com `text-shadow` ou fundo semitransparente — nunca texto claro sobre imagem clara sem contraste

```less
// _awa-slideshow.less — estrutura base do hero
.awa-hero-slider {
    // Prevenir CLS: altura reservada antes da imagem carregar
    min-height: clamp(300px, 40vw, 540px);
    background: var(--awa-gray-100);

    // A imagem de fundo não deve causar reflow
    .swiper-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }

    // Sobreposição de texto: sempre legível
    .slide-caption {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        padding: var(--awa-s-6) var(--awa-s-8);
        background: linear-gradient(to top, rgba(0,0,0,.6) 0%, transparent 100%);
        color: #fff;
    }
}
```

---

### 4.6 Sistema de Animações — Tokens e Consistência

**Estado atual:** As durações e easings estão definidas em `_awa-effects-system.less` mas nem todos os componentes os utilizam — muitos ainda têm `transition: all 0.3s ease` hardcoded.

**Escala de tempo AWA (já definida, precisa ser aplicada):**

| Token | Valor | Uso |
|---|---|---|
| `--awa-duration-instant` | 50ms | Feedback imediato (ripple click) |
| `--awa-duration-fast` | 150ms | Hover de cor, ícones |
| `--awa-duration-base` | 200ms | Dropdowns, tooltips, transição padrão |
| `--awa-duration-slow` | 300ms | Modals, drawers, overlays |
| `--awa-duration-xl` | 500ms | Hero, animações de entrada de seção |
| `--awa-ease-default` | `cubic-bezier(.4,0,.2,1)` | Material Design standard |
| `--awa-ease-enter` | `cubic-bezier(0,0,.2,1)` | Elementos entrando na tela |
| `--awa-ease-exit` | `cubic-bezier(.4,0,1,1)` | Elementos saindo da tela |

**Regra:** nunca escrever `transition: all 0.3s ease` — usar os tokens:
```less
// ERRADO
.btn { transition: all 0.3s ease; }

// CORRETO
.btn {
    transition:
        background-color var(--awa-duration-fast) var(--awa-ease-default),
        box-shadow       var(--awa-duration-base) var(--awa-ease-default),
        transform        var(--awa-duration-fast) var(--awa-ease-default);
}
```

---

### 4.7 Hover States — Padrão Unificado

Todo elemento interativo deve ter **3 estados** definidos:

```less
// _awa-interaction-states.less — padrão para todos os componentes clicáveis

// Mixin reutilizável
.awa-interactive() {
    cursor: pointer;
    transition:
        background-color var(--awa-duration-fast) var(--awa-ease-default),
        color            var(--awa-duration-fast) var(--awa-ease-default),
        box-shadow       var(--awa-duration-base) var(--awa-ease-default),
        transform        var(--awa-duration-fast) var(--awa-ease-default);

    // Hover: leve elevação
    &:hover {
        transform: translateY(-1px);
        box-shadow: var(--awa-shadow-md);
    }

    // Active/pressed: feedback tátil
    &:active {
        transform: translateY(0) scale(0.98);
        box-shadow: var(--awa-shadow-sm);
    }

    // Focus: acessibilidade WCAG 2.4.7
    &:focus-visible {
        outline: 2px solid var(--awa-primary);
        outline-offset: 2px;
        box-shadow: var(--awa-shadow-focus);
    }
}

// Cards de produto
.product-item { .awa-interactive(); }

// Botões
.btn, .action.primary, .action.secondary { .awa-interactive(); }

// Links de categoria
.category-item a { .awa-interactive(); }
```

---

### 4.8 Skeleton Loading — Ausência de Imagens sem Layout Shift

Em vez de mostrar a imagem padrão quebrada ou um espaço em branco, usar skeleton loading:

```less
// _awa-loading-states.less
.product-image-wrapper {
    position: relative;
    overflow: hidden;
    background: var(--awa-gray-100);

    // Skeleton shimmer enquanto carrega
    &.awa-loading::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(
            90deg,
            transparent 25%,
            rgba(255,255,255,.4) 50%,
            transparent 75%
        );
        background-size: 200% 100%;
        animation: awa-skeleton-shimmer 1.5s infinite;
    }
}

@keyframes awa-skeleton-shimmer {
    from { background-position: 200% 0; }
    to   { background-position: -200% 0; }
}
```

---

### 4.9 Quick View — Padrão de Modal

O modal de Quick View (já usa Fancybox via Rokanthemes) deve seguir:

1. **Entrada:** `scale(0.95) + opacity:0` → `scale(1) + opacity:1` em 250ms
2. **Saída:** inverso em 150ms
3. **Backdrop:** `rgba(0,0,0,.5)` com blur `backdrop-filter: blur(4px)` em browsers modernos
4. **Foco trapping:** o foco do teclado não pode sair do modal enquanto ele está aberto (WCAG 2.1)
5. **Scroll lock:** `body` recebe `overflow: hidden` enquanto o modal está aberto

---

### 4.10 Padronização Visual — Checklist de Consistência

Antes de qualquer componente ser considerado "pronto", verificar:

| Critério | Ferramenta de verificação |
|---|---|
| Cores via token (`var(--awa-*)`) | `grep "#[0-9a-f]" arquivo.less` — deve retornar 0 |
| Espaçamentos via escala (`var(--awa-s-*)`) | Inspetor → valores hardcoded em px |
| Tipografia via escala (`var(--awa-text-*)`) | `grep "font-size:" arquivo.less` — deve retornar 0 |
| Transições via tokens | `grep "0\.3s ease\|0\.2s ease" arquivo.less` |
| Focus ring visível | Tab pela página — todo item focável deve ter outline visível |
| Contraste WCAG AA (4.5:1 texto) | [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/) |
| Mobile sem overflow horizontal | DevTools → `document.body.scrollWidth > window.innerWidth` |
| Sem texto sem `escapeHtml()` | Revisão manual de PHMLTs com `echo $var` |

---

## 5. O que uma Plataforma de Grande Porte Precisa

> Referências: Magazine Luiza, Shopee BR, Americanas, Amazon BR. Esta seção define o gap entre o estado atual e o padrão de plataforma madura.

---

### 5.1 Performance — Core Web Vitals Alvo

| Métrica | Alvo | Como medir |
|---|---|---|
| **LCP** (Largest Contentful Paint) | < 2.5s | PageSpeed Insights / Lighthouse |
| **CLS** (Cumulative Layout Shift) | < 0.1 | Layout stable antes de JS carregar |
| **INP** (Interaction to Next Paint) | < 200ms | Chrome DevTools Performance |
| **TTFB** (Time to First Byte) | < 800ms | Nginx + PHP-FPM + Redis FPC |
| **FCP** (First Contentful Paint) | < 1.8s | Critical CSS inline |

**Checklist de performance:**
- [ ] Critical CSS (above-fold) inline no `<head>` — máximo 14KB
- [ ] Fontes críticas com `preload` incondicional
- [ ] Imagens hero com `loading="eager" fetchpriority="high"`
- [ ] Todas as outras imagens com `loading="lazy"`
- [ ] JS principal em `defer` ou `type="module"`
- [ ] Redis FPC habilitado e funcionando
- [ ] Nginx servindo gzip/brotli nos assets
- [ ] Cache de imagens com TTL adequado (1 ano para assets com hash)

### 5.2 Acessibilidade — WCAG 2.1 AA (Obrigatório LGPD + Lei Brasileira)

| Critério | Implementação |
|---|---|
| Todas as imagens com `alt` descritivo | Magento já força — verificar produtos importados |
| Contraste de cor mínimo 4.5:1 | Textos sobre vermelho/fundo escuro |
| Focus ring visível em todos os elementos interativos | `focus-visible` polyfill + `:focus-visible` CSS |
| Skip links funcionais | Já implementado via `awa-a11y-nav-links.phtml` |
| ARIA labels em carrosséis | Swiper tem built-in |
| Modais com foco trapping | Fancybox e `dropdownDialog` precisam de verificação |
| Formulários com `label` associado | Verificar checkout e B2B forms |

### 5.3 SEO Técnico

| Item | Estado | Prioridade |
|---|---|---|
| `meta_title` em todos os produtos | ❌ 687/692 ausentes | 🔴 Crítico |
| `meta_description` em todos os produtos | ❌ 687/692 ausentes | 🔴 Crítico |
| Canonical tags em PLP + PDP | ✅ Habilitado via config | OK |
| URL rewrites ativos | ✅ | OK |
| Schema.org JSON-LD | ✅ Módulo SchemaOrg ativo | OK |
| Open Graph tags | ✅ Módulo SchemaOrg + og-meta.phtml | OK |
| Sitemap XML gerado | Verificar via `/sitemap.xml` | 🟡 Verificar |
| Robots.txt correto | Verificar crawl budget | 🟡 Verificar |
| Imagens com `alt` em produtos | Depende do ERP sync | 🟡 Médio |
| Breadcrumbs estruturados | Verificar via Schema.org | 🟡 Médio |

### 5.4 UX Patterns de Grande Plataforma

| Pattern | Status AWA | Referência |
|---|---|---|
| Busca preditiva com sugestões | ✅ Mirasvit Search | Shopee |
| Quick View sem sair da listagem | ✅ Rokanthemes QuickView | Amazon |
| Minicart com preview de itens | ✅ com dropdown | Magalu |
| Menu vertical com overlay | 🟡 Overlay inconsistente | Shopee |
| Carrossel de produtos com motion | 🟡 Mix Owl/Swiper | Americanas |
| Skeleton loading em cards | ❌ Não implementado | Shopee |
| Filtros de PLP sem reload | ✅ LayeredAjax | Todos |
| Lazy load de imagens | 🟡 Parcial | Universal |
| Toast/notificações inline | 🟡 Magento padrão | Melhorar |
| Wishlist acessível sem login | Verificar | Magalu |
| Comparação de produtos | ✅ Nativo Magento | — |
| Avaliações de produtos | Verificar | Shopee |

### 5.5 Segurança e Confiança Visual

| Elemento | Implementação AWA |
|---|---|
| Selos de segurança no checkout | Verificar `home_security_seals` (desativado) |
| HTTPS + indicador visual | Nginx + certificado Let's Encrypt |
| Cookie consent LGPD | ✅ Módulo CookieConsent |
| Política de privacidade linkada | Verificar footer |
| CNPJ visível no footer | Verificar — obrigatório por lei |
| SAC / telefone no header | Verificar header hotline |
| Tempo estimado de entrega no PDP | Verificar se o B2B OfflinePayment exibe |

### 5.6 Mobile First — Checklist

| Critério | Verificação |
|---|---|
| Touch targets ≥ 44×44px | Todos os botões e links |
| Sem overflow horizontal em 375px | `document.body.scrollWidth` |
| Menu mobile funcional com swipe | Drawer lateral com gesture |
| Formulários com teclado correto | `type="tel"` para telefone, `type="email"` para email |
| Zoom em inputs desativado (iOS) | `font-size: 16px` em inputs — nunca abaixo disso |
| CTA visível above-fold no mobile | Botão "Adicionar ao Carrinho" sem scroll |
| Imagens com `srcset` para mobile | Magento gera automaticamente |

### 5.7 Monitoramento e Alertas

O que uma plataforma madura monitora automaticamente:

```bash
# Verificações semanais via cron
# 1. Produtos sem imagem
mysql ... "SELECT COUNT(*) FROM ... WHERE small_image = 'no_selection'"
# Alerta se > 0

# 2. Taxa de cancelamento
mysql ... "SELECT ROUND(canceled/total*100,1) FROM ..."
# Alerta se > 20%

# 3. Indexers com status != Ready
php bin/magento indexer:status | grep -v Ready
# Alerta se encontrar

# 4. Exception log crescendo
wc -l var/log/exception.log
# Alerta se > 100 linhas novas no dia

# 5. FPC hit rate
redis-cli ... INFO stats | grep "keyspace_hits\|keyspace_misses"
# Alerta se hit rate < 60%

# 6. Espaço em disco
df -h pub/media/
# Alerta se > 85%
```

---

## 6. Referência Técnica — Boas Práticas CSS e Magento

> Guia de referência para desenvolvedores trabalhando no tema. Use esta seção antes de qualquer edição CSS/LESS.

### 2.1 Magento 2 — Arquitetura de Tema

**Hierarquia obrigatória:**
```
vendor/magento/theme-frontend-blank          ← core (nunca editar)
  └── app/design/frontend/Rokanthemes/ayo_home5/   ← tema pai (nunca editar)
        └── app/design/frontend/AWA_Custom/ayo_home5_child/  ← único ponto de edição
```

**Regra de override de template:**
```bash
# Copiar do pai para o filho com estrutura idêntica
cp [tema_pai]/[Vendor_Module]/templates/[file].phtml \
   [tema_filho]/[Vendor_Module]/templates/[file].phtml
# Editar apenas a cópia no filho
```

**RequireJS — mergeamento nativo:**
O Magento faz merge automático de todos os `requirejs-config.js` na hierarquia. O filho deve apenas sobrescrever o que difere — nunca redeclarar dependências que o pai já define.

### 2.2 Arquitetura LESS Saudável (SMACSS + BEM + Design Tokens)

```
web/css/source/
├── _tokens.less              ← variáveis primitivas (nunca use hex fora daqui)
├── _design-system.less       ← CSS custom properties (:root)
├── _base.less                ← reset, body, tipografia base
├── _layout.less              ← grid, containers, breakpoints
├── _components.less          ← header, footer, nav, produto, checkout
├── _pages.less               ← sobrescritas page-específicas (PLP, PDP, B2B)
├── _utilities.less           ← helpers, a11y, visibility
└── _extend.less              ← entry point: apenas @import dos 7 acima
```

**Regra:** Se você precisa criar um 8º arquivo, é sinal de que um dos 7 existentes precisa ser expandido — não que precisa de um novo arquivo.

### 2.3 Especificidade — A Pirâmide Correta

```
:root { --token }              ← nível 0 (CSS custom properties)
element { }                    ← nível 1 (elementos base)
.class { }                     ← nível 2 (componentes)
.parent .child { }             ← nível 3 (composição)
.page-type .component { }      ← nível 4 (page-specific)
[data-attribute] { }           ← nível 5 (estado/variação)
!important                     ← NUNCA (exceto reset de terceiro necessário)
```

A presença de 32.826 `!important` indica que a pirâmide está invertida — isso precisa ser revertido sistematicamente.

### 2.4 Design Tokens — Único Source of Truth

```less
// CORRETO — _tokens.less
@awa-red: #e8251e;
@awa-red-dark: darken(@awa-red, 10%);

// CORRETO — _design-system.less
:root {
  --awa-red: #e8251e;
  --awa-primary: var(--awa-red);
}

// ERRADO — qualquer outro arquivo
.botao { background: #e8251e; }     // hex hardcoded = proibido
.botao { background: @minha-cor; }  // variável local = proibido
```

### 2.5 Performance CSS — Core Web Vitals

**Critical CSS (inline no `<head>`):** apenas o acima do fold inicial — ~4KB máximo.

**Carregamento assíncrono correto:**
```html
<!-- CORRETO: preload + swap -->
<link rel="preload" href="bundle.css" as="style" 
      onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="bundle.css"></noscript>
```

**Regra de ouro:** máximo 2 requisições CSS bloqueantes no `<head>`. O resto via preload/async.

### 2.6 Layout XML — Extensão, Não Substituição

```xml
<!-- ERRADO: remove + recria tudo -->
<referenceContainer name="header" remove="true"/>

<!-- CORRETO: cirurgia mínima -->
<referenceBlock name="header.logo">
    <action method="setTemplate">
        <argument name="template" xsi:type="string">
            GrupoAwamotos_Theme::html/logo.phtml
        </argument>
    </action>
</referenceBlock>
```

### 2.7 Imagens de Produto — as 3 Roles são Obrigatórias

O Magento usa 3 roles de imagem com funções distintas:

| Role | Onde é usado | Tamanho típico |
|------|-------------|---------------|
| `image` | PDP (zoom, lightbox) | 800–1200px |
| `small_image` | PLP, carrosséis, busca | 240–400px |
| `thumbnail` | Minicart, wishlist, orders | 75–100px |

**Regra:** toda importação de produto via ERP ou admin deve definir as 3 roles. Se a imagem for a mesma, atribuir o mesmo arquivo às 3.

**Verificação mensal:**
```sql
SELECT COUNT(*) FROM catalog_product_entity_varchar
WHERE attribute_id IN (
  SELECT attribute_id FROM eav_attribute
  WHERE attribute_code IN ('small_image', 'thumbnail') AND entity_type_id = 4
)
AND (value = 'no_selection' OR value = '' OR value IS NULL)
AND store_id = 0;
-- Deve retornar 0
```

### 2.8 SEO de Produto — Template Obrigatório

Todo produto deve ter:
- `meta_title`: `{Nome} — AWA Motos` (máx 60 chars)
- `meta_description`: descrição funcional com palavra-chave (máx 155 chars)
- `url_key`: slug em português, sem acentos (ex: `guidao-cb-300-texturizado`)
- `description`: texto descritivo real (não apenas especificações técnicas)

### 2.9 WebP como Formato Padrão

Com ImageMagick configurado, o Magento serve WebP automaticamente via negociação de conteúdo HTTP. Benefícios:
- ~30% menor que JPEG equivalente
- Suporte em todos os browsers modernos (Chrome, Firefox, Safari 14+, Edge)
- Fallback automático para JPEG/PNG em browsers antigos

**Verificação:**
```bash
curl -H "Accept: image/webp" -I https://awamotos.com/pub/media/catalog/product/cache/.../image.jpg
# Deve retornar Content-Type: image/webp
```

### 2.10 Monitoramento de Cancelamentos

Uma taxa de cancelamento > 30% indica problema no fluxo de compra. Monitorar semanalmente:
```sql
SELECT 
  DATE_FORMAT(created_at, '%Y-%m') as mes,
  SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as cancelados,
  SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as completos,
  COUNT(*) as total,
  ROUND(SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as pct_cancelamento
FROM sales_order
GROUP BY mes ORDER BY mes DESC LIMIT 6;
```

### 2.11 Proteção contra regressão (CI mínimo)

Toda alteração de layout/CSS deve passar por:
1. `tail -5 var/log/exception.log` → zero novas entradas
2. Screenshot das 3 páginas críticas: Home, PLP, PDP
3. Teste mobile 375px: sem overflow horizontal
4. Console do browser: sem erros de JS/RequireJS

---

## 7. Plano de Correção em Fases

> **Princípio de execução:** cada fase é deployável de forma independente. Nenhuma fase bloqueia a produção. Comece sempre pela menor mudança que traz o maior ganho.

---

### FASE 0 — Baseline, Proteção e Fixes Imediatos (1-2 dias)
**Objetivo:** criar uma linha de base rastreável E resolver os bugs de deploy mais críticos antes de qualquer mudança estrutural.

#### 0.1 Snapshot de métricas
```bash
# Executar e salvar saída
./scripts/visual-debt-audit.sh > /tmp/baseline-jun2026.txt
grep -r "!important" web/css/source/ --include="*.less" | wc -l >> /tmp/baseline-jun2026.txt
ls web/css/*.css | wc -l >> /tmp/baseline-jun2026.txt
```

#### 0.2 Commit de baseline limpo
```bash
git add -A
git commit -m "chore: snapshot theme state before CSS debt cleanup"
git tag baseline-jun2026
```

#### 0.3 Criar branch de trabalho
```bash
git checkout -b chore/theme-css-debt-cleanup
```

#### 0.4 Screenshot baseline das páginas críticas
Capturar desktop + mobile (375px) de:
- Home (`/`)
- PLP (`/pecas-para-motos`)
- PDP (qualquer produto)
- Checkout (`/checkout/cart`)
- Login B2B

Salvar em `tests/e2e/snapshots/baseline/`.

#### 0.5 Fix imediato — remover arquivos `.orig` do preprocessed (BUG-01 Camada 2)

```bash
# Remover arquivos stale AGORA — causa regressão a cada deploy
sudo -u www-data rm -f \
  var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/*.orig* \
  var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/*.bak*

# Confirmar que foram removidos
ls var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/
```

#### 0.6 Criar script de deploy seguro em `scripts/deploy-theme.sh`

```bash
#!/bin/bash
# scripts/deploy-theme.sh
# USO: sudo bash scripts/deploy-theme.sh
# Garante deploy completo sem regressão de BUG-01

set -e

THEME="AWA_Custom/ayo_home5_child"
REDIS_HOST="::1"
REDIS_PASS="Aw4R3d1s2026Sec"
PHP="sudo -u www-data php"
MAGENTO="bin/magento"

echo "→ [1/5] Limpando var/view_preprocessed do tema..."
sudo -u www-data rm -rf "var/view_preprocessed/pub/static/frontend/AWA_Custom/"

echo "→ [2/5] Compilando static content..."
$PHP $MAGENTO setup:static-content:deploy pt_BR en_US -f --theme "$THEME"

echo "→ [3/5] Flush Magento cache..."
$PHP $MAGENTO cache:flush

echo "→ [4/5] Flush Redis DB1 (block/layout/config)..."
redis-cli -h "$REDIS_HOST" -a "$REDIS_PASS" -n 1 FLUSHDB

echo "→ [5/5] Flush Redis DB2 (FPC — HTML completo)..."
redis-cli -h "$REDIS_HOST" -a "$REDIS_PASS" -n 2 FLUSHDB

echo "✅ Deploy completo. Verifique:"
tail -3 var/log/exception.log
```

**Critério de saída:** branch criada, métricas salvas, screenshots tiradas, arquivos `.orig` removidos, script de deploy criado.

---

### FASE 0B — Fixes de Catálogo e Dados (execução paralela à Fase 0)
**Objetivo:** corrigir bugs de dados que já estão impactando a loja agora, independente do trabalho CSS.

#### 0B.1 Fix de imagens quebradas — 207 produtos (BUG IMG-01)
```bash
# Executar a query de reparo de imagens (ver Seção 3.2 — IMG-01)
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" < /tmp/fix_image_roles.sql

# Reprocessar cache de imagens
sudo -u www-data php bin/magento catalog:images:resize --skip-hidden-images

# Reindexar produto flat
sudo -u www-data php bin/magento indexer:reindex catalog_product_flat catalog_product_attribute

# Flush
sudo -u www-data php bin/magento cache:flush
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB
```

#### 0B.2 Trocar adapter para ImageMagick (BUG-07)
```bash
php -r "echo extension_loaded('imagick') ? 'ImageMagick OK' : 'GD2 fallback';"
sudo -u www-data php bin/magento config:set dev/image/default_adapter ImageMagick
sudo -u www-data php bin/magento cache:flush
```

#### 0B.3 Configurar limpeza automática de quotes (BUG-10)
```bash
sudo -u www-data php bin/magento config:set sales/quote/delete_quote_after 30
sudo -u www-data php bin/magento cache:flush
```

#### 0B.4 Investigar cancelamentos (BUG-09)
```bash
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" -e "
SELECT cancel_reason, COUNT(*) FROM sales_order
WHERE status = 'canceled' GROUP BY cancel_reason ORDER BY 2 DESC;"
```

**Critério de saída:** zero imagens quebradas nos carrosséis, adapter atualizado, cancelamentos investigados.

---

### FASE 1 — Limpeza de Artefatos Mortos (2-3 dias)
**Objetivo:** remover o lixo sem risco — arquivos que não afetam produção.

#### 1.1 Remover arquivos `.bak`, `.orig`, `.bak-*`
```bash
# Identificar
find app/design/frontend/AWA_Custom/ayo_home5_child \
  -name "*.bak*" -o -name "*.orig*" | sort

# Remover após confirmar que não são importados
# (grep pelo nome antes de deletar)
```

#### 1.2 Mover CSS com data no nome para `_deprecated/`
Arquivos como `awa-bugfix-terminal-2026-06-12.css`, `awa-home-standardize-pass20-2026-06-10.css` etc. que já foram consolidados em partials LESS não devem ficar no diretório raiz.

```bash
mkdir -p web/css/_deprecated/
# Mover arquivos com padrão awa-*-YYYY-MM-DD.css
# e awa-*-pass[0-9]+.css para _deprecated/
```

#### 1.3 Remover documentação de crise do source
Arquivos `__ANALISE_DUPLICACOES_2026-06-05.md`, `__PLANO_EMERGENCIA_TIMEOUT_2026-06-05.md` etc. no diretório do tema pertencem ao histórico Git, não ao working tree.

```bash
# Mover para docs/ na raiz do projeto ou remover
find app/design/frontend/AWA_Custom/ayo_home5_child \
  -name "__*.md" | sort
```

#### 1.4 Identificar LESS partials órfãos
```bash
# Partials que existem mas NÃO são importados em _extend.less
python3 - <<'EOF'
import os, re

source_dir = "app/design/frontend/AWA_Custom/ayo_home5_child/web/css/source"
extend_file = f"{source_dir}/_extend.less"

with open(extend_file) as f:
    extend_content = f.read()

all_partials = {f for f in os.listdir(source_dir) if f.startswith('_') and f.endswith('.less')}
imported = {re.sub(r"[@import\s(once)\s'\"]+|['\";]", '', l).strip() 
            for l in extend_content.splitlines() 
            if '@import' in l and not l.strip().startswith('//')}

orphans = all_partials - {f"_{i}.less" if not i.startswith('_') else f"{i}.less" 
                           for i in imported}
for o in sorted(orphans):
    print(o)
EOF
```

**Critério de saída:** zero arquivos `.bak`/`.orig`, documentos de crise removidos, lista de partials órfãos mapeada.

---

### FASE 2 — Consolidação dos Tokens (3-5 dias)
**Objetivo:** estabelecer um único source of truth para cores, tipografia e espaçamento.

#### 2.1 Auditar variáveis duplicadas
```bash
# Variáveis @awa-* declaradas em múltiplos arquivos
grep -r "^@awa-" web/css/source/ --include="*.less" -h | sort | uniq -d
```

#### 2.2 Consolidar em `_tokens.less` e `_design-system.less`

**Estrutura alvo:**
```less
// _tokens.less — ÚNICA definição de todas as variáveis LESS
// Regra: se você precisar de uma cor/tamanho/espaçamento, procure aqui primeiro

// Cores
@awa-red:            #e8251e;
@awa-red-dark:       darken(@awa-red, 10%);
@awa-black:          #1a1a1a;
@awa-white:          #ffffff;
@awa-gray-100:       #f8f8f8;
@awa-gray-200:       #eeeeee;
@awa-gray-500:       #9e9e9e;
@awa-gray-700:       #4a4a4a;

// Tipografia
@awa-font-primary:   'Rajdhani', sans-serif;
@awa-font-body:      'Roboto', sans-serif;
@awa-text-sm:        0.875rem;    // 14px
@awa-text-base:      1rem;        // 16px
@awa-text-lg:        1.125rem;    // 18px
@awa-text-xl:        1.25rem;     // 20px

// Espaçamento (escala 4px)
@awa-space-1:        4px;
@awa-space-2:        8px;
@awa-space-3:        12px;
@awa-space-4:        16px;
@awa-space-6:        24px;
@awa-space-8:        32px;

// Containers
@awa-page-max:       1440px;
@awa-page-pad:       24px;
@awa-page-pad-mobile: 12px;

// Breakpoints
@awa-bp-sm:          576px;
@awa-bp-md:          768px;
@awa-bp-lg:          1024px;
@awa-bp-xl:          1280px;
@awa-bp-2xl:         1440px;

// Borders
@awa-radius-sm:      4px;
@awa-radius-md:      8px;
@awa-radius-lg:      16px;
@awa-radius-full:    9999px;

// Shadows
@awa-shadow-sm:      0 1px 3px rgba(0,0,0,.08);
@awa-shadow-md:      0 4px 12px rgba(0,0,0,.12);
@awa-shadow-lg:      0 8px 24px rgba(0,0,0,.16);
```

#### 2.3 Eliminar hex hardcoded
```bash
# Encontrar hex hardcoded fora de _tokens.less
grep -r "#[0-9a-fA-F]\{3,6\}" web/css/source/ --include="*.less" \
  --exclude="_tokens.less" -l | head -20
```

Substituir sistematicamente pelo token correspondente.

#### 2.4 CSS Custom Properties para runtime

```less
// _design-system.less
:root {
  // Semânticos (usados em CSS/PHTML)
  --awa-primary:     @{awa-red};
  --awa-primary-dark: @{awa-red-dark};
  --awa-surface:     @{awa-white};
  --awa-text:        @{awa-black};
  --awa-text-muted:  @{awa-gray-500};
  --awa-border:      @{awa-gray-200};
  
  // Espaçamento runtime
  --awa-gap-xs:      @{awa-space-1};
  --awa-gap-sm:      @{awa-space-2};
  --awa-gap-md:      @{awa-space-4};
  --awa-gap-lg:      @{awa-space-6};
  --awa-gap-xl:      @{awa-space-8};
}
```

**Critério de saída:** zero variáveis duplicadas, zero hex hardcoded fora de `_tokens.less`, CSS custom properties definidas uma única vez.

---

### FASE 3 — Reestruturação do _extend.less (5-7 dias)
**Objetivo:** reduzir de 115 imports para ≤ 15, mantendo o output CSS idêntico.

> ⚠️ Esta é a fase de maior risco. Trabalhar em branch isolada com testes visuais a cada merge.

#### 3.1 Mapeamento de dependências

Para cada um dos 115 imports ativos, documentar:
- Área de responsabilidade (header, PLP, PDP, B2B, home, etc.)
- Conflitos com outros partials (regras sobrepostas)
- Candidato ao partial destino na estrutura alvo

#### 3.2 Estrutura alvo do `_extend.less`

```less
// =============================================
// AWA MOTOS — DESIGN SYSTEM ENTRY POINT
// Máximo 15 imports. Se precisar de mais, 
// expanda um dos partials abaixo.
// =============================================

// 1. Foundation (tokens, reset, design system)
@import (once) '_foundation';      // tokens + design-system + reset

// 2. Layout (grid, containers, breakpoints)
@import (once) '_layout';

// 3. Tipografia
@import (once) '_typography';

// 4. Componentes globais (header, footer, nav)
@import (once) '_components-global';

// 5. Componentes de produto (card, PLP, PDP)
@import (once) '_components-catalog';

// 6. Fluxos (checkout, cart, B2B)
@import (once) '_components-commerce';

// 7. Páginas específicas (home, CMS, account)
@import (once) '_pages';

// 8. Utilitários (a11y, visibility, print)
@import (once) '_utilities';

// 9. Vendor overrides (Rokanthemes, third-party)
@import (once) '_vendor-overrides';

// 10. Hotfixes (prazo máximo 30 dias — migrar para o partial correto)
// @import (once) '_hotfixes';
```

#### 3.3 Processo de migração segura

Para cada partial legado (`_awa-header-clean-professional-2026-06.less`, etc.):

```bash
# 1. Compilar antes da mudança
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR -f \
  --theme AWA_Custom/ayo_home5_child 2>&1 | tail -3

# 2. Copiar regras para o partial destino
# 3. Comentar (não deletar ainda) o @import no _extend.less
# 4. Compilar novamente
# 5. Comparar visual com screenshot baseline
# 6. Se OK: deletar o arquivo fonte e o @import comentado
# 7. Se regrediu: descomentar e investigar conflito
```

#### 3.4 Estratégia para eliminar `!important`

O objetivo não é eliminar todos de uma vez, mas estabelecer um processo:

**Passo 1:** Identificar os `!important` que estão ganhando batalhas que não deveriam existir:
```bash
# !important em propriedades estruturais (layout) são os mais perigosos
grep -r "max-width.*!important\|width.*!important\|display.*!important" \
  web/css/source/ --include="*.less" | wc -l
```

**Passo 2:** Para cada `!important` estrutural, aumentar a especificidade do seletor em vez de usar `!important`:
```less
// ANTES
.page-main { max-width: 1440px !important; }

// DEPOIS (especificidade maior, sem !important)
body .page-wrapper .page-main { max-width: 1440px; }
```

**Meta:** reduzir de 32.826 para < 200 declarações `!important` (apenas para overrides legítimos de terceiros).

**Critério de saída:** `_extend.less` com ≤ 15 imports ativos, `!important` < 5.000 (meta intermediária), nenhuma regressão visual nas 5 páginas de teste.

---

### FASE 4 — Consolidação dos Bundles CSS (3-4 dias)
**Objetivo:** reduzir de 229 arquivos CSS para ≤ 8 bundles com responsabilidades claras.

#### 4.1 Arquitetura de bundles alvo

| Bundle | Responsabilidade | Carregamento |
|--------|-----------------|--------------|
| `styles-m.css` / `styles-l.css` | Pipeline LESS compilado (toda a CSS principal) | Bloqueante crítico |
| `awa-critical.css` | Critical CSS inline (above-fold, ~4KB) | `<style>` inline |
| `awa-vendor.css` | Bootstrap, icon fonts, owl/swiper | Async preload |
| `awa-async.css` | Cookie consent, datepicker, lightbox | Lazy (interaction) |

**Regra:** qualquer CSS que não seja crítico above-fold vai no pipeline LESS ou em async — nunca como arquivo CSS standalone carregado no `<head>`.

#### 4.2 Migrar CSS standalone para LESS

Cada arquivo `awa-*.css` que representa uma feature deve:
1. Ter seu conteúdo migrado para o partial LESS correto
2. Ser removido de qualquer `layout XML` ou PHTML que o carregue
3. Ser deletado de `web/css/`

#### 4.3 Simplificar os loaders PHTML

Os 109 templates em `Magento_Theme/templates/html/` incluem dezenas de loaders CSS como `awa-bugfix-terminal-2026-06-12.css`. Após a migração dos estilos para LESS:

```bash
# Identificar phmtl que carregam CSS
grep -rl "\.css" app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/ \
  | xargs grep -l "link rel\|stylesheet"
```

Cada loader sem função deve ser removido do layout XML e deletado.

**Critério de saída:** ≤ 8 arquivos CSS em `web/css/` (excluindo pasta `source/`), ≤ 20 templates PHTML em `html/`, zero loaders CSS inline desnecessários.

---

### FASE 5 — Simplificação da Estratégia Async (2-3 dias)
**Objetivo:** ter uma estratégia de carregamento CSS clara, documentada e testável.

#### 5.1 Regra dos 3 slots de carregamento

```
SLOT 1 — Bloqueante (<head>):
  → styles-m.css / styles-l.css (pipeline LESS) SOMENTE

SLOT 2 — Preload crítico (<head>, não bloqueante):
  → awa-critical.css (critical CSS above-fold)
  → fonts (Google Fonts / self-hosted)

SLOT 3 — Async (body-end ou interaction):
  → awa-vendor.css (bootstrap, ícones, swiper)
  → awa-async.css (cookie, datepicker, lightbox)
  → Page-specific CSS (checkout, B2B) via condicional
```

#### 5.2 Implementação do preload correto

```phtml
<?php // awa-head-preload.phtml — versão simplificada ?>
<?php foreach ($cssToPreload as $file): ?>
<link rel="preload" href="<?= $block->getViewFileUrl($file) ?>" 
      as="style" 
      onload="this.onload=null;this.rel='stylesheet'">
<?php endforeach; ?>
<noscript>
<?php foreach ($cssToPreload as $file): ?>
<link rel="stylesheet" href="<?= $block->getViewFileUrl($file) ?>">
<?php endforeach; ?>
</noscript>
```

#### 5.3 Condicional por tipo de página

```xml
<!-- catalog_product_view.xml — CSS específico de PDP -->
<head>
    <css src="css/awa-pdp-specific.css" media="print" 
         content_type="css" 
         order="100"/>
</head>
```

**Critério de saída:** estratégia documentada em 1 parágrafo, ≤ 3 PHTML de carregamento CSS ativos, zero CSS bloqueante não-crítico no `<head>`.

---

### FASE 6 — Testes e Validação (2-3 dias)
**Objetivo:** garantir que nenhuma regressão chegou a produção e estabelecer proteção contínua.

#### 6.1 Checklist de validação pós-fase

Para cada fase concluída:

```bash
# 1. Compilação limpa
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR en_US -f \
  --theme AWA_Custom/ayo_home5_child

# 2. Flush completo
sudo -u www-data php bin/magento cache:flush
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 1 FLUSHDB
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB

# 3. Zero erros nos logs
tail -20 var/log/exception.log | grep -i "error\|exception"

# 4. Screenshot comparativo
# Usar Playwright ou browser nativo do Cursor
```

#### 6.2 Atualizar baseline Playwright

```bash
cd tests/e2e
npx playwright test specs/visual-audit-home-header-footer.spec.ts --update-snapshots
```

#### 6.3 Documentar a arquitetura final

Ao final, o arquivo `DESIGN_SYSTEM_STATUS.md` deve ter:
- Mapa de responsabilidades de cada partial LESS
- Regras de contribuição (onde adicionar novos estilos)
- Processo de deploy
- Métricas atualizadas

**Critério de saída:** todas as 5 páginas críticas sem regressão, métricas documentadas, Playwright rodando sem falhas.

---

### FASE 6C — SEO e Qualidade de Catálogo (4-5 dias)
**Objetivo:** corrigir os 687 produtos sem meta e completar a cobertura WebP — impacto direto em SEO orgânico.

#### 6C.1 Gerar meta_title e meta_description automaticamente (BUG-06)

Adicionar no `ERPIntegration/Model/ProductSync.php` para todos os novos syncs:

```php
// Geração automática de meta SEO durante sync ERP
private function applyMetaSeo(ProductInterface $product): void
{
    if (!$product->getMetaTitle()) {
        $product->setMetaTitle(
            $product->getName() . ' — AWA Motos | Distribuidora de Peças'
        );
    }

    if (!$product->getMetaDescription()) {
        $desc = strip_tags((string)$product->getDescription());
        $product->setMetaDescription(
            $desc
                ? mb_substr($desc, 0, 155)
                : $product->getName() . '. Compre com entrega rápida. Peças originais e compatíveis para sua moto.'
        );
    }
}
```

**Backfill para os 687 produtos existentes:**
```sql
-- meta_title: "Nome do Produto — AWA Motos"
INSERT INTO catalog_product_entity_varchar (attribute_id, store_id, entity_id, value)
SELECT
  (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'meta_title' AND entity_type_id = 4),
  0,
  cpe.entity_id,
  CONCAT(name_attr.value, ' — AWA Motos')
FROM catalog_product_entity cpe
JOIN catalog_product_entity_varchar name_attr
  ON cpe.entity_id = name_attr.entity_id
  AND name_attr.store_id = 0
  AND name_attr.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
LEFT JOIN catalog_product_entity_varchar existing
  ON cpe.entity_id = existing.entity_id
  AND existing.store_id = 0
  AND existing.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'meta_title' AND entity_type_id = 4)
WHERE existing.entity_id IS NULL OR existing.value = '' OR existing.value IS NULL
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

#### 6C.2 Converter 100% das imagens para WebP (BUG-08)

```bash
# Reprocessar todas as imagens com ImageMagick (após BUG-07 corrigido)
sudo -u www-data php bin/magento catalog:images:resize --skip-hidden-images

# Verificar resultado
find pub/media/catalog/product/cache -name "*.webp" | wc -l
find pub/media/catalog/product/cache -name "*.jpg" | wc -l
```

#### 6C.3 Padronizar imagens de categoria

Criar um padrão visual para as imagens de categoria (fundo branco, produto centralizado, 800×600px):
- Exportar lista de categorias sem imagem ou com fundo inconsistente
- Definir template visual para novas imagens
- Aplicar via admin ou script de batch

#### 6C.4 Verificar e corrigir proporções de imagem nos carrosséis

Configurar aspect ratio fixo nos cards de produto para garantir que o layout não quebre independente do tamanho da imagem original:

```less
// Em _awa-product-cards.less
.product-image-wrapper {
    aspect-ratio: 1 / 1;  // Quadrado para uniformidade
    overflow: hidden;
    
    .product-image-photo {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center;
        background: var(--awa-gray-50, #fafafa);
    }
}
```

**Critério de saída:** 100% dos produtos com meta_title e meta_description, 100% WebP no cache, cards com proporção uniforme.

---

### FASE 6B — Correção dos Plugins de Manipulação de HTML (3-4 dias)
**Objetivo:** eliminar os BUGs 01/03/04 de forma estrutural, removendo a dependência de nomes hardcoded e parsing de HTML.

> Esta fase pode ser executada em paralelo com a Fase 6.

#### 6B.1 Substituir `OptimizeHeadStylesPlugin` por layout XML condicional

O plugin atualmente hardcoda nomes de arquivo CSS e faz string matching no HTML da resposta. A abordagem correta no Magento 2 é usar layout handles:

```xml
<!-- catalog_category_view.xml — CSS específico de PLP via handle nativo -->
<page>
    <head>
        <!-- Remove bundles globais que não se aplicam ao PLP -->
        <remove src="css/awa-home-density-grid-20260611.min.css"/>
    </head>
</page>

<!-- cms_index_index.xml — bundles específicos da home -->
<page>
    <head>
        <css src="css/awa-home-density-grid-20260611.min.css" order="200"/>
    </head>
</page>
```

Isso elimina:
- Necessidade de um plugin PHP que processa o HTML inteiro
- Risco de nome hardcoded desatualizado
- Tempo de CPU no response (parsing de strings de 100KB+)

#### 6B.2 Simplificar `PatchHomeHeaderHtmlPlugin`

O plugin injeta CSS de header via manipulação de HTML da resposta. O problema: a detecção `htmlHasSiteHeader()` é baseada em marcação HTML que pode mudar.

**Solução:** mover toda a injeção de CSS para o `head.additional` via layout XML, eliminando a necessidade de pós-processamento do HTML.

Se o plugin for necessário para casos de borda (ex: resposta gerada por terceiros), restringir a detecção a um atributo `data-` que seja estável:
```html
<!-- Adicionar ao header.phtml -->
<header data-awa-site-header="true">
```
```php
// Detecção estável — não quebra com mudança de classes
if (!str_contains($html, 'data-awa-site-header="true"')) {
    return $result;
}
```

#### 6B.3 Fix do FOUT de fontes (BUG-03)

Mover preload de fontes críticas de `head.phtml` para `default_head_blocks.xml`:

```xml
<!-- Magento_Theme/layout/default_head_blocks.xml -->
<head>
    <!-- Preload fontes críticas — incondicional, independe de Themeoption -->
    <link src="fonts/rubik/rubik-600.woff2" rel="preload" as="font"
          crossorigin="anonymous"/>
    <link src="fonts/source-sans-3/source-sans-3-400.woff2" rel="preload" as="font"
          crossorigin="anonymous"/>
</head>
```

Remover a lógica condicional do `head.phtml`.

#### 6B.4 Protocolo para renomeação de bundles CSS

Toda vez que um bundle CSS mudar de nome, o processo obrigatório é:

1. Grep no código PHP pelos nomes antigos: `rg "nome-antigo" app/code/ --type php`
2. Atualizar constantes em `OptimizeHeadStylesPlugin`
3. Atualizar referências em PHMLTs de loader
4. Verificar `default.xml`, `default_head_blocks.xml` e todos os layout handles
5. Executar deploy completo via `scripts/deploy-theme.sh`

**Critério de saída:** zero plugins que fazem string matching de nomes de arquivo CSS no HTML de resposta, FOUT de fontes eliminado, preload incondicional.

---

### FASE 7 — Governança Contínua (permanente)
**Objetivo:** impedir que o débito se acumule novamente.

#### 7.1 Regras de contribuição CSS

**Antes de qualquer edição CSS/LESS:**

1. Abrir `web/css/source/_tokens.less` — existe um token para o valor que preciso? Use-o.
2. Abrir o partial da área afetada — existe uma regra parecida que posso estender?
3. Só se não existir: adicionar no partial correto (nunca criar arquivo novo).
4. Nunca usar `!important` — aumentar especificidade do seletor.
5. Nunca usar hex hardcoded — usar o token.

#### 7.2 Script de guarda pré-commit

```bash
#!/bin/bash
# scripts/css-guard.sh — rode antes de qualquer commit de CSS
IMPORTANT_COUNT=$(grep -r "!important" web/css/source/ --include="*.less" | wc -l)
echo "!important count: $IMPORTANT_COUNT"

if [ "$IMPORTANT_COUNT" -gt 500 ]; then
  echo "❌ BLOQUEADO: $IMPORTANT_COUNT declarações !important (limite: 500)"
  exit 1
fi

echo "✅ CSS guard OK"
```

#### 7.3 Limite de arquivos por diretório

| Diretório | Limite máximo |
|-----------|:------------:|
| `web/css/source/` (total) | 30 arquivos |
| `web/css/` (CSS compilados) | 10 arquivos |
| `Magento_Theme/templates/html/` | 20 templates |
| `@import` ativos em `_extend.less` | 15 imports |

Qualquer proposta que ultrapasse esses limites deve ser justificada e aprovada explicitamente.

#### 7.4 Processo de hotfix emergencial

Quando há urgência real:

```less
// _hotfixes.less — arquivo que aceita fixes rápidos
// REGRA: máximo 7 dias antes de migrar para o partial correto
// OBRIGATÓRIO: comentar com data, descrição e prazo de migração

// [2026-07-01] Fix: logo desaparece no scroll — migrar para _components-global até 2026-07-08
.logo-wrapper { opacity: 1 !important; } // TODO-migrate
```

---

## 8. Roadmap e Estimativas

```
                              Jun/2026   Jul/2026   Ago/2026
                              ─────────  ─────────  ─────────
FASE 0  — Baseline + deploy fix  ██
FASE 0B — Fixes catálogo         ██  (paralela à F0)
FASE 1  — Limpeza artefatos        ███
FASE 2  — Consolidar tokens            ████
FASE 3  — Reestruturar extend              ████████
FASE 4  — Bundles CSS                             ████
FASE 5  — Estratégia async                            ████
FASE 6  — Testes e validação                             ███
FASE 6B — Correção plugins          ──────────────────────███   (paralela F4/F5/F6)
FASE 6C — SEO + catálogo            ──────────────────────███   (paralela F4/F5/F6)
FASE 7  — Governança contínua                                ████→
```

**Total estimado:** 6-8 semanas de trabalho iterativo com deploys seguros.

**Executado em Jun/2026 — FASE 0 + 0B + 1 + 6C:**

| Data | Ação | Resultado |
|------|------|-----------|
| 23 Jun | Remover `.orig` de `var/view_preprocessed` | ✅ BUG-01 Camada 2 resolvida |
| 23 Jun | Criar `scripts/deploy-theme.sh` | ✅ Protocolo de deploy automatizado |
| 23 Jun | Fix imagens `small_image`/`thumbnail` | ✅ 64 registros atualizados |
| 23 Jun | Adapter IMAGEMAGICK ativado | ✅ Novo padrão de resize |
| 23 Jun | Alertas de preço e stock ativados | ✅ `allow_price=1`, `allow_stock=1` |
| 23 Jun | Cancelamentos investigados | ✅ Operação admin em lote (Jun/11), não abandono |
| 24 Jun | FASE 1 — Limpeza de artefatos | ✅ 85 LESS + 28 CSS movidos para `_deprecated/` |
| 24 Jun | FASE 6C — Meta SEO gerado | ✅ 687 produtos com meta_title + meta_description |
| 24 Jun | GAP-01 — Alertas ativados | ✅ Cron diário configurado |
| 24 Jun | GAP-02 — Reviews ativadas | ✅ `catalog/review/active=1` |
| 24 Jun | GAP-03 — Checkout auditado | ✅ 4/4 P0 passando, gate B2B correto |
| 24 Jun | ERP image sync | ⚠️ 179 SKUs sem CodInterno — cadastro manual necessário |
| 24 Jun | FASE 2 — Tokens: `@awa-opc-body` consolidado, `page-pad-mobile` 12→16px canônico, `_awa-core-variables-bundle` (2218L) arquivado | ✅ |
| 24 Jun | FASE 6B — OptimizeHeadStylesPlugin: 12 fragmentos CSS mortos comentados | ✅ |
| 24 Jun | FASE 6B — PatchHomeHeaderHtmlPlugin: BUG-01 Camada 4 — 13 versões hardcoded → regex genérica | ✅ BUG-01 100% mitigado |

---

## 8.5 Gaps Identificados — Próximas Ações

Três áreas identificadas durante a análise que ainda não têm fase definida:

### GAP-01 — Alertas de Preço e Estoque Desativados

**Problema:** Os módulos de `product_alert` (aviso de preço e back-in-stock) estão desativados por padrão no Magento. Verificado via configuração.

**Impacto:** Clientes que visitam produtos fora de estoque ou caros não têm mecanismo de retorno automático.

**Ação:**
```bash
# Ativar alertas de preço e estoque
sudo -u www-data php bin/magento config:set catalog/productalert/allow_price 1
sudo -u www-data php bin/magento config:set catalog/productalert/allow_stock 1
sudo -u www-data php bin/magento config:set catalog/productalert_cron/frequency D
sudo -u www-data php bin/magento cache:flush
```

**Status:** ✅ **Executado em Jun/2026** — `allow_price=1`, `allow_stock=1`, `frequency=D` configurados.

**Fase sugerida:** Fase 0B — baixo risco, alto impacto de retenção.

---

### GAP-02 — Reviews e Avaliações de Produtos Praticamente Inexistentes

**Problema:** O catálogo tem poucos produtos com avaliações reais. Reviews são um dos maiores fatores de conversão em e-commerce.

**Impacto:** Páginas de produto com zero estrelas → confiança reduzida → taxa de conversão abaixo do potencial.

**Ações:**
- Ativar envio automático de e-mail pós-entrega solicitando review
- Criar campanha de incentivo para clientes existentes
- Verificar se o módulo `GrupoAwamotos_SmartSuggestions` pode incluir CTA de avaliação

```bash
# Verificar configuração atual de review
sudo -u www-data php bin/magento config:show catalog/review/allow_guest
# Checar contagem atual
mysql -u "$MAGENTO_DB_USER" -p"$MAGENTO_DB_PASS" "$MAGENTO_DB_NAME" -e "
  SELECT status_id, COUNT(*) as total FROM review GROUP BY status_id;"
```

**Fase sugerida:** Fase 4 (UX Patterns) ou como ação de CRM paralela.

---

### GAP-03 — Checkout UX não Documentado nem Auditado

**Problema:** O checkout é a página mais crítica para conversão mas não tem cobertura neste plano. O tema Ayo tem overrides de checkout que podem ter regressões.

**Impacto:** Qualquer bug visual no checkout = perda direta de receita.

**Ações necessárias:**
1. Auditoria visual do fluxo completo: carrinho → shipping → payment → success
2. Verificar se o módulo `GrupoAwamotos_OfflinePayment` renderiza corretamente em mobile
3. Testar o fluxo B2B com `acombinar` (método mais usado pelos clientes B2B)
4. Criar spec Playwright cobrindo o fluxo completo

```bash
# Verificar se há erros de checkout nos logs
grep -i "checkout\|payment\|order.*place" var/log/exception.log | tail -10
# Checar conversão carrinho→pedido (30 dias)
# mysql: taxa de checkout completion via quote vs order
```

**Fase sugerida:** Fase 4 — adicionar subsection específica de checkout no plano de UX.

---

## 9. KPIs de Sucesso

### 9.1 Bugs Visuais e de Deploy

| Bug | Estado Atual | Meta Fase 0 | Meta Final |
|-----|:---:|:---:|:---:|
| BUG-01 Header reverte após deploy | ✅ Todas as 4 camadas mitigadas (Jun/24) | ✅ | ✅ Resolvido |
| BUG-02 CSS home ≠ PLP/PDP | 🔴 Ativo | 🟡 Mitigado | ✅ Resolvido |
| BUG-03 FOUT nas fontes | 🟡 Intermitente | 🟡 Mitigado | ✅ Resolvido |
| BUG-04 Layout cache stale | 🟡 Intermitente | 🟡 Mitigado | ✅ Resolvido |
| BUG-05 Service Worker stale | 🟡 Intermitente | — | ✅ Resolvido |
| Arquivos `.orig` em preprocessed | ✅ Removidos (Jun/2026) | ✅ 0 arquivos | ✅ 0 arquivos |

### 9.2 Bugs de Catálogo e Dados

| Bug | Impacto | Estado | Meta |
|-----|---------|:---:|:---:|
| IMG-01 207 produtos sem `small_image`/`thumbnail` | Imagens quebradas em todos os carrosséis | ✅ Corrigido Jun/24 (64 registros atualizados) | ✅ |
| IMG-02 179 produtos ativos sem imagem principal | Placeholder em PDP — `CodInterno ERP` não mapeado | ⚠️ Ação manual no ERP | 🟡 Fase 6C |
| IMG-04 75% sem WebP (4.338 imagens) | Páginas 30% mais pesadas | 🟡 Médio | 🟡 Fase 6C |
| BUG-06 687/692 sem meta SEO | CTR orgânico baixo | ✅ Corrigido Jun/24 (692/692 com meta) | ✅ |
| BUG-07 Adapter GD2 (não ImageMagick) | Qualidade de resize inferior | ✅ Corrigido Jun/23 (IMAGEMAGICK ativo) | ✅ |
| BUG-08 Conversão WebP incompleta | Performance degradada | 🟡 Médio | 🟡 Fase 6C |
| BUG-09 52% cancelamentos "A Combinar" | **Esclarecido Jun/23:** operação admin em lote. 15 pedidos no mesmo segundo em Jun/11. Não é abandono orgânico. | 🟢 Monitorar | ✅ |
| BUG-10 quotes limpeza automática | DB bloat lento | ✅ Configurado (30 dias, já ativo) | ✅ |

### 9.3 Débito Técnico CSS

| Métrica | Hoje | Meta Fase 3 | Meta Final |
|---------|-----:|:-----------:|:----------:|
| Arquivos CSS em `web/css/` | 205 → 162 → **66** ✅ Jun/24 (-79%) | ≤ 50 | ≤ 10 |
| Partials LESS em `source/` | 361 → **275** (-86 em _deprecated/) | ≤ 100 | ≤ 30 |
| Partials `_awa-*.less` | 296 → **≈210** | ≤ 50 | ≤ 20 |
| Declarações `!important` | 32.826 | ≤ 5.000 | ≤ 200 |
| `@import` no `_extend.less` | 115 → **52** ✅ Jun/24 (-55%) | ≤ 40 | ≤ 15 |
| Linhas no `_extend.less` | 2.386 → **2.195** ✅ Jun/24 | ≤ 500 | ≤ 150 |
| Hex hardcoded (não-token) | 3.324 → **150** ✅ Jun/24 (-95%) | ≤ 50 | 0 |
| Templates PHTML em `html/` | 109 | ≤ 40 | ≤ 20 |
| Regressões visuais por deploy | 0 (deploy de Jun/24 limpo) | 0 | 0 |
| Nomes CSS hardcoded em PHP | ≥ 15 constantes | ≤ 5 | 0 |
| Plugins de parsing de HTML | 2 plugins ativos | 1 | 0 |
| Cobertura meta SEO | 5/692 → **692/692** ✅ | 100% | 100% |
| Reviews ativas | 0 → **ativas** ✅ | ativo | ativo |
| Checkout P0 passing | — → **4/4** ✅ | 4/4 | 4/4 |

---

## 10. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|:---:|:---:|---|
| Regressão visual ao remover `!important` | Alta | Alto | Nunca deletar antes de confirmar visualmente; trabalhar em branch |
| CSS compilado diferente entre ambientes | Média | Alto | Sempre compilar no servidor de produção via `www-data` |
| Partial órfão que era importado indiretamente | Média | Médio | Grep recursivo antes de deletar qualquer arquivo |
| Conflito de especificidade Rokanthemes vs AWA | Alta | Médio | Manter todos os overrides Rokanthemes no `_vendor-overrides.less` com comentário |
| `var/view_preprocessed` desatualizado | Baixa | Alto | Sempre incluir o flush do `var/view_preprocessed` no checklist de deploy |

---

## 11. Protocolo de Deploy Seguro (referência rápida)

> **Script criado em Jun/2026:** `scripts/deploy-theme.sh` automatiza todo este protocolo.

```bash
# Forma recomendada — usar o script (criado em Jun/2026)
sudo bash scripts/deploy-theme.sh           # deploy completo
sudo bash scripts/deploy-theme.sh --css     # apenas CSS (sem di:compile)
sudo bash scripts/deploy-theme.sh --check   # verifica saúde sem deploy

# Manual (referência):
# 1. Remover arquivos .orig/.bak stale do view_preprocessed
find var/view_preprocessed/pub/static/frontend/AWA_Custom/ayo_home5_child \
  \( -name "*.orig*" -o -name "*.bak*" \) -delete

# 2. Compilar (sempre como www-data)
sudo -u www-data php bin/magento setup:static-content:deploy pt_BR en_US -f \
  --theme AWA_Custom/ayo_home5_child

# 3. Flush Magento cache
sudo -u www-data php bin/magento cache:flush

# 4. Flush Redis (DB1 = block/layout, DB2 = FPC)
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 1 FLUSHDB
redis-cli -h ::1 -a 'Aw4R3d1s2026Sec' -n 2 FLUSHDB

# 5. Verificar logs
tail -5 var/log/exception.log

# 6. Abrir browser (sem service worker, sem cache)
# DevTools → Application → Service Workers → Unregister
# DevTools → Network → Disable cache
```

---

## 12. Apêndice — Comandos de Diagnóstico

```bash
# Métricas gerais de débito CSS
echo "CSS files:" && ls web/css/*.css 2>/dev/null | grep -v ".min\|.br\|.gz" | wc -l
echo "LESS partials:" && ls web/css/source/*.less | wc -l
echo "_awa-* partials:" && ls web/css/source/_awa-*.less | wc -l
echo "!important count:" && grep -r "!important" web/css/source/ --include="*.less" | wc -l
echo "Active @imports:" && grep "^@import" web/css/source/_extend.less | grep -v "^//" | wc -l

# Variáveis LESS duplicadas
grep -rh "^@awa-" web/css/source/ --include="*.less" | sort | uniq -d

# CSS hardcoded (hex fora de _tokens)
grep -r "#[0-9a-fA-F]\{3,6\}" web/css/source/ --include="*.less" \
  --exclude="_tokens.less" --exclude="_awa-variables.less" -l

# Templates PHTML que carregam CSS
grep -rl "\.css" Magento_Theme/templates/html/ | xargs grep -l "link\|stylesheet" 2>/dev/null

# Partials importados N vezes (possível duplicação)
grep "^@import" web/css/source/_extend.less | grep -v "//" | \
  sed "s/.*'\(.*\)'.*/\1/" | sort | uniq -d
```

---

*Documento criado em Jun/2026.*

**Última execução: 24 Jun 2026 (madrugada/manhã) — FASE 4+5 completas.**

| Métrica | Início | Jun/24 final | Alvo |
|---------|--------|-------------|------|
| `!important` | 27.944 | 27.682 | < 5.000 ⚠️ |
| `@import` no `_extend.less` | 115 | **10** ✅ | ≤ 15 |
| Hex hardcoded (em regras CSS) | ~1.521 | **559** (-63%) | ≤ 200 |
| CSS em `web/css/` (não-min) | 205 | **52** ✅ | ≤ 50 |
| PHMLs ativos | 109 | **40** ✅ | ≤ 40 |
| Blocos layout sem output | 4 | **0** ✅ | 0 |
| CSS morto removido | — | **~9MB** ✅ | — |
| Tokens LESS adicionados | — | **+18** | — |

**O que foi feito nesta sessão (segunda metade):**
- Removidos CSS legados (super-global, layout-bundle, layout-canonical — ~4.8MB)
- 4 blocos `default.xml` sem output removidos
- 69 PHMLs → `_deprecated/` (109 → 40 ativos)
- 11 CSS órfãos → `_deprecated/` (371KB)
- 18 novos tokens LESS adicionados a `_awa-variables.less`
- 997 substituições hex → token em 80+ arquivos LESS (1521 → 559, −63%)
- `_extend.less` reestruturado: 52 → **10 imports** via 6 bundles:
  - `_awa-bundle-base-components.less` (forms/cards/buttons)
  - `_awa-bundle-midgame.less` (consolidated, header, layout, cart — 20 imports)
  - `_awa-bundle-phase3-final.less` (B2B + home interleaved — 22 imports)
  - + bundles de apoio: components, header, home, layout, cart, b2b
- Deploy limpo 4.9s. Logs sem erros.

**Pendente (maior risco):**
- `!important` 27.682 → < 5.000: requer CSS cascade layers (`@layer`) ou refatoração de seletores. Estimativa: 2–3 dias. NÃO fazer sem testes visuais antes/depois em dev.

*Revisar métricas a cada fase concluída e atualizar a coluna "Hoje".*
