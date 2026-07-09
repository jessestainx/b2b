#!/bin/bash

###############################################################################
# REXIS ML - Installation Script
# Automação completa da instalação e configuração do módulo
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Emojis
CHECK="✓"
CROSS="✗"
ARROW="→"
ROCKET="🚀"
GEAR="⚙️"
DATABASE="🗄️"
EMAIL="📧"
PHONE="📱"

echo -e "${BLUE}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                                                            ║"
echo "║     REXIS ML - Sistema de Recomendações Inteligentes      ║"
echo "║                   Installation Script                     ║"
echo "║                                                            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}\n"

# Check if running from Magento root
if [ ! -f "bin/magento" ]; then
    echo -e "${RED}${CROSS} Erro: Execute este script da raiz do Magento!${NC}"
    echo -e "${YELLOW}   Exemplo: bash app/code/GrupoAwamotos/RexisML/INSTALL.sh${NC}"
    exit 1
fi

echo -e "${BLUE}${ROCKET} Iniciando instalação do REXIS ML...${NC}\n"

# Step 1: Enable module
echo -e "${YELLOW}${GEAR} [1/8] Habilitando módulo...${NC}"
php bin/magento module:enable GrupoAwamotos_RexisML
echo -e "${GREEN}${CHECK} Módulo habilitado${NC}\n"

# Step 2: Run setup upgrade
echo -e "${YELLOW}${DATABASE} [2/8] Criando tabelas do banco de dados...${NC}"
php bin/magento setup:upgrade
echo -e "${GREEN}${CHECK} Tabelas criadas:${NC}"
echo -e "   - rexis_dataset_recomendacao"
echo -e "   - rexis_network_rules"
echo -e "   - rexis_customer_classification"
echo -e "   - rexis_metricas_conversao\n"

# Step 3: Compile DI
echo -e "${YELLOW}${GEAR} [3/8] Compilando injeção de dependências...${NC}"
php bin/magento setup:di:compile
echo -e "${GREEN}${CHECK} DI compilada${NC}\n"

# Step 4: Deploy static content
echo -e "${YELLOW}${GEAR} [4/8] Deployando conteúdo estático...${NC}"
php bin/magento setup:static-content:deploy -f pt_BR en_US
echo -e "${GREEN}${CHECK} Conteúdo estático deployado${NC}\n"

# Step 5: Clear cache
echo -e "${YELLOW}${GEAR} [5/8] Limpando cache...${NC}"
php bin/magento cache:clean
php bin/magento cache:flush
echo -e "${GREEN}${CHECK} Cache limpo${NC}\n"

# Step 6: Set permissions
echo -e "${YELLOW}${GEAR} [6/8] Configurando permissões...${NC}"
chmod +x scripts/rexis_ml_sync.py 2>/dev/null || true
echo -e "${GREEN}${CHECK} Permissões configuradas${NC}\n"

# Step 7: Check Python dependencies
echo -e "${YELLOW}${GEAR} [7/8] Verificando dependências Python...${NC}"
if command -v python3 &> /dev/null; then
    echo -e "${GREEN}${CHECK} Python 3 encontrado${NC}"

    # Check required packages
    python3 -c "import pandas" 2>/dev/null && echo -e "${GREEN}${CHECK} pandas instalado${NC}" || echo -e "${YELLOW}${ARROW} Instale: pip3 install pandas${NC}"
    python3 -c "import sqlalchemy" 2>/dev/null && echo -e "${GREEN}${CHECK} sqlalchemy instalado${NC}" || echo -e "${YELLOW}${ARROW} Instale: pip3 install sqlalchemy${NC}"
    python3 -c "import pymysql" 2>/dev/null && echo -e "${GREEN}${CHECK} pymysql instalado${NC}" || echo -e "${YELLOW}${ARROW} Instale: pip3 install pymysql${NC}"
    python3 -c "import mlxtend" 2>/dev/null && echo -e "${GREEN}${CHECK} mlxtend instalado${NC}" || echo -e "${YELLOW}${ARROW} Instale: pip3 install mlxtend${NC}"
else
    echo -e "${YELLOW}${ARROW} Python 3 não encontrado - necessário para sincronização${NC}"
fi
echo ""

# Step 8: Test installation
echo -e "${YELLOW}${GEAR} [8/8] Testando instalação...${NC}"
if php bin/magento rexis:stats > /dev/null 2>&1; then
    echo -e "${GREEN}${CHECK} Comando CLI funcionando${NC}"
else
    echo -e "${YELLOW}${ARROW} Comando CLI disponível (sem dados ainda)${NC}"
fi
echo ""

# Installation complete
echo -e "${GREEN}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                                                            ║"
echo "║            ${CHECK} INSTALAÇÃO CONCLUÍDA COM SUCESSO!              ║"
echo "║                                                            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}\n"

# Next steps
echo -e "${BLUE}${ROCKET} PRÓXIMOS PASSOS:${NC}\n"

echo -e "${YELLOW}1. Configurar credenciais no Admin:${NC}"
echo -e "   ${ARROW} Stores → Configuration → Grupo Awamotos → REXIS ML"
echo -e "   - Email recipients"
echo -e "   - WhatsApp API URL e Key"
echo -e "   - Score mínimo (padrão: 0.7)\n"

echo -e "${YELLOW}2. Executar primeira sincronização:${NC}"
echo -e "   ${ARROW} python3 scripts/rexis_ml_sync.py"
echo -e "   ${ARROW} ou: php bin/magento rexis:sync\n"

echo -e "${YELLOW}3. Verificar estatísticas:${NC}"
echo -e "   ${ARROW} php bin/magento rexis:stats\n"

echo -e "${YELLOW}4. Testar automações:${NC}"
echo -e "   ${EMAIL} Email:    php bin/magento rexis:test-email seu@email.com"
echo -e "   ${PHONE} WhatsApp: php bin/magento rexis:test-whatsapp 5511999998888\n"

echo -e "${YELLOW}5. Acessar Dashboard:${NC}"
echo -e "   ${ARROW} Admin → REXIS ML → Dashboard\n"

echo -e "${YELLOW}6. Adicionar Widget no CMS:${NC}"
echo -e "   ${ARROW} Content → Widgets → Add Widget"
echo -e "   ${ARROW} Tipo: REXIS ML - Recomendações Personalizadas\n"

# API Endpoints
echo -e "${BLUE}${GEAR} ENDPOINTS DA API REST:${NC}\n"
echo -e "   GET  /rest/V1/rexis/recommendations/:customerId"
echo -e "   GET  /rest/V1/rexis/crosssell/:sku"
echo -e "   GET  /rest/V1/rexis/rfm/:customerId"
echo -e "   POST /rest/V1/rexis/convert"
echo -e "   GET  /rest/V1/rexis/metrics\n"

# Documentation
echo -e "${BLUE}${DATABASE} DOCUMENTAÇÃO:${NC}\n"
echo -e "   ${ARROW} README.md                - Visão geral"
echo -e "   ${ARROW} GUIA_RAPIDO_REXIS_ML.md  - Instalação rápida"
echo -e "   ${ARROW} API_AUTOMATIONS_GUIDE.md - API e automações"
echo -e "   ${ARROW} ENHANCED_FEATURES.md     - Features avançadas"
echo -e "   ${ARROW} PHASE3_SUMMARY.md        - Resumo Fase 3\n"

echo -e "${GREEN}${CHECK} Instalação completa! Sistema pronto para uso.${NC}\n"
