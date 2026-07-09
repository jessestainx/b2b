/**
 * AWA Catálogo flipbook — carrega PDF.js + page-flip e inicializa (compatível com RequireJS).
 */
(function () {
    'use strict';

    var root = document.getElementById('awa-catalog-flipbook');
    if (!root) {
        return;
    }

    var pdfUrl = root.getAttribute('data-pdf-url') || '';
    var workerUrl = root.getAttribute('data-worker-url') || '';
    var pdfJsUrl = root.getAttribute('data-pdf-js-url') || '';
    var pageFlipUrl = root.getAttribute('data-page-flip-url') || '';

    var statusEl = document.getElementById('awa-catalog-flipbook-status');
    var stageEl = document.getElementById('awa-catalog-flipbook-stage');
    var controlsEl = document.getElementById('awa-catalog-flipbook-controls');
    var fallbackEl = document.getElementById('awa-catalog-flipbook-fallback');
    var bookEl = document.getElementById('awa-catalog-flipbook-book');
    var counterEl = document.getElementById('awa-catalog-counter');
    var pageFlip = null;

    if (!pdfUrl || !pdfJsUrl || !pageFlipUrl) {
        showFallback();
        return;
    }

    loadScript(pdfJsUrl)
        .then(function () {
            return loadScript(pageFlipUrl);
        })
        .then(startFlipbook)
        .catch(showFallback);

    function loadScript(url)
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
                (0, eval)(code);
            } finally {
                if (amdBackup) {
                    window.define = amdBackup;
                } else {
                    window.define = undefined;
                }
            }
        });
    }

    function showFallback()
    {
        if (statusEl) {
            statusEl.classList.add('is-hidden');
        }
        if (stageEl) {
            stageEl.classList.add('is-hidden');
        }
        if (controlsEl) {
            controlsEl.classList.add('is-hidden');
        }
        if (fallbackEl) {
            fallbackEl.classList.remove('is-hidden');
        }
    }

    function startFlipbook()
    {
        var pdfjsLib = window.pdfjsLib;
        var PageFlip = window.St && window.St.PageFlip;

        if (!pdfjsLib || !PageFlip) {
            showFallback();
            return;
        }

        pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
        pdfjsLib.getDocument(pdfUrl).promise.then(renderFlipbook).catch(showFallback);
    }

    function renderFlipbook(pdf)
    {
        return pdf.getPage(1).then(function (firstPage) {
            var baseViewport = firstPage.getViewport({ scale: 1 });
            var scale = Math.min(1200 / baseViewport.width, 1.25);
            var dimensions = {
                width: Math.round(baseViewport.width * scale),
                height: Math.round(baseViewport.height * scale)
            };
            var numPages = pdf.numPages;
            var chain = Promise.resolve();

            for (var pageNum = 1; pageNum <= numPages; pageNum++) {
                chain = chain.then(renderPage.bind(null, pdf, pageNum, scale));
            }

            return chain.then(function () {
                initPageFlip(dimensions, numPages);
            });
        }).catch(showFallback);
    }

    function renderPage(pdf, pageNum, scale)
    {
        return pdf.getPage(pageNum).then(function (page) {
            var viewport = page.getViewport({ scale: scale });
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d');

            canvas.width = viewport.width;
            canvas.height = viewport.height;

            return page.render({
                canvasContext: context,
                viewport: viewport
            }).promise.then(function () {
                var pageEl = document.createElement('div');
                pageEl.className = 'awa-catalog-flipbook__page';
                pageEl.appendChild(canvas);
                bookEl.appendChild(pageEl);
            });
        });
    }

    function initPageFlip(dimensions, numPages)
    {
        var PageFlip = window.St && window.St.PageFlip;
        var isMobile = window.matchMedia('(max-width: 767px)').matches;
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        pageFlip = new PageFlip(bookEl, {
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
            counterEl.textContent = (pageFlip.getCurrentPageIndex() + 1) + ' / ' + numPages;
        }

        pageFlip.on('flip', updateCounter);
        updateCounter();

        function goToPage(targetIndex)
        {
            if (targetIndex < 0 || targetIndex >= numPages) {
                return;
            }

            var previousIndex = pageFlip.getCurrentPageIndex();

            if (targetIndex === previousIndex) {
                return;
            }

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
            }, 350);
        }

        document.getElementById('awa-catalog-prev').addEventListener('click', function (event) {
            event.preventDefault();
            goToPage(pageFlip.getCurrentPageIndex() - 1);
        });

        document.getElementById('awa-catalog-next').addEventListener('click', function (event) {
            event.preventDefault();
            goToPage(pageFlip.getCurrentPageIndex() + 1);
        });

        statusEl.classList.add('is-hidden');
        stageEl.classList.remove('is-hidden');
        controlsEl.classList.remove('is-hidden');
    }
})();
