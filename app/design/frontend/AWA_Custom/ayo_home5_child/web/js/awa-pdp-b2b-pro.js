/**
 * AWA PDP B2B Pro — Interactive features
 * Image zoom, tabs, sticky sidebar, smooth scroll
 * 2026-03-28
 */
(function () {
    'use strict';

    if (window.__awaPdpB2bProInit) return;
    window.__awaPdpB2bProInit = true;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    var pdpGalleryLayoutScheduled = false;
    var pdpGalleryLayoutObserver = null;
    var pdpGalleryMutationObserver = null;
    var pdpGalleryLayoutPendingMedia = null;

    function getPdpMediaColumn() {
        return document.querySelector('.catalog-product-view .product.col.media') ||
               document.querySelector('.catalog-product-view .product.media');
    }

    function setPdpGalleryLayout(media) {
        media = media || getPdpMediaColumn();
        if (!media) {
            return;
        }

        var galleryPlaceholder = media.querySelector('.gallery-placeholder');
        var stage = media.querySelector('.fotorama__stage');
        var shaft = media.querySelector('.fotorama__stage__shaft');
        var frame = media.querySelector('.fotorama__stage__frame');
        var wrap = media.querySelector('.fotorama__wrap');
        var item = media.querySelector('.fotorama-item');

        var mediaWidth = Math.max(media.clientWidth || 0, galleryPlaceholder && galleryPlaceholder.clientWidth || 0);
        if (!mediaWidth) {
            return;
        }

        var targetWidth = mediaWidth + 'px';
        var normalizedWidth = Math.max(240, Math.round(mediaWidth));
        var compactHeight = Math.max(220, Math.round(normalizedWidth * 0.68));
        var maxHeight = Math.min(520, compactHeight);

        [galleryPlaceholder, stage, shaft, frame, wrap, item].forEach(function (el) {
            if (!el) return;
            el.style.width = targetWidth;
            el.style.maxWidth = '100%';
            el.style.minWidth = '0px';
            el.style.boxSizing = 'border-box';
        });

        if (galleryPlaceholder) {
            galleryPlaceholder.style.minHeight = compactHeight + 'px';
            galleryPlaceholder.style.maxHeight = maxHeight + 'px';
            galleryPlaceholder.style.overflow = 'hidden';
        }

        if (shaft) {
            shaft.style.left = '0px';
            shaft.style.transform = 'none';
        }
    }

    function schedulePdpGalleryLayout(media) {
        if (media) {
            pdpGalleryLayoutPendingMedia = media;
        }
        if (pdpGalleryLayoutScheduled) {
            return;
        }
        pdpGalleryLayoutScheduled = true;
        if (!window.requestAnimationFrame) {
            setPdpGalleryLayout(pdpGalleryLayoutPendingMedia);
            pdpGalleryLayoutPendingMedia = null;
            pdpGalleryLayoutScheduled = false;
            return;
        }
        window.requestAnimationFrame(function () {
            setPdpGalleryLayout(pdpGalleryLayoutPendingMedia);
            pdpGalleryLayoutPendingMedia = null;
            pdpGalleryLayoutScheduled = false;
        });
    }

    function initPdpGalleryObservers(media) {
        media = media || getPdpMediaColumn();
        if (!media) {
            return;
        }

        if (pdpGalleryLayoutObserver && pdpGalleryLayoutObserver.disconnect) {
            pdpGalleryLayoutObserver.disconnect();
            pdpGalleryLayoutObserver = null;
        }
        if (pdpGalleryMutationObserver && pdpGalleryMutationObserver.disconnect) {
            pdpGalleryMutationObserver.disconnect();
            pdpGalleryMutationObserver = null;
        }

        media.querySelectorAll('.fotorama__stage img, .gallery-placeholder img, .fotorama__img')
            .forEach(function (img) {
                if (img.complete && img.naturalWidth) {
                    return;
                }
                img.addEventListener('load', function () {
                    schedulePdpGalleryLayout(media);
                }, { once: true });
            });

        if (window.ResizeObserver) {
            pdpGalleryLayoutObserver = new ResizeObserver(function () {
                schedulePdpGalleryLayout(media);
            });
            [media,
             media.querySelector('.gallery-placeholder'),
             media.querySelector('.fotorama'),
             media.querySelector('.fotorama__stage'),
             media.querySelector('.fotorama__wrap'),
             media.querySelector('.fotorama__stage__shaft')].forEach(function (el) {
                if (!el) return;
                pdpGalleryLayoutObserver.observe(el);
            });
        }

        if (window.MutationObserver) {
            pdpGalleryMutationObserver = new MutationObserver(function () {
                schedulePdpGalleryLayout(media);
            });
            pdpGalleryMutationObserver.observe(media, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }

        if (typeof jQuery !== 'undefined' && jQuery.fn && media.querySelector('.fotorama')) {
            jQuery(document).off('fotorama:ready.pdpGallery fotorama:show.pdpGallery fotorama:load.pdpGallery');
            jQuery(document).on('fotorama:ready.pdpGallery fotorama:show.pdpGallery fotorama:load.pdpGallery', '.fotorama', function () {
                schedulePdpGalleryLayout(media);
            });
            jQuery(media.querySelector('.fotorama')).on('fotorama:ready.fallbackPdpGallery fotorama:show.fallbackPdpGallery', function () {
                schedulePdpGalleryLayout(media);
            });
        }
    }

    /* ========================================
       1. IMAGE ZOOM OVERLAY
       ======================================== */
    function initImageZoom() {
        let mediaCol = document.querySelector('.catalog-product-view .product.col.media') ||
                       document.querySelector('.catalog-product-view .product.media');
        if (!mediaCol) return;

        // Add zoom trigger button
        let stage = mediaCol.querySelector('.fotorama__stage') ||
                    mediaCol.querySelector('.gallery-placeholder');
        if (!stage) return;

        // Create zoom trigger
        let trigger = document.createElement('button');
        trigger.className = 'awa-pdp-zoom-trigger';
        trigger.setAttribute('type', 'button');
        trigger.setAttribute('aria-label', 'Ampliar imagem');
        trigger.setAttribute('title', 'Ampliar imagem');
        trigger.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>';
        stage.style.position = 'relative';
        stage.appendChild(trigger);

        // Create overlay
        let overlay = document.createElement('div');
        overlay.className = 'awa-pdp-zoom-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', 'Galeria de imagens ampliada');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<button class="awa-pdp-zoom-close" aria-label="Fechar" title="Fechar">&times;</button>' +
            '<button class="awa-pdp-zoom-nav awa-pdp-zoom-nav--prev" aria-label="Imagem anterior" title="Anterior">&#8249;</button>' +
            '<img src="" alt="Imagem ampliada do produto" />' +
            '<button class="awa-pdp-zoom-nav awa-pdp-zoom-nav--next" aria-label="Próxima imagem" title="Próxima">&#8250;</button>';
        document.body.appendChild(overlay);

        let zoomImg = overlay.querySelector('img');
        let images = [];
        let currentIdx = 0;

        function getImages() {
            let imgs = [];
            let fotoramaStage = mediaCol.querySelector('.fotorama');
            if (fotoramaStage && typeof jQuery !== 'undefined') {
                try {
                    let fotorama = jQuery(fotoramaStage).data('fotorama');
                    if (fotorama && fotorama.data) {
                        fotorama.data.forEach(function (item) {
                            if (item.full) imgs.push(item.full);
                            else if (item.img) imgs.push(item.img);
                        });
                    }
                } catch (e) { /* fallback below */ }
            }
            if (!imgs.length) {
                mediaCol.querySelectorAll('.fotorama__stage__frame img').forEach(function (img) {
                    let src = img.getAttribute('src') || img.getAttribute('data-src');
                    if (src && src.indexOf('placeholder') === -1) imgs.push(src);
                });
            }
            if (!imgs.length) {
                let mainImg = mediaCol.querySelector('img');
                if (mainImg) imgs.push(mainImg.src);
            }
            return imgs;
        }

        function showZoom(idx) {
            images = getImages();
            if (!images.length) return;
            currentIdx = Math.max(0, Math.min(idx || 0, images.length - 1));
            zoomImg.src = images[currentIdx];
            zoomImg.alt = 'Imagem ' + (currentIdx + 1) + ' de ' + images.length;
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function hideZoom() {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            trigger.focus();
        }

        function nextImage() {
            if (images.length > 1) {
                currentIdx = (currentIdx + 1) % images.length;
                zoomImg.src = images[currentIdx];
                zoomImg.alt = 'Imagem ' + (currentIdx + 1) + ' de ' + images.length;
            }
        }

        function prevImage() {
            if (images.length > 1) {
                currentIdx = (currentIdx - 1 + images.length) % images.length;
                zoomImg.src = images[currentIdx];
                zoomImg.alt = 'Imagem ' + (currentIdx + 1) + ' de ' + images.length;
            }
        }

        trigger.addEventListener('click', function () { showZoom(0); });
        overlay.querySelector('.awa-pdp-zoom-close').addEventListener('click', hideZoom);
        overlay.querySelector('.awa-pdp-zoom-nav--prev').addEventListener('click', prevImage);
        overlay.querySelector('.awa-pdp-zoom-nav--next').addEventListener('click', nextImage);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) hideZoom();
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('active')) return;
            if (e.key === 'Escape') hideZoom();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
    }

    /* ========================================
       2. TABS SYSTEM
       ======================================== */
    function initTabs() {
        let tabContainer = document.querySelector('.awa-pdp-tabs');
        if (!tabContainer) return;

        let tabBtns = tabContainer.querySelectorAll('.awa-pdp-tabs__nav-btn');
        let tabPanels = tabContainer.querySelectorAll('.awa-pdp-tabs__panel');

        function activateTab(idx) {
            tabBtns.forEach(function (btn, i) {
                let isActive = i === idx;
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('tabindex', isActive ? '0' : '-1');
            });
            tabPanels.forEach(function (panel, i) {
                let isActive = i === idx;
                panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                panel.classList.toggle('active', isActive);
            });
        }

        tabBtns.forEach(function (btn, i) {
            btn.addEventListener('click', function () { activateTab(i); });
            btn.addEventListener('keydown', function (e) {
                let target = -1;
                if (e.key === 'ArrowRight') target = (i + 1) % tabBtns.length;
                if (e.key === 'ArrowLeft') target = (i - 1 + tabBtns.length) % tabBtns.length;
                if (e.key === 'Home') target = 0;
                if (e.key === 'End') target = tabBtns.length - 1;
                if (target >= 0) {
                    e.preventDefault();
                    tabBtns[target].focus();
                    activateTab(target);
                }
            });
        });

        // Auto-activate first tab
        activateTab(0);
    }

    /* ========================================
       3. SHARE FUNCTIONALITY
       ======================================== */
    function initShare() {
        let shareLinks = document.querySelectorAll('[data-awa-share]');
        shareLinks.forEach(function (el) {
            el.addEventListener('click', function (e) {
                let action = el.getAttribute('data-awa-share');
                let url = encodeURIComponent(window.location.href);
                let title = encodeURIComponent(document.title);

                if (action === 'whatsapp') {
                    window.open('https://wa.me/?text=' + title + '%20' + url, '_blank', 'noopener');
                } else if (action === 'email') {
                    window.location.href = 'mailto:?subject=' + title + '&body=' + url;
                } else if (action === 'copy') {
                    e.preventDefault();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(window.location.href).then(function () {
                            let original = el.textContent;
                            el.textContent = 'Copiado!';
                            setTimeout(function () { el.textContent = original; }, 2000);
                        });
                    }
                }
            });
        });
    }

    /* ========================================
       4. LAZY LOADING
       ======================================== */
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            let lazyImages = document.querySelectorAll('.catalog-product-view img[data-src]');
            let observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        let img = entry.target;
                        img.src = img.getAttribute('data-src');
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '200px' });

            lazyImages.forEach(function (img) { observer.observe(img); });
        }
    }

    /* ========================================
       5. SMOOTH SCROLL ON TAB ANCHORS
       ======================================== */
    function initSmoothScroll() {
        document.querySelectorAll('.catalog-product-view a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                let target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    let reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
                    target.focus({ preventScroll: true });
                }
            });
        });
    }

    /* ========================================
       INIT ALL
       ======================================== */
    ready(function () {
        if (!document.body.classList.contains('catalog-product-view')) return;

        // Wait for Fotorama to initialize before adding zoom
        let fotoramaCheck = setInterval(function () {
            let fotorama = document.querySelector('.fotorama__stage');
            if (fotorama) {
                clearInterval(fotoramaCheck);
                var media = getPdpMediaColumn();
                initImageZoom();
                schedulePdpGalleryLayout(media);
                initPdpGalleryObservers(media);
            }
        }, 300);
        // Timeout after 10s
        setTimeout(function () {
            clearInterval(fotoramaCheck);
            initImageZoom();
            var media = getPdpMediaColumn();
            schedulePdpGalleryLayout();
            initPdpGalleryObservers(media);
        }, 10000);

        initTabs();
        initShare();
        initLazyLoad();
        initSmoothScroll();

        schedulePdpGalleryLayout();
        window.addEventListener('resize', function () {
            schedulePdpGalleryLayout();
        }, { passive: true });
        window.addEventListener('orientationchange', function () {
            schedulePdpGalleryLayout();
        });
        window.addEventListener('load', function () {
            schedulePdpGalleryLayout();
        }, { once: true });
    });
})();
