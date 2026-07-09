define([
    'jquery',
    'GrupoAwamotos_Theme/js/vendor/pdf.min',
    'GrupoAwamotos_Theme/js/vendor/page-flip.browser'
], function ($, pdfjsLib, St) {
    'use strict';

    return function (config, element) {
        var $status = $('#awa-catalog-flipbook-status');
        var $stage = $('#awa-catalog-flipbook-stage');
        var $controls = $('#awa-catalog-flipbook-controls');
        var $fallback = $('#awa-catalog-flipbook-fallback');
        var $book = $('#awa-catalog-flipbook-book');
        var $counter = $('#awa-catalog-counter');
        var pageFlip = null;

        if (!config.pdfUrl || !pdfjsLib) {
            showFallback();
            return;
        }

        pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerUrl;

        pdfjsLib.getDocument(config.pdfUrl).promise
            .then(renderFlipbook)
            .catch(showFallback);

        function showFallback()
        {
            $status.addClass('is-hidden');
            $stage.addClass('is-hidden');
            $controls.addClass('is-hidden');
            $fallback.removeClass('is-hidden');
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
                    $book.append(pageEl);
                });
            });
        }

        function initPageFlip(dimensions, numPages)
        {
            if (!St || !St.PageFlip) {
                showFallback();
                return;
            }

            var isMobile = window.matchMedia('(max-width: 767px)').matches;
            var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            pageFlip = new St.PageFlip($book.get(0), {
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

            pageFlip.loadFromHTML($book.find('.awa-catalog-flipbook__page').toArray());

            function updateCounter()
            {
                $counter.text((pageFlip.getCurrentPageIndex() + 1) + ' / ' + numPages);
            }

            pageFlip.on('flip', updateCounter);
            updateCounter();

            $('#awa-catalog-prev').on('click', function () {
                pageFlip.flipPrev();
            });

            $('#awa-catalog-next').on('click', function () {
                pageFlip.flipNext();
            });

            $status.addClass('is-hidden');
            $stage.removeClass('is-hidden');
            $controls.removeClass('is-hidden');
        }
    };
});
