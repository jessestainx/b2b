/**
 * AWA Motos — awa-nav-cls-fix-reset.js
 *
 * Problema: o "AWA CLS Fix v5" (script inline no HTML do tema pai) define
 *   height: 0px !important; min-height: 0px !important; overflow: hidden !important;
 * no elemento .awa-nav-bar__inner para mobile (max-width: 991px) como otimização
 * de CLS. No entanto, o script de reset (awa-header-a11y-performance.js) não
 * limpa esses estilos inline no .awa-nav-bar__inner — apenas em .container,
 * .row, .menu_left_home1, etc.
 *
 * Resultado: usuários que carregam a página em mobile e redimensionam para
 * desktop, ou ambientes de teste com viewport inicial pequeno, ficam com o
 * nav bar inner colapsado e o menu vertical invisível.
 *
 * Solução: remover as propriedades inline injetadas pelo CLS fix quando o
 * viewport é desktop (> 991px). Executa no DOMContentLoaded e também em
 * resize, garantindo consistência.
 */
(function () {
    'use strict';

    let MOBILE_MAX = 991;

    function isDesktop() {
        return !(window.matchMedia && window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)').matches);
    }

    function resetNavBarInner() {
        if (!isDesktop()) {
            return;
        }

        let inner = document.querySelector('.awa-nav-bar__inner');

        if (!inner) {
            return;
        }

        inner.style.removeProperty('height');
        inner.style.removeProperty('min-height');
        inner.style.removeProperty('overflow');
    }
function onReady() {
        resetNavBarInner();
}

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady, { once: false });
    } else {
        onReady();
    }

    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            resetNavBarInner();
}, 100);
    });
}());
