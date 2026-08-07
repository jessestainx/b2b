/**
 * Catálogo em revista — PDF.js + page-flip com render sob demanda.
 * Vendors UMD são carregados fora do AMD (define temporariamente isolado).
 */
define(['jquery'], function ($) {
    'use strict';

    var WINDOW_RADIUS = 2;
    var vendorsPromise = null;

    /**
     * @param {Object} config
     * @param {String} config.pdfUrl
     * @param {String} config.workerUrl
     * @param {String} config.pdfJsUrl
     * @param {String} config.pageFlipUrl
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var root = element || document.getElementById('awa-catalog-flipbook');
        var $root = $(root);
        var $status = $root.find('#awa-catalog-flipbook-status');
        var $stage = $root.find('#awa-catalog-flipbook-stage');
        var $controls = $root.find('#awa-catalog-flipbook-controls');
        var $fallback = $root.find('#awa-catalog-flipbook-fallback');
        var $book = $root.find('#awa-catalog-flipbook-book');
        var $counter = $root.find('#awa-catalog-counter');
        var bookEl = $book.get(0);
        var pageFlip = null;
        var pdfDoc = null;
        var pdfjsLib = null;
        var St = null;
        var scale = 1;
        var numPages = 0;
        var pageEls = [];
        var rendered = {};
        var inflight = {};

        if (!config || !config.pdfUrl || !config.pdfJsUrl || !config.pageFlipUrl || !bookEl) {
            showFallback();
            return;
        }

        loadVendors(config.pdfJsUrl, config.pageFlipUrl)
            .then(function () {
                pdfjsLib = window.pdfjsLib;
                St = window.St;
                if (!pdfjsLib || typeof pdfjsLib.getDocument !== 'function' || !St || !St.PageFlip) {
                    throw new Error('Vendor globals missing');
                }
                if (config.workerUrl) {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerUrl;
                }
                return pdfjsLib.getDocument({
                    url: config.pdfUrl,
                    disableAutoFetch: true,
                    disableStream: false
                }).promise;
            })
            .then(bootstrapLazyFlipbook)
            .catch(showFallback);

        function loadVendors(pdfJsUrl, pageFlipUrl)
        {
            if (window.pdfjsLib && window.St && window.St.PageFlip) {
                return Promise.resolve();
            }
            if (vendorsPromise) {
                return vendorsPromise;
            }
            vendorsPromise = loadClassicScript(pdfJsUrl).then(function () {
                return loadClassicScript(pageFlipUrl);
            });
            return vendorsPromise;
        }

        /**
         * Carrega UMD via fetch+eval com define isolado (sem janela async sem AMD).
         */
        function loadClassicScript(url)
        {
            return fetch(url, { credentials: 'same-origin' }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load ' + url);
                }
                return response.text();
            }).then(function (code) {
                var amdBackup = window.define;
                try {
                    window.define = undefined;
                    // eslint-disable-next-line no-eval
                    (0, eval)(code);
                } finally {
                    if (amdBackup) {
                        window.define = amdBackup;
                    } else {
                        try {
                            delete window.define;
                        } catch (e) {
                            window.define = undefined;
                        }
                    }
                }
            });
        }

        function showFallback()
        {
            $status.addClass('is-hidden');
            $stage.addClass('is-hidden');
            $controls.addClass('is-hidden');
            $fallback.removeClass('is-hidden');
        }

        function bootstrapLazyFlipbook(pdf)
        {
            pdfDoc = pdf;
            numPages = pdf.numPages;

            return pdfDoc.getPage(1).then(function (firstPage) {
                var baseViewport = firstPage.getViewport({ scale: 1 });
                scale = Math.min(1200 / baseViewport.width, 1.25);
                var dimensions = {
                    width: Math.round(baseViewport.width * scale),
                    height: Math.round(baseViewport.height * scale)
                };

                bookEl.innerHTML = '';
                pageEls = [];

                for (var i = 1; i <= numPages; i++) {
                    var pageEl = document.createElement('div');
                    pageEl.className = 'awa-catalog-flipbook__page';
                    pageEl.setAttribute('data-page', String(i));
                    pageEl.setAttribute('role', 'group');
                    pageEl.setAttribute('aria-label', 'Página ' + i + ' de ' + numPages);
                    pageEl.style.width = dimensions.width + 'px';
                    pageEl.style.height = dimensions.height + 'px';
                    pageEl.style.background = '#f3f3f3';
                    bookEl.appendChild(pageEl);
                    pageEls.push(pageEl);
                }

                var warm = [];
                for (var p = 1; p <= Math.min(numPages, WINDOW_RADIUS + 1); p++) {
                    warm.push(ensurePage(p));
                }

                return Promise.all(warm).then(function () {
                    initPageFlip(dimensions, numPages);
                });
            });
        }

        function ensurePage(pageNum)
        {
            if (pageNum < 1 || pageNum > numPages) {
                return Promise.resolve();
            }
            if (rendered[pageNum]) {
                return Promise.resolve();
            }
            if (inflight[pageNum]) {
                return inflight[pageNum];
            }

            inflight[pageNum] = pdfDoc.getPage(pageNum).then(function (page) {
                var viewport = page.getViewport({ scale: scale });
                var canvas = document.createElement('canvas');
                var context = canvas.getContext('2d');

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.setAttribute('aria-hidden', 'true');

                return page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise.then(function () {
                    var pageEl = pageEls[pageNum - 1];
                    if (!pageEl) {
                        return;
                    }
                    pageEl.innerHTML = '';
                    pageEl.appendChild(canvas);
                    rendered[pageNum] = true;
                    delete inflight[pageNum];
                });
            }).catch(function () {
                delete inflight[pageNum];
            });

            return inflight[pageNum];
        }

        function warmAround(index)
        {
            var center = index + 1;
            var tasks = [];
            for (var p = center - WINDOW_RADIUS; p <= center + WINDOW_RADIUS + 1; p++) {
                tasks.push(ensurePage(p));
            }
            return Promise.all(tasks);
        }

        function pruneFarPages(index)
        {
            var center = index + 1;
            Object.keys(rendered).forEach(function (key) {
                var pageNum = parseInt(key, 10);
                if (Math.abs(pageNum - center) > WINDOW_RADIUS + 2) {
                    var pageEl = pageEls[pageNum - 1];
                    if (pageEl) {
                        pageEl.innerHTML = '';
                    }
                    delete rendered[pageNum];
                }
            });
        }

        function initPageFlip(dimensions, totalPages)
        {
            var isMobile = window.matchMedia('(max-width: 767px)').matches;
            var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            pageFlip = new St.PageFlip(bookEl, {
                width: dimensions.width,
                height: dimensions.height,
                size: 'stretch',
                minWidth: 280,
                maxWidth: 1200,
                minHeight: 400,
                maxHeight: 1600,
                showCover: true,
                mobileScrollSupport: false,
                usePortrait: isMobile,
                drawShadow: !reducedMotion,
                flippingTime: reducedMotion ? 0 : 700
            });

            pageFlip.loadFromHTML(bookEl.querySelectorAll('.awa-catalog-flipbook__page'));

            function updateCounter()
            {
                $counter.text((pageFlip.getCurrentPageIndex() + 1) + ' / ' + totalPages);
            }

            pageFlip.on('flip', function () {
                var idx = pageFlip.getCurrentPageIndex();
                updateCounter();
                warmAround(idx).then(function () {
                    pruneFarPages(idx);
                });
            });
            updateCounter();
            warmAround(0);

            function goToPage(targetIndex)
            {
                if (targetIndex < 0 || targetIndex >= totalPages) {
                    return;
                }

                var previousIndex = pageFlip.getCurrentPageIndex();
                if (targetIndex === previousIndex) {
                    return;
                }

                warmAround(targetIndex).then(function () {
                    pageFlip.turnToPage(targetIndex);

                    window.setTimeout(function () {
                        if (pageFlip.getCurrentPageIndex() === previousIndex) {
                            if (targetIndex > previousIndex) {
                                pageFlip.flipNext('top');
                            } else {
                                pageFlip.flipPrev('top');
                            }
                        }
                        updateCounter();
                        pruneFarPages(pageFlip.getCurrentPageIndex());
                    }, 350);
                });
            }

            $root.find('#awa-catalog-prev').on('click', function (event) {
                event.preventDefault();
                goToPage(pageFlip.getCurrentPageIndex() - 1);
            });

            $root.find('#awa-catalog-next').on('click', function (event) {
                event.preventDefault();
                goToPage(pageFlip.getCurrentPageIndex() + 1);
            });

            $root.on('keydown', function (event) {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    goToPage(pageFlip.getCurrentPageIndex() - 1);
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    goToPage(pageFlip.getCurrentPageIndex() + 1);
                }
            });

            if (!$root.attr('tabindex')) {
                $root.attr('tabindex', '0');
            }

            $status.addClass('is-hidden');
            $stage.removeClass('is-hidden');
            $controls.removeClass('is-hidden');
        }
    };
});
