/**
 * AWA Motos — Venia Concept configuration
 *
 * Este arquivo configura o PWA Studio para conectar ao Magento backend.
 * Quando executar `npm install && npm run build`, será usado pelo webpack.
 *
 * IMPORTANTE: após instalar PWA Studio (@magento/venia-concept),
 *   este arquivo deve ser renomeado para `local.config.js`
 */

// Venia PWA Studio config
module.exports = {
    // Backend Magento
    MAGENTO_BACKEND_URL: process.env.MAGENTO_BACKEND_URL || 'https://awamotos.com',

    // GraphQL endpoint (auto-detected)
    GRAPHQL_ENDPOINT: process.env.GRAPHQL_ENDPOINT || '',

    // Store code (awa_motos_br para B2B)
    STORE_VIEW_CODE: process.env.STORE_VIEW_CODE || 'awa_motos_br',
    SITE_CODE: process.env.SITE_CODE || 'base',

    // Custom B2B routes (serão mesclados com Venia padrão)
    customRoutes: [
        // @example adicionar rotas customizadas aqui
    ],

    // Image optimization
    IMAGE_RESIZING_URL: process.env.IMAGE_RESIZING_URL,
    MAGE_MEDIA_PATH_PATTERN: '/media/catalog/product/',

    // PWA features
    PWA_STUDIO_PORTS_STYLE: 'integrated',
    PWA_USE_LIGHTNINGCSS: false,
    GENERATE_PWA_META: true,

    // Checkout
    CHECKOUT_BRAINTREE_TOKEN: process.env.CHECKOUT_BRAINTREE_TOKEN,
    PWA_CHECKOUT_USE_NEW_FLOW: true,

    // B2B custom (quando AWA migrar)
    awaB2B: {
        enabled: true,
        endpoint: '/graphql',
        features: {
            quickOrder: true,
            quoteRequest: true,
            priceByCustomerGroup: true,
        }
    },

    // Build optimization
    ENABLE_BUILD_OPTIMIZATION: true,
};
