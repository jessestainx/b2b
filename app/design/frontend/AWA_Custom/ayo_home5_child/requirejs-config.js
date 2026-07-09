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
            'Magento_Catalog/js/product/breadcrumbs': 'js/awa-pdp-breadcrumbs',
            'AWA_Custom/js/awa-back-to-top': 'js/awa-back-to-top',
            'jquery/ui': 'jquery/compat',
            'Rokanthemes_LayeredAjax/js/layeredajax': 'GrupoAwamotos_Theme/js/awa-layeredajax-stub',
            'Rokanthemes_LayeredAjax/js/price/layeredajaxslider': 'GrupoAwamotos_Theme/js/awa-layeredajax-stub',
            'Magento_Checkout/template/billing-address/form.html':
                'Magento_Checkout/template/billing-address/form.html',
            'Magento_Catalog/js/product/view/awa-pdp-cep-estimator': 'js/awa-pdp-cep-estimator'
        }
    },
    paths: {
        'rokanthemes/timecircles': 'js/rokanthemes/timecircles',
        'rokanthemes/verticalmenu': 'js/vendor-verticalmenu-fixed',
        'js/awa-requirejs-bootstrap': 'js/awa-requirejs-bootstrap',
        'awa-b2b-header': 'js/awa-b2b-header',
        'js/awa-home-category-carousel': 'js/awa-home-category-carousel',
        'js/jquery-andself-compat': 'js/jquery-andself-compat.min',
        'awa-header-sticky': 'js/awa-header-sticky',
        'awa-header-nav-runtime': 'js/awa-header-nav-runtime',
        'awa-header-customer-runtime': 'js/awa-header-customer-runtime',
        'awa-header-runtime-bootstrap': 'js/awa-header-runtime-bootstrap',
        'awa-b2b-pdp-price-reload': 'js/awa-b2b-pdp-price-reload',
        'awa-b2b-price-hydrator': 'js/awa-b2b-price-hydrator',
        'awa-scroll-reveal': 'js/awa-scroll-reveal',
        'awa-card-enhance': 'js/awa-card-enhance',
        'awa-footer-returns-hotfix': 'js/awa-footer-returns-hotfix',
        'awa-link-a11y-hotfix': 'js/awa-link-a11y-hotfix',
        'awa-b2b-plp-qty': 'js/awa-b2b-plp-qty',
        'awa-nav-cls-fix-reset': 'js/awa-nav-cls-fix-reset',
        'awa-menu-controller': 'js/awa-menu-controller',
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
