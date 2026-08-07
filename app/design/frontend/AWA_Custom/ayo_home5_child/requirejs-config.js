/**
 * AWA Custom child theme — RequireJS overrides mínimos.
 * Aliases do core Magento vêm do merge padrão (vendor + módulos).
 */
var config = {
    waitSeconds: 30,
    deps: [
        'js/awa-requirejs-min-fallback',
        'js/awa-requirejs-bootstrap'
    ],
    map: {
        '*': {
            awaCustomCompatBootstrap: 'js/awa-custom-compat-bootstrap',
            /* Luma Magento_Theme/js/theme ativa mage/sticky no .cart-summary e colide
             * com position:sticky CSS + footer. Usar shim leve → theme-heavy (skip cart). */
            'Magento_Theme/js/theme': 'js/theme',
            'Magento_Catalog/js/product/breadcrumbs': 'js/awa-pdp-breadcrumbs',
            'AWA_Custom/js/awa-back-to-top': 'js/awa-back-to-top',
            'jquery/ui': 'jquery/compat',
            'Rokanthemes_LayeredAjax/js/layeredajax': 'GrupoAwamotos_Theme/js/awa-layeredajax-stub',
            'Rokanthemes_LayeredAjax/js/price/layeredajaxslider': 'GrupoAwamotos_Theme/js/awa-layeredajax-stub',
            'Magento_Ui/js/lib/knockout/bindings/color-picker': 'js/awa-ko-color-picker-stub',
            'Magento_Bundle/js/validation': 'js/awa-bundle-validation-stub',
            'Magento_Checkout/template/billing-address/form.html':
                'Magento_Checkout/template/billing-address/form.html',
            'Magento_Catalog/js/product/view/awa-pdp-cep-estimator': 'js/awa-pdp-cep-estimator',
            /* Bust cache HTTP do template KO (closeMinicart sem ()).
             * Manter alinhado a GrupoAwamotos\Theme\Model\MinicartAssetVersion::RUNTIME */
            'Magento_Checkout/template/minicart/content.html':
                'Magento_Checkout/template/minicart/content.html?v=20260806-minicart-opt-r4'
        }
    },
    paths: {
        'rokanthemes/timecircles': 'js/rokanthemes/timecircles',
        'rokanthemes/verticalmenu': 'js/vendor-verticalmenu-fixed',
        'js/awa-requirejs-bootstrap': 'js/awa-requirejs-bootstrap',
        'awa-b2b-header': 'js/awa-b2b-header',
        'js/awa-home-category-carousel': 'js/awa-home-category-carousel',
        'js/jquery-andself-compat': 'js/jquery-andself-compat.min',
        // RequireJS não acrescenta .js quando o path tem "?"; incluir .js antes do query.
        // Manter alinhado a MinicartAssetVersion::RUNTIME
        'awa-header-sticky': 'js/awa-header-sticky.js?v=20260805-mobile-condensed-1row-r7',
        'js/awa-minicart-position': 'js/awa-minicart-position.js?v=20260806-minicart-opt-r4',
        'awa-header-nav-runtime': 'js/awa-header-nav-runtime',
        'awa-header-customer-runtime': 'js/awa-header-customer-runtime',
        'awa-header-runtime-bootstrap': 'js/awa-header-runtime-bootstrap.js?v=20260720-header-final-r6',
        'awa-b2b-pdp-price-reload': 'js/awa-b2b-pdp-price-reload',
        'awa-b2b-price-hydrator': 'js/awa-b2b-price-hydrator',
        'awa-scroll-reveal': 'js/awa-scroll-reveal',
        'awa-card-enhance': 'js/awa-card-enhance',
        'awa-footer-returns-hotfix': 'js/awa-footer-returns-hotfix',
        'awa-link-a11y-hotfix': 'js/awa-link-a11y-hotfix',
        'awa-b2b-plp-qty': 'js/awa-b2b-plp-qty',
        'awa-nav-cls-fix-reset': 'js/awa-nav-cls-fix-reset',
        'awa-menu-controller': 'js/awa-menu-controller.dept-strip-r16',
        'js/vendor/floating-ui.amd': 'js/vendor/floating-ui.amd.min',
        'js/vendor/floating-ui.core.umd': 'js/vendor/floating-ui.core.umd.min',
        'js/vendor/floating-ui.dom.umd': 'js/vendor/floating-ui.dom.umd.min',
        '@floating-ui/core': 'js/vendor/floating-ui.core.umd.min',
        'js/vmenu-promo-carousel': 'js/vmenu-promo-carousel'
    },
    shim: {
        'js/vendor/floating-ui.core.umd': {
            exports: 'FloatingUICore'
        },
        'js/vendor/floating-ui.dom.umd': {
            deps: ['js/vendor/floating-ui.core.umd'],
            exports: 'FloatingUIDOM'
        },
        'jquery/ui': ['jquery'],
        'matchMedia': {
            exports: 'mediaCheck'
        }
    },
    config: {
        mixins: {
            'mage/apply/main': {
                'js/mixin-mage-apply-safe': true
            },
            'mage/sticky': {
                'js/mixin/cart-disable-mage-sticky': true
            },
            'Magento_Search/js/form-mini': {
                'js/mixin/quicksearch-panel-harden': true
            },
            'Magento_Checkout/js/view/cart/shipping-estimation': {
                'js/mixin/cart-shipping-estimation-customer-gate': true
            },
            'Magento_Checkout/js/view/billing-address': {
                'js/mixin/billing-address-autofill': true
            },
            'Magento_Checkout/js/view/payment/default': {
                'js/mixin/payment-default-billing-guard': true
            },
            'Magento_Checkout/js/model/error-processor': {
                'js/mixin/checkout-error-processor': true
            },
            'Magento_Checkout/js/view/summary/item/details/thumbnail': {
                'js/mixin/checkout-summary-thumbnail': true
            },
            'Magento_Checkout/js/view/shipping-address/address-renderer/default': {
                'js/mixin/shipping-address-telephone': true
            },
            'Magento_Checkout/js/view/shipping-information/address-renderer/default': {
                'js/mixin/shipping-address-telephone': true
            }
        },
        text: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
    }
};
