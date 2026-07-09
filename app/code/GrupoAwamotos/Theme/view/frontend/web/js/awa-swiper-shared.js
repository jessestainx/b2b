/**
 * Utilitários compartilhados — inits Swiper AWA (hero, legacy, footer).
 */
define([], function () {
    'use strict';

    function bool(value, fallback)
    {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (typeof value === 'string') {
            return !(value === 'false' || value === '0');
        }
        return !!value;
    }

    function prefersReducedMotion()
    {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function prefersFinePointer()
    {
        return !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);
    }

    /**
     * @param {boolean|number|string|false} value
     * @returns {false|{delay:number,disableOnInteraction:boolean,pauseOnMouseEnter:boolean}}
     */
    function autoplayConfig(value)
    {
        var delay;

        if (value === false || value === 0 || value === '0' || value === 'false') {
            return false;
        }

        delay = parseInt(String(value), 10);
        if (isNaN(delay) || delay < 1000) {
            delay = 5000;
        }

        return {
            delay: delay,
            disableOnInteraction: false,
            pauseOnMouseEnter: prefersFinePointer()
        };
    }

    /**
     * @param {Element|null|undefined} slideEl
     */
    function animateBannerText(slideEl)
    {
        if (!slideEl) {
            return;
        }

        slideEl.querySelectorAll(".text-banner [data-animation^='animated']").forEach(function (item) {
            var animation = item.getAttribute('data-animation');

            if (!animation) {
                return;
            }

            item.classList.add(animation);
            window.setTimeout(function () {
                item.classList.remove(animation);
            }, 900);
        });
    }

    return {
        bool: bool,
        prefersReducedMotion: prefersReducedMotion,
        prefersFinePointer: prefersFinePointer,
        autoplayConfig: autoplayConfig,
        animateBannerText: animateBannerText
    };
});
