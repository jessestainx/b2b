define([], function () {
    'use strict';

    const STORAGE_KEY = 'awa_cookie_consent';
    const COOKIE_NAME = 'awa_cookies_accepted';
    const COOKIE_DAYS = 365;

    function setCookie(name, value, days)
    {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name)
    {
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

    function hasConsented()
    {
        try {
            if (localStorage.getItem(STORAGE_KEY)) {
                return true;
            }
        } catch (e) {
            // localStorage bloqueado (modo privado restrito)
        }
        return getCookie(COOKIE_NAME) !== null;
    }

    function saveConsent(value)
    {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {
            // silencia erros de localStorage
        }
        setCookie(COOKIE_NAME, value, COOKIE_DAYS);
    }

    function syncBannerHeight()
    {
        const banner = document.getElementById('awa-cookie-banner');
        if (!banner || !banner.classList.contains('awa-cookie-banner--visible')) {
            document.documentElement.style.removeProperty('--awa-cookie-banner-height');
            return;
        }

        // Defer layout read — evita forced synchronous layout.
        requestAnimationFrame(function () {
            if (banner.classList.contains('awa-cookie-banner--visible')) {
                document.documentElement.style.setProperty('--awa-cookie-banner-height', banner.offsetHeight + 'px');
            }
        });
    }

    function setBannerActive(isActive)
    {
        if (document.body) {
            document.body.classList.toggle('awa-cookie-banner-active', isActive);
        }

        if (isActive) {
            syncBannerHeight();
            return;
        }

        document.documentElement.style.removeProperty('--awa-cookie-banner-height');
    }

    function hideBanner()
    {
        const banner = document.getElementById('awa-cookie-banner');
        if (banner) {
            let cleaned = false;
            const cleanup = function () {
                if (cleaned) {
                    return;
                }
                cleaned = true;
                banner.style.display = 'none';
                setBannerActive(false);
            };

            banner.classList.remove('awa-cookie-banner--visible');
            banner.addEventListener('transitionend', cleanup, { once: true });
            setTimeout(cleanup, 500);
        }
    }

    function showBanner()
    {
        const banner = document.getElementById('awa-cookie-banner');
        if (!banner) {
            return;
        }
        banner.style.display = 'block';
        // Double-rAF: garante paint de display:block antes da animacao CSS — sem forced layout.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('awa-cookie-banner--visible');
                setBannerActive(true);
            });
        });
    }

    function bindEvents()
    {
        const acceptBtn = document.getElementById('awa-cookie-accept');
        const declineBtn = document.getElementById('awa-cookie-decline');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                saveConsent('all');
                hideBanner();
            });
        }

        if (declineBtn) {
            declineBtn.addEventListener('click', function () {
                saveConsent('essential');
                hideBanner();
            });
        }
    }

    return {
        init: function () {
            if (hasConsented()) {
                return;
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    bindEvents();
                    window.addEventListener('resize', syncBannerHeight);
                    setTimeout(showBanner, 800);
                });
            } else {
                bindEvents();
                window.addEventListener('resize', syncBannerHeight);
                setTimeout(showBanner, 800);
            }
        }
    };
});
