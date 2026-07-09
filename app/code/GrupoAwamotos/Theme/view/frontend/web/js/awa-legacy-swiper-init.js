define([
    'swiper',
    'GrupoAwamotos_Theme/js/awa-swiper-shared'
], function (Swiper, swiperShared) {
    'use strict';

    function intValue(value, fallback)
    {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? fallback : parsed;
    }

    function countFromPair(pair, fallback)
    {
        return Array.isArray(pair) ? intValue(pair[1], fallback) : intValue(pair, fallback);
    }

    function qsa(selector, root)
    {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function createButton(className, label, glyph)
    {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.setAttribute('aria-label', label);
        button.innerHTML = '<span aria-hidden="true">' + glyph + '</span>';
        return button;
    }

    function ensureChrome(container, config)
    {
        var mount = container.parentElement || container;
        var prev = config.prevSelector ? mount.querySelector(config.prevSelector) : null;
        var next = config.nextSelector ? mount.querySelector(config.nextSelector) : null;
        var pagination = null;

        if (swiperShared.bool(config.navigation, true) && (!prev || !next)) {
            var nav = mount.querySelector('.awa-legacy-swiper-nav');
            if (!nav) {
                nav = document.createElement('div');
                nav.className = 'awa-legacy-swiper-nav awa-carousel__nav';
                prev = createButton('awa-carousel__arrow awa-carousel__arrow--prev swiper-button-prev', config.prevLabel || 'Anterior', '‹');
                next = createButton('awa-carousel__arrow awa-carousel__arrow--next swiper-button-next', config.nextLabel || 'Próximo', '›');
                nav.appendChild(prev);
                nav.appendChild(next);
                mount.appendChild(nav);
            } else {
                prev = nav.querySelector('.swiper-button-prev');
                next = nav.querySelector('.swiper-button-next');
            }
        }

        if (swiperShared.bool(config.pagination, false)) {
            pagination = mount.querySelector('.swiper-pagination');
            if (!pagination) {
                pagination = document.createElement('div');
                pagination.className = 'swiper-pagination';
                mount.appendChild(pagination);
            }
        }

        return {
            prev: prev,
            next: next,
            pagination: pagination
        };
    }

    function prepareContainer(container, config)
    {
        var wrapper = container.querySelector(':scope > .swiper-wrapper');
        var children;

        container.classList.remove('owl-carousel', 'owl-loaded', 'owl-theme');
        container.classList.add('swiper', 'awa-legacy-swiper');
        container.setAttribute('role', 'region');
        container.setAttribute('aria-roledescription', 'carrossel');
        container.setAttribute('aria-label', config.label || 'Carrossel');

        if (!wrapper) {
            wrapper = document.createElement(container.tagName.toLowerCase() === 'ul' ? 'ul' : 'div');
            wrapper.className = 'swiper-wrapper';
            children = Array.prototype.slice.call(container.children);
            children.forEach(function (child) {
                wrapper.appendChild(child);
            });
            container.appendChild(wrapper);
        }

        Array.prototype.slice.call(wrapper.children).forEach(function (slide) {
            if (slide.nodeType !== 1) {
                return;
            }
            slide.classList.remove('owl-item', 'cloned');
            slide.classList.add('swiper-slide');
            slide.style.removeProperty('width');
            slide.style.removeProperty('display');
        });

        return wrapper;
    }

    function buildOptions(container, config, chrome)
    {
        var items = intValue(config.items, 1);
        var mobile = countFromPair(config.itemsMobile, Math.min(items, 1));
        var tablet = countFromPair(config.itemsTablet, Math.min(items, 2));
        var desktopSmall = countFromPair(config.itemsDesktopSmall, Math.min(items, 3));
        var desktop = countFromPair(config.itemsDesktop, items);
        var speed = intValue(config.slideSpeed || config.speed, 500);
        var autoPlay = config.autoPlay || config.autoplay || false;

        if (swiperShared.prefersReducedMotion()) {
            autoPlay = false;
            speed = 0;
        }

        return {
            slidesPerView: mobile,
            slidesPerGroup: swiperShared.bool(config.scrollPerPage, false) ? mobile : 1,
            speed: speed,
            loop: swiperShared.bool(config.loop, false),
            autoHeight: swiperShared.bool(config.autoHeight, false),
            effect: config.effect || (config.transitionStyle === 'fade' ? 'fade' : 'slide'),
            fadeEffect: {
                crossFade: true
            },
            watchOverflow: true,
            navigation: chrome.prev && chrome.next ? {
                prevEl: chrome.prev,
                nextEl: chrome.next,
                disabledClass: 'is-disabled'
            } : false,
            pagination: chrome.pagination ? {
                el: chrome.pagination,
                clickable: true
            } : false,
            autoplay: swiperShared.bool(autoPlay, false) ? swiperShared.autoplayConfig(autoPlay) : false,
            breakpoints: {
                480: {
                    slidesPerView: mobile,
                    slidesPerGroup: swiperShared.bool(config.scrollPerPage, false) ? mobile : 1
                },
                768: {
                    slidesPerView: tablet,
                    slidesPerGroup: swiperShared.bool(config.scrollPerPage, false) ? tablet : 1
                },
                992: {
                    slidesPerView: desktopSmall,
                    slidesPerGroup: swiperShared.bool(config.scrollPerPage, false) ? desktopSmall : 1
                },
                1200: {
                    slidesPerView: desktop,
                    slidesPerGroup: swiperShared.bool(config.scrollPerPage, false) ? desktop : 1
                }
            },
            on: {
                init: function (swiper) {
                    container.classList.add('awa-legacy-swiper-ready');
                    if (swiperShared.bool(config.animateText, false)) {
                        swiperShared.animateBannerText(swiper.slides[swiper.activeIndex]);
                    }
                },
                slideChange: function (swiper) {
                    if (swiperShared.bool(config.animateText, false)) {
                        swiperShared.animateBannerText(swiper.slides[swiper.activeIndex]);
                    }
                }
            }
        };
    }

    function initOne(container, config)
    {
        var chrome;
        var options;

        if (!container || container.dataset.awaLegacySwiperInit === '1') {
            return;
        }

        if (container.offsetParent === null && window.getComputedStyle(container).display === 'none') {
            return;
        }

        container.dataset.awaLegacySwiperInit = '1';
        prepareContainer(container, config);
        chrome = ensureChrome(container, config);
        options = buildOptions(container, config, chrome);
        container.awaLegacySwiper = new Swiper(container, options);
    }

    return function (config, element) {
        var cfg = config || {};
        var root = element || document;
        var targets = cfg.selector ? qsa(cfg.selector, root) : [root];

        targets.forEach(function (target) {
            if (target && target.nodeType === 1) {
                initOne(target, cfg);
            }
        });
    };
});
