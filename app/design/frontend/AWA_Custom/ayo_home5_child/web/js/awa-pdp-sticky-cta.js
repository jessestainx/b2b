(function (root, factory) {
    "use strict";

    let api = factory(root, root && root.document ? root.document : null);
    if (typeof module === "object" && module.exports) {
        module.exports = api;
    }
})(typeof window !== "undefined" ? window : globalThis, function (root, document) {
    "use strict";

    let MOBILE_QUERY = "(max-width: 767px)";
    let REDUCED_MOTION_QUERY = "(prefers-reduced-motion: reduce)";
    let INVALID_STICKY_LABEL_RE = /\b(entrar|login|cadastro|acessar)\b/i;

    let SELECTORS = {
        addToCart: "#product-addtocart-button",
        addToCartForm: "#product_addtocart_form",
        b2bPriceGate: ".product-info-main .b2b-login-to-see-price",
        b2bLoginButton: ".product-add-form .b2b-login-to-buy-btn",
        b2bPendingBanner: "#b2b-pending-banner",
        media: ".product.col.media, .product.media, .column.main .fotorama__stage, .gallery-placeholder",
        price: ".product-info-price .price-box .price-wrapper .price, .product-info-main .price-box .price-final_price .price, .product-info-main .price-box .price",
        productName: ".page-title-wrapper .page-title .base, h1.page-title .base, .product-info-main .page-title .base",
        productInfoMain: ".product-info-main"
    };

    function reportError(context, error) {
        if (root && root.console && typeof root.console.warn === "function") {
            root.console.warn("[AWA Sticky CTA] " + context, error);
        }
    }

    function safeInvoke(context, callback, fallback) {
        try {
            return callback();
        } catch (error) {
            reportError(context, error);
            return fallback;
        }
    }

    function normalizeText(value) {
        if (typeof value !== "string") {
            return "";
        }
        return value.replace(/\s+/g, " ").trim();
    }

    function hasValidCartAction(action) {
        if (typeof action !== "string") {
            return false;
        }
        return /\/checkout\/cart\/add\/?/i.test(action);
    }

    function isInvalidStickyLabel(label) {
        return INVALID_STICKY_LABEL_RE.test(normalizeText(label));
    }

    function isVisible(element) {
        if (!element) {
            return false;
        }
        if (element.hidden || element.getAttribute("aria-hidden") === "true") {
            return false;
        }
        return !!(
            element.offsetWidth ||
            element.offsetHeight ||
            (element.getClientRects && element.getClientRects().length)
        );
    }

    let exportedApi = {
        normalizeText: normalizeText,
        hasValidCartAction: hasValidCartAction,
        isInvalidStickyLabel: isInvalidStickyLabel,
        isVisible: isVisible
    };

    if (!document) {
        return exportedApi;
    }

    if (root.__awaRound3PdpStickyCtaInit) {
        return exportedApi;
    }
    root.__awaRound3PdpStickyCtaInit = true;

    let stickyStarted = false;
    let deferredRetryBound = false;
    let deferredRetryObserver = null;
    let deferredRetryIntervalId = null;
    let deferredRetryTimeoutId = null;

    function setAttrIfMissing(el, name, value) {
        if (el && !el.getAttribute(name)) {
            el.setAttribute(name, value);
        }
    }

    function prefersReducedMotion() {
        return !!(root.matchMedia && root.matchMedia(REDUCED_MOTION_QUERY).matches);
    }

    function getButtonLabel(button) {
        return normalizeText(button ? (button.textContent || "") : "");
    }

    function clearDeferredRetry() {
        if (deferredRetryObserver) {
            deferredRetryObserver.disconnect();
            deferredRetryObserver = null;
        }
        if (deferredRetryIntervalId) {
            root.clearInterval(deferredRetryIntervalId);
            deferredRetryIntervalId = null;
        }
        if (deferredRetryTimeoutId) {
            root.clearTimeout(deferredRetryTimeoutId);
            deferredRetryTimeoutId = null;
        }
        deferredRetryBound = false;
    }

    function resolveAddToCartButton() {
        let button = document.querySelector(SELECTORS.addToCart);
        let form;
        if (!button || button.getAttribute("data-b2b-original-hidden") === "1") {
            return null;
        }
        form = button.form || button.closest(SELECTORS.addToCartForm);
        if (!form || form.id !== "product_addtocart_form") {
            return null;
        }
        return button;
    }

    function hasValidCartActionForm(form) {
        if (!form || !form.getAttribute) {
            return false;
        }
        return hasValidCartAction(form.getAttribute("action") || "");
    }

    function isRestrictedB2bContext(button) {
        let body = document.body;
        let form = button ? (button.form || button.closest(SELECTORS.addToCartForm)) : null;
        let gateVisible = isVisible(document.querySelector(SELECTORS.b2bPriceGate));

        if (body && (body.classList.contains("b2b-restricted-mode") || body.classList.contains("b2b-pending-mode"))) {
            return true;
        }
        if (isVisible(document.querySelector(SELECTORS.b2bLoginButton)) || isVisible(document.querySelector(SELECTORS.b2bPendingBanner))) {
            return true;
        }
        if (!form || !hasValidCartActionForm(form)) {
            return true;
        }
        return gateVisible;
    }

    function isStickyCapableAddToCartButton(button) {
        let form;
        if (!button || !isVisible(button)) {
            return false;
        }
        form = button.form || button.closest(SELECTORS.addToCartForm);
        if (!form || form.id !== "product_addtocart_form" || !hasValidCartActionForm(form)) {
            return false;
        }
        if (isInvalidStickyLabel(getButtonLabel(button))) {
            return false;
        }
        return !isRestrictedB2bContext(button);
    }

    function isActionableAddToCartButton(button) {
        if (!isStickyCapableAddToCartButton(button)) {
            return false;
        }
        if (button.disabled || button.getAttribute("aria-disabled") === "true") {
            return false;
        }
        return !(button.classList && button.classList.contains("disabled"));
    }

    function enhanceQtyControls() {
        let qtyInput = document.getElementById("qty");
        let qtyUp = document.querySelector(".info-qty .qty-up");
        let qtyDown = document.querySelector(".info-qty .qty-down");
        if (qtyInput) {
            setAttrIfMissing(qtyInput, "inputmode", "numeric");
            setAttrIfMissing(qtyInput, "pattern", "[0-9]*");
            setAttrIfMissing(qtyInput, "min", "1");
            setAttrIfMissing(qtyInput, "aria-label", "Quantidade");
            setAttrIfMissing(qtyInput, "title", "Quantidade");
        }
        if (qtyUp) {
            qtyUp.setAttribute("role", "button");
            setAttrIfMissing(qtyUp, "aria-label", "Aumentar quantidade");
            setAttrIfMissing(qtyUp, "title", "Aumentar quantidade");
        }
        if (qtyDown) {
            qtyDown.setAttribute("role", "button");
            setAttrIfMissing(qtyDown, "aria-label", "Diminuir quantidade");
            setAttrIfMissing(qtyDown, "title", "Diminuir quantidade");
        }
    }

    function getMediaSentinel() {
        let nodes = document.querySelectorAll(SELECTORS.media);
        let i;
        for (i = 0; i < nodes.length; i += 1) {
            if (safeInvoke("media sentinel bounds", function () {
                let rect = nodes[i].getBoundingClientRect();
                return rect.width > 0 && rect.height > 80;
            }, false)) {
                return nodes[i];
            }
        }
        return null;
    }

    function createStickyUi(getButton) {
        let bar = document.createElement("div");
        bar.className = "awa-pdp-sticky-cta";
        bar.setAttribute("aria-hidden", "true");
        bar.innerHTML = '<div class="awa-pdp-sticky-cta__inner" role="region" aria-label="Atalho de compra do produto">' +
            '<div class="awa-pdp-sticky-cta__meta">' +
            '<span class="awa-pdp-sticky-cta__name" hidden></span>' +
            '<span class="awa-pdp-sticky-cta__label">Comprar agora</span>' +
            '<span class="awa-pdp-sticky-cta__price"></span>' +
            '</div>' +
            '<button type="button" class="awa-pdp-sticky-cta__button" title="Comprar" aria-label="Comprar">Comprar</button>' +
            '</div>';
        document.body.appendChild(bar);

        let stickyButton = bar.querySelector(".awa-pdp-sticky-cta__button");
        let stickyPrice = bar.querySelector(".awa-pdp-sticky-cta__price");
        let stickyName = bar.querySelector(".awa-pdp-sticky-cta__name");

        function getLiveButton() {
            return typeof getButton === "function" ? getButton() : null;
        }

        function syncFromOriginal() {
            let originalButton = getLiveButton();
            let label = getButtonLabel(originalButton) || "Comprar";
            let priceNode = document.querySelector(SELECTORS.price);
            let priceText = normalizeText(priceNode ? (priceNode.textContent || "") : "");
            let nameNode = document.querySelector(SELECTORS.productName);
            let nameText = normalizeText(nameNode ? (nameNode.textContent || "") : "");
            let canAct = isActionableAddToCartButton(originalButton);
            stickyButton.textContent = label;
            stickyButton.title = label;
            stickyButton.setAttribute("aria-label", nameText ? label + " — " + nameText : label);
            stickyButton.disabled = !canAct;
            bar.classList.toggle("awa-pdp-sticky-cta--disabled", !canAct);
            stickyPrice.classList.toggle("awa-pdp-sticky-cta__price--muted", !priceText);
            stickyPrice.textContent = priceText || "Confira condições";

            if (stickyName) {
                if (nameText) {
                    stickyName.textContent = nameText;
                    stickyName.hidden = false;
                } else {
                    stickyName.textContent = "";
                    stickyName.hidden = true;
                }
            }
        }

        stickyButton.addEventListener("click", function () {
            let originalButton = getLiveButton();
            let behavior = prefersReducedMotion() ? "auto" : "smooth";
            if (!originalButton) {
                return;
            }
            if (!isActionableAddToCartButton(originalButton)) {
                originalButton.focus();
                return;
            }
            safeInvoke("sticky scrollIntoView", function () {
                originalButton.scrollIntoView({ block: "center", behavior: behavior });
            }, null);
            root.setTimeout(function () {
                safeInvoke("sticky add-to-cart click", function () {
                    originalButton.click();
                }, null);
            }, prefersReducedMotion() ? 0 : 120);
        });

        syncFromOriginal();

        if (root.MutationObserver) {
            let observeTarget = document.querySelector(SELECTORS.productInfoMain) || document.body;
            new root.MutationObserver(function () {
                safeInvoke("sticky mutation sync", syncFromOriginal, null);
            }).observe(observeTarget, {
                childList: true,
                subtree: true,
                attributes: true,
                characterData: true
            });
        }

        return { root: bar, sync: syncFromOriginal };
    }

    function scheduleDeferredInit(init) {
        if (deferredRetryBound || stickyStarted || !document.body) {
            return;
        }
        deferredRetryBound = true;
        deferredRetryIntervalId = root.setInterval(function () {
            safeInvoke("deferred init interval", init, null);
        }, 450);
        deferredRetryTimeoutId = root.setTimeout(clearDeferredRetry, 30000);
        if (root.MutationObserver) {
            deferredRetryObserver = new root.MutationObserver(function () {
                safeInvoke("deferred init mutation", init, null);
            });
            deferredRetryObserver.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ["class", "style", "disabled", "aria-hidden"]
            });
        }
    }

    function init() {
        let addToCartButton;
        let mediaSentinel;
        let sticky;
        let body;
        let mq;

        if (!document.body || !document.body.classList.contains("catalog-product-view") || stickyStarted) {
            return;
        }

        enhanceQtyControls();
        addToCartButton = resolveAddToCartButton();
        if (!addToCartButton || !document.querySelector(SELECTORS.productInfoMain) || !isStickyCapableAddToCartButton(addToCartButton)) {
            scheduleDeferredInit(init);
            return;
        }

        mediaSentinel = getMediaSentinel();
        if (!mediaSentinel) {
            scheduleDeferredInit(init);
            return;
        }

        if (document.querySelector(".awa-pdp-sticky-cta")) {
            stickyStarted = true;
            clearDeferredRetry();
            return;
        }

        stickyStarted = true;
        clearDeferredRetry();
        sticky = createStickyUi(function () {
            let liveButton = resolveAddToCartButton();
            if (liveButton && liveButton !== addToCartButton) {
                addToCartButton = liveButton;
            }
            return addToCartButton;
        });
        body = document.body;
        mq = root.matchMedia ? root.matchMedia(MOBILE_QUERY) : null;

        function shouldShowSticky() {
            let liveButton = resolveAddToCartButton();
            if (mq && !mq.matches) {
                return false;
            }
            if (!liveButton || !document.contains(liveButton) || liveButton.closest(".awa-pdp-sticky-cta")) {
                return false;
            }
            return isActionableAddToCartButton(liveButton);
        }

        function setVisible(visible) {
            body.classList.toggle("awa-pdp-sticky-cta-visible", !!visible);
            body.classList.toggle("awa-pdp-sticky-cta-ready", shouldShowSticky());
            sticky.root.setAttribute("aria-hidden", visible ? "false" : "true");
        }

        if (root.IntersectionObserver) {
            new root.IntersectionObserver(function (entries) {
                let entry = entries[0];
                let isVisibleNow = shouldShowSticky() && entry && !entry.isIntersecting;
                setVisible(isVisibleNow);
                safeInvoke("intersection sticky sync", sticky.sync, null);
            }, { root: null, threshold: 0.05 }).observe(mediaSentinel);
        } else {
            let onScroll = function () {
                let rect = mediaSentinel.getBoundingClientRect();
                setVisible(shouldShowSticky() && rect.bottom < 0);
                safeInvoke("fallback scroll sticky sync", sticky.sync, null);
            };
            root.addEventListener("scroll", onScroll, { passive: true });
            root.addEventListener("resize", onScroll);
            onScroll();
        }

        if (mq && mq.addEventListener) {
            mq.addEventListener("change", function () {
                safeInvoke("media query sticky sync", sticky.sync, null);
                if (!mq.matches) {
                    setVisible(false);
                }
            });
        }

        root.addEventListener("resize", function () {
            safeInvoke("window resize sticky sync", sticky.sync, null);
        }, { passive: true });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            safeInvoke("DOMContentLoaded init", init, null);
        }, { once: true });
    } else {
        safeInvoke("immediate init", init, null);
    }

    return exportedApi;
});
