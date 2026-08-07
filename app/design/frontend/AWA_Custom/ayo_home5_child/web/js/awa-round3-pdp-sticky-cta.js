(function () {
    'use strict';

    function reportError(context, error) {
        if (typeof console !== 'undefined' && console && typeof console.warn === 'function') {
            console.warn('[AWA Sticky CTA]', context, error);
        }
    }

    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function hasValidCartActionFromAction(action) {
        if (typeof action !== 'string') {
            return false;
        }
        return /\/checkout\/cart\/add\/?/i.test(action);
    }

    function isInvalidStickyLabel(label) {
        return /\b(entrar|login|cadastro|acessar)\b/i.test(normalizeText(label));
    }

    if (typeof module === 'object' && module.exports) {
        module.exports = {
            normalizeText: normalizeText,
            hasValidCartActionFromAction: hasValidCartActionFromAction,
            isInvalidStickyLabel: isInvalidStickyLabel
        };
        return;
    }

    if (window.__awaRound3PdpStickyCtaInit) {
        return;
    }
    window.__awaRound3PdpStickyCtaInit = true;

    let MOBILE_QUERY = '(max-width: 767px)';
    let REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
    let INVALID_STICKY_LABEL_RE = /\b(entrar|login|cadastro|acessar)\b/i;
    let stickyStarted = false;
    let deferredRetryBound = false;
    let deferredRetryObserver = null;
    let deferredRetryIntervalId = null;
    let deferredRetryTimeoutId = null;
    let SELECTORS = {
        addToCart: '#product-addtocart-button',
        addToCartForm: '#product_addtocart_form',
        b2bPriceGate: '.product-info-main .b2b-login-to-see-price',
        b2bLoginButton: '.product-add-form .b2b-login-to-buy-btn',
        b2bPendingBanner: '#b2b-pending-banner',
        media: '.product.col.media, .product.media, .column.main .fotorama__stage, .gallery-placeholder',
        price: '.product-info-main .price-box .price-final_price .price, .product-info-main .price-box .price',
        productInfoMain: '.product-info-main'
    };

    function setAttrIfMissing(el, name, value) {
        if (el && !el.getAttribute(name)) {
            el.setAttribute(name, value);
        }
    }

    function isVisible(el) {
        if (!el) {
            return false;
        }
        if (el.hidden || el.getAttribute('aria-hidden') === 'true') {
            return false;
        }
        return !!(el.offsetWidth || el.offsetHeight || (el.getClientRects && el.getClientRects().length));
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia(REDUCED_MOTION_QUERY).matches);
    }

    function getButtonLabel(button) {
        return normalizeText(button ? (button.textContent || '') : '');
    }

    function clearDeferredRetry() {
        if (deferredRetryObserver) {
            deferredRetryObserver.disconnect();
            deferredRetryObserver = null;
        }

        if (deferredRetryIntervalId) {
            window.clearInterval(deferredRetryIntervalId);
            deferredRetryIntervalId = null;
        }

        if (deferredRetryTimeoutId) {
            window.clearTimeout(deferredRetryTimeoutId);
            deferredRetryTimeoutId = null;
        }

        deferredRetryBound = false;
    }

    function resolveAddToCartButton() {
        let button = document.querySelector(SELECTORS.addToCart);
        let form;

        if (!button) {
            return null;
        }

        if (button.getAttribute('data-b2b-original-hidden') === '1') {
            return null;
        }

        form = button.form || button.closest(SELECTORS.addToCartForm);
        if (!form || form.id !== 'product_addtocart_form') {
            return null;
        }

        return button;
    }

    function hasValidCartAction(form) {
        let action;

        if (!form || !form.getAttribute) {
            return false;
        }

        action = form.getAttribute('action') || '';
        return hasValidCartActionFromAction(action);
    }

    function hasVisibleB2bPriceGate() {
        let gate = document.querySelector(SELECTORS.b2bPriceGate);
        return isVisible(gate);
    }

    function hasVisibleB2bLoginReplacement() {
        let replacement = document.querySelector(SELECTORS.b2bLoginButton);
        return isVisible(replacement);
    }

    function hasVisiblePendingBanner() {
        let pendingBanner = document.querySelector(SELECTORS.b2bPendingBanner);
        return isVisible(pendingBanner);
    }

    function hasRestrictedB2bBodyState() {
        let body = document.body;

        if (!body) {
            return false;
        }

        return body.classList.contains('b2b-restricted-mode') ||
            body.classList.contains('b2b-pending-mode');
    }

    function hasKnownRestrictedB2bContext() {
        return hasRestrictedB2bBodyState() ||
            hasVisibleB2bPriceGate() ||
            hasVisibleB2bLoginReplacement() ||
            hasVisiblePendingBanner();
    }

    function isRestrictedB2bContext(button) {
        let form = button ? (button.form || button.closest(SELECTORS.addToCartForm)) : null;
        let gateVisible = hasVisibleB2bPriceGate();

        if (hasRestrictedB2bBodyState()) {
            return true;
        }

        if (hasVisibleB2bLoginReplacement() || hasVisiblePendingBanner()) {
            return true;
        }

        if (!form || !hasValidCartAction(form)) {
            return true;
        }

        if (gateVisible) {
            return true;
        }

        return false;
    }

    function isStickyCapableAddToCartButton(button) {
        let form;
        let label;

        if (!button) {
            return false;
        }

        form = button.form || button.closest(SELECTORS.addToCartForm);
        if (!form || form.id !== 'product_addtocart_form') {
            return false;
        }

        if (!hasValidCartAction(form)) {
            return false;
        }

        if (!isVisible(button)) {
            return false;
        }

        label = getButtonLabel(button);
        if (label && INVALID_STICKY_LABEL_RE.test(label)) {
            return false;
        }

        if (isRestrictedB2bContext(button)) {
            return false;
        }

        return true;
    }

    function isActionableAddToCartButton(button) {
        if (!isStickyCapableAddToCartButton(button)) {
            return false;
        }

        if (button.disabled || button.getAttribute('aria-disabled') === 'true') {
            return false;
        }

        if (button.classList && button.classList.contains('disabled')) {
            return false;
        }

        return true;
    }

    function enhanceQtyControls() {
        let qtyInput = document.getElementById('qty');
        let qtyUp = document.querySelector('.info-qty .qty-up');
        let qtyDown = document.querySelector('.info-qty .qty-down');

        if (qtyInput) {
            setAttrIfMissing(qtyInput, 'inputmode', 'numeric');
            setAttrIfMissing(qtyInput, 'pattern', '[0-9]*');
            setAttrIfMissing(qtyInput, 'min', '1');
            setAttrIfMissing(qtyInput, 'aria-label', 'Quantidade');
            setAttrIfMissing(qtyInput, 'title', 'Quantidade');
        }

        if (qtyUp) {
            qtyUp.setAttribute('role', 'button');
            setAttrIfMissing(qtyUp, 'aria-label', 'Aumentar quantidade');
            setAttrIfMissing(qtyUp, 'title', 'Aumentar quantidade');
        }

        if (qtyDown) {
            qtyDown.setAttribute('role', 'button');
            setAttrIfMissing(qtyDown, 'aria-label', 'Diminuir quantidade');
            setAttrIfMissing(qtyDown, 'title', 'Diminuir quantidade');
        }
    }

    function getMediaSentinel() {
        let nodes = document.querySelectorAll(SELECTORS.media);
        let i;
        let rect;

        for (i = 0; i < nodes.length; i += 1) {
            rect = nodes[i].getBoundingClientRect();
            if (rect.width > 0 && rect.height > 80) {
                return nodes[i];
            }
        }

        return null;
    }

    function getPriceText() {
        let node = document.querySelector(SELECTORS.price);
        return normalizeText(node ? (node.textContent || '') : '');
    }

    function createStickyUi(getButton) {
        let bar = document.createElement('div');
        bar.className = 'awa-pdp-sticky-cta';
        bar.setAttribute('aria-hidden', 'true');
        bar.innerHTML = '<div class="awa-pdp-sticky-cta__inner" role="region" aria-label="Atalho de compra do produto"><div class="awa-pdp-sticky-cta__meta"><span class="awa-pdp-sticky-cta__label">Comprar agora</span><span class="awa-pdp-sticky-cta__price"></span></div><button type="button" class="awa-pdp-sticky-cta__button" title="Comprar" aria-label="Comprar">Comprar</button></div>';
        document.body.appendChild(bar);

        let stickyButton = bar.querySelector('.awa-pdp-sticky-cta__button');
        let stickyPrice = bar.querySelector('.awa-pdp-sticky-cta__price');

        function getLiveButton() {
            if (typeof getButton !== 'function') {
                return null;
            }
            return getButton();
        }

        stickyButton.addEventListener('click', function () {
            let behavior = prefersReducedMotion() ? 'auto' : 'smooth';
            let button = getLiveButton();

            if (!button) {
                return;
            }

            if (!isActionableAddToCartButton(button)) {
                button.focus();
                return;
            }

            try {
                button.scrollIntoView({ block: 'center', behavior: behavior });
            } catch (e) {
                try {
                    button.scrollIntoView();
                } catch (innerError) {
                    reportError('scrollIntoView', innerError);
                }
            }

            window.setTimeout(function () {
                try {
                    button.click();
                } catch (e) {
                    reportError('button click', e);
                }
            }, prefersReducedMotion() ? 0 : 120);
        });

        function syncFromOriginal() {
            let button = getLiveButton();
            let label = getButtonLabel(button) || 'Comprar';
            let priceText = getPriceText();
            let canAct = isActionableAddToCartButton(button);

            stickyButton.textContent = label;
            stickyButton.title = label;
            stickyButton.setAttribute('aria-label', label);
            stickyButton.disabled = !canAct;
            bar.classList.toggle('awa-pdp-sticky-cta--disabled', !canAct);
            stickyPrice.classList.toggle('awa-pdp-sticky-cta__price--muted', !priceText);
            stickyPrice.textContent = priceText || 'Confira condições';
        }

        syncFromOriginal();

        if (window.MutationObserver) {
            let observeTarget = document.querySelector(SELECTORS.productInfoMain) || document.body;

            new MutationObserver(function () {
                try {
                    syncFromOriginal();
                } catch (e) {
                    reportError('mutation sync', e);
                }
            }).observe(observeTarget, {
                childList: true,
                subtree: true,
                attributes: true,
                characterData: true
            });
        }

        return {
            root: bar,
            sync: syncFromOriginal
        };
    }

    function init() {
        if (!document.body || !document.body.classList.contains('catalog-product-view')) {
            return;
        }

        if (stickyStarted) {
            return;
        }

        enhanceQtyControls();

        let addToCartButton = resolveAddToCartButton();
        let productInfo = document.querySelector(SELECTORS.productInfoMain);
        if (!addToCartButton || !productInfo || !isStickyCapableAddToCartButton(addToCartButton)) {
            if (productInfo && hasKnownRestrictedB2bContext()) {
                clearDeferredRetry();
                return;
            }
            scheduleDeferredInit();
            return;
        }

        let mediaSentinel = getMediaSentinel();
        if (!mediaSentinel) {
            scheduleDeferredInit();
            return;
        }

        if (document.querySelector('.awa-pdp-sticky-cta')) {
            stickyStarted = true;
            clearDeferredRetry();
            return;
        }

        stickyStarted = true;
        clearDeferredRetry();

        let sticky = createStickyUi(function () {
            let liveButton = resolveAddToCartButton();
            if (liveButton && liveButton !== addToCartButton) {
                addToCartButton = liveButton;
            }
            return addToCartButton;
        });

        let body = document.body;
        let mq = window.matchMedia ? window.matchMedia(MOBILE_QUERY) : null;

        function shouldShowSticky() {
            let button = resolveAddToCartButton();
            if (mq && !mq.matches) {
                return false;
            }
            if (!button || !document.contains(button) || button.closest('.awa-pdp-sticky-cta')) {
                return false;
            }
            return isActionableAddToCartButton(button);
        }

        function setVisible(visible) {
            body.classList.toggle('awa-pdp-sticky-cta-visible', !!visible);
            body.classList.toggle('awa-pdp-sticky-cta-ready', shouldShowSticky());
            sticky.root.setAttribute('aria-hidden', visible ? 'false' : 'true');
        }

        if (window.IntersectionObserver) {
            new IntersectionObserver(function (entries) {
                let entry = entries[0];
                let isVisibleNow = shouldShowSticky() && entry && !entry.isIntersecting;
                setVisible(isVisibleNow);
                try {
                    sticky.sync();
                } catch (e) {
                    reportError('intersection sync', e);
                }
            }, { root: null, threshold: 0.05 }).observe(mediaSentinel);
        } else {
            let onScroll = function () {
                let rect = mediaSentinel.getBoundingClientRect();
                setVisible(shouldShowSticky() && rect.bottom < 0);
                try {
                    sticky.sync();
                } catch (e) {
                    reportError('scroll sync', e);
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll);
            onScroll();
        }

        if (mq && mq.addEventListener) {
            mq.addEventListener('change', function () {
                try {
                    sticky.sync();
                } catch (e) {
                    reportError('mq sync', e);
                }
                if (!mq.matches) {
                    setVisible(false);
                }
            });
        }

        window.addEventListener('resize', function () {
            try {
                sticky.sync();
            } catch (e) {
                reportError('resize sync', e);
            }
        }, { passive: true });
    }

    function scheduleDeferredInit() {
        if (deferredRetryBound || stickyStarted || !document.body) {
            return;
        }

        deferredRetryBound = true;

        deferredRetryIntervalId = window.setInterval(function () {
            init();
        }, 450);

        deferredRetryTimeoutId = window.setTimeout(function () {
            clearDeferredRetry();
        }, 30000);

        if (window.MutationObserver) {
            deferredRetryObserver = new MutationObserver(function () {
                init();
            });
            deferredRetryObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
