define([], function () {
    'use strict';

    const STORAGE_KEY = 'awa_cookie_consent';
    const COOKIE_NAME = 'awa_cookies_accepted';
    const MAGENTO_COOKIE_NAME = 'user_allowed_save_cookie';
    const COOKIE_DAYS = 365;
    let isInitialized = false;
    let isDelegatedClickBound = false;
    let isResizeBound = false;

    function setCookie(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        const nameEQ = name + '=';
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const c = cookies[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }

    function hasConsented() {
        try {
            if (localStorage.getItem(STORAGE_KEY)) {
                return true;
            }
        } catch (e) {
            // localStorage bloqueado
        }
        return getCookie(COOKIE_NAME) !== null;
    }

    function saveConsent(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {
            // silencia erros de localStorage
        }
        setCookie(COOKIE_NAME, value, COOKIE_DAYS);
        setCookie(MAGENTO_COOKIE_NAME, JSON.stringify({ 1: 1 }), COOKIE_DAYS);
    }

    function hideMagentoNativeCookie() {
        const nativeBlock = document.getElementById('notice-cookie-block');
        if (nativeBlock) {
            nativeBlock.style.display = 'none';
            nativeBlock.setAttribute('aria-hidden', 'true');
        }
    }

    function applyConsent(value) {
        saveConsent(value);
        hideMagentoNativeCookie();
        hideBanner();
    }

    function syncBannerHeight() {
        const banner = document.getElementById('awa-cookie-banner');
        if (!banner || !banner.classList.contains('awa-cookie-banner--visible')) {
            document.documentElement.style.removeProperty('--awa-cookie-banner-height');
            return;
        }

        requestAnimationFrame(function () {
            if (banner.classList.contains('awa-cookie-banner--visible')) {
                document.documentElement.style.setProperty('--awa-cookie-banner-height', banner.offsetHeight + 'px');
            }
        });
    }

    function setBannerActive(isActive) {
        if (document.body) {
            document.body.classList.toggle('awa-cookie-banner-active', isActive);
        }

        if (isActive) {
            syncBannerHeight();
            return;
        }

        document.documentElement.style.removeProperty('--awa-cookie-banner-height');
    }

    function hideBanner() {
        const banner = document.getElementById('awa-cookie-banner');
        if (!banner) {
            setBannerActive(false);
            return;
        }

        let cleaned = false;
        const cleanup = function () {
            if (cleaned) {
                return;
            }
            cleaned = true;
            if (document.activeElement && banner.contains(document.activeElement)) {
                document.activeElement.blur();
            }
            banner.classList.remove('awa-cookie-banner--visible');
            banner.setAttribute('aria-hidden', 'true');
            banner.setAttribute('hidden', 'hidden');
            banner.style.display = 'none';
            banner.style.pointerEvents = 'none';
            setBannerActive(false);
        };

        banner.classList.remove('awa-cookie-banner--visible');
        banner.addEventListener('transitionend', cleanup, { once: true });
        window.setTimeout(cleanup, 450);
    }

    function showBanner() {
        const banner = document.getElementById('awa-cookie-banner');
        if (!banner) {
            return;
        }

        banner.removeAttribute('hidden');
        banner.setAttribute('aria-hidden', 'false');
        banner.style.display = 'block';
        banner.style.pointerEvents = 'auto';

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('awa-cookie-banner--visible');
                setBannerActive(true);
            });
        });
    }

    function bindButtonHandler(button, consentValue) {
        if (!button || button.getAttribute('data-awa-cookie-bound') === '1') {
            return;
        }

        button.setAttribute('data-awa-cookie-bound', '1');
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            applyConsent(consentValue);
        });
    }

    function bindEvents() {
        bindButtonHandler(document.getElementById('awa-cookie-accept'), 'all');
        bindButtonHandler(document.getElementById('awa-cookie-decline'), 'essential');

        if (isDelegatedClickBound) {
            return;
        }

        document.addEventListener('click', function (event) {
            const target = event.target && event.target.closest
                ? event.target.closest('#awa-cookie-accept, #awa-cookie-decline')
                : null;

            if (!target) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (target.id === 'awa-cookie-accept') {
                applyConsent('all');
                return;
            }

            if (target.id === 'awa-cookie-decline') {
                applyConsent('essential');
            }
        }, true);
        isDelegatedClickBound = true;
    }

    return {
        init: function () {
            if (isInitialized) {
                return;
            }
            isInitialized = true;

            if (hasConsented()) {
                hideMagentoNativeCookie();
                hideBanner();
                return;
            }

            hideMagentoNativeCookie();

            function startBannerFlow() {
                bindEvents();
                if (!isResizeBound) {
                    window.addEventListener('resize', syncBannerHeight);
                    isResizeBound = true;
                }
                window.setTimeout(showBanner, 400);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startBannerFlow, { once: true });
            } else {
                startBannerFlow();
            }
        }
    };
});
