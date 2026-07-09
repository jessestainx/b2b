define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        let trackSelector = config.trackSelector || '.rexis-carousel-track';
        let slideSelector = config.slideSelector || '.rexis-carousel-slide';
        let prevSelector = config.prevSelector || '.rexis-prev';
        let nextSelector = config.nextSelector || '.rexis-next';
        let mobileBreakpoint = parseInt(config.mobileBreakpoint, 10) || 480;
        let tabletBreakpoint = parseInt(config.tabletBreakpoint, 10) || 768;
        let desktopBreakpoint = parseInt(config.desktopBreakpoint, 10) || 992;

        var $track = $root.find(trackSelector).first();
        var $slides = $track.find(slideSelector);
        var $prev = $root.find(prevSelector).first();
        var $next = $root.find(nextSelector).first();
        var $viewport = $track.parent();
        let position = 0;
        let resizeTimer = null;
        let snapOffsets = [0];

        if (!$track.length || !$slides.length || $root.data('rexisCarouselInit')) {
            return;
        }

        $root.data('rexisCarouselInit', true);
        $root.attr('tabindex', $root.attr('tabindex') || '0');

        function getVisibleSlides()
        {
            let width = $root.outerWidth();

            if (width <= mobileBreakpoint) {
                return 1;
            }

            if (width <= tabletBreakpoint) {
                return 2;
            }

            if (width <= desktopBreakpoint) {
                return 3;
            }

            return 4;
        }

        function clampPosition(pos)
        {
            let max = Math.max(0, snapOffsets.length - 1);

            return Math.max(0, Math.min(max, pos));
        }

        function buildSnapOffsets()
        {
            let maxTranslate = Math.max(0, $track.get(0).scrollWidth - $viewport.get(0).clientWidth + 2); // +2 buffer
            let visibleSlides = getVisibleSlides();
            let rawOffsets = [];
            let maxStartIndex = Math.max(0, $slides.length - visibleSlides);

            $slides.each(function (index, slide) {
                if (index > maxStartIndex) {
                    return false;
                }

                rawOffsets.push(Math.min(slide.offsetLeft, maxTranslate));
            });

            if (!rawOffsets.length) {
                rawOffsets = [0];
            }

            snapOffsets = rawOffsets.filter(function (offset, index, offsets) {
                return index === 0 || offset !== offsets[index - 1];
            });
        }

        function updateNavState()
        {
            let max = Math.max(0, snapOffsets.length - 1);

            if ($prev.length) {
                $prev.prop('disabled', position <= 0);
                $prev.attr('aria-disabled', position <= 0 ? 'true' : 'false');
                $prev.toggleClass('is-disabled', position <= 0);
            }

            if ($next.length) {
                $next.prop('disabled', position >= max);
                $next.attr('aria-disabled', position >= max ? 'true' : 'false');
                $next.toggleClass('is-disabled', position >= max);
            }
        }

        function render()
        {
            let offset = snapOffsets[position] || 0;

            position = clampPosition(position);
            offset = snapOffsets[position] || 0;

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () {
                    $track.css('transform', 'translate3d(-' + offset + 'px, 0, 0)');
                });
            } else {
                $track.css('transform', 'translate3d(-' + offset + 'px, 0, 0)');
            }

            updateNavState();
        }

        function move(direction)
        {
            position = clampPosition(position + direction);
            render();
        }

        $prev.on('click', function () {
            move(-1);
        });

        $next.on('click', function () {
            move(1);
        });

        $root.on('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                move(-1);
                event.preventDefault();
            }

            if (event.key === 'ArrowRight') {
                move(1);
                event.preventDefault();
            }
        });

        $(window).on('resize.rexisCarousel', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                buildSnapOffsets();
                render();
            }, 160);
        });

        if ('IntersectionObserver' in window) {
            let observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        observer.disconnect();
                        buildSnapOffsets();
                        render();
                    }
                });
            }, {rootMargin: '300px 0px'});

            observer.observe($root.get(0));
        } else {
            buildSnapOffsets();
            render();
        }
    };
});
