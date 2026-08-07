/**
 * SSOT storefront — Swiper, Chart.js, stubs Owl/bxSlider, init legado.
 */
var config = {
    map: {
        '*': {
            'quickview/bxslider': 'GrupoAwamotos_Theme/js/awa-bxslider-stub',
            'rokanthemes/bxslider': 'GrupoAwamotos_Theme/js/awa-bxslider-stub',
            chartjs: 'GrupoAwamotos_Theme/js/vendor/chart.umd.min',
            chartJs: 'GrupoAwamotos_Theme/js/vendor/chart.umd.min',
            'chart.js': 'GrupoAwamotos_Theme/js/vendor/chart.umd.min',
            awaLegacySwiper: 'GrupoAwamotos_Theme/js/awa-legacy-swiper-init',
            awaHeroSlider: 'GrupoAwamotos_Theme/js/awa-hero-slider',
            awaFooterInteractions: 'GrupoAwamotos_Theme/js/awa-footer-interactions',
            awaCatalogFlipbook: 'GrupoAwamotos_Theme/js/catalogo-flipbook',
            'Rokanthemes_LayeredAjax/js/layeredajax': 'GrupoAwamotos_Theme/js/awa-layeredajax-stub',
            'Rokanthemes_LayeredAjax/js/price/layeredajaxslider': 'GrupoAwamotos_Theme/js/awa-layeredajax-stub'
        }
    },
    paths: {
        swiper: 'GrupoAwamotos_Theme/js/vendor/swiper-bundle.min',
        awaChartjs: 'GrupoAwamotos_Theme/js/vendor/chart.umd.min',
        'rokanthemes/owl': 'GrupoAwamotos_Theme/js/awa-owl-carousel-stub'
    },
    shim: {
        swiper: {
            exports: 'Swiper'
        },
        'GrupoAwamotos_Theme/js/vendor/swiper-bundle.min': {
            exports: 'Swiper'
        },
        'GrupoAwamotos_Theme/js/vendor/chart.umd.min': {
            exports: 'Chart'
        },
        'GrupoAwamotos_Theme/js/vendor/pdf.min': {
            exports: 'pdfjsLib'
        },
        'GrupoAwamotos_Theme/js/vendor/page-flip.browser': {
            exports: 'St'
        },
        'GrupoAwamotos_Theme/js/awa-owl-carousel-stub': ['jquery'],
        'GrupoAwamotos_Theme/js/awa-bxslider-stub': ['jquery']
    }
};
