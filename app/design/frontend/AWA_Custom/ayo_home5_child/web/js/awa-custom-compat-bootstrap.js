define(['jquery'], function ($) {
    'use strict';

    const SEARCH_FORM_SELECTOR = 'form.form.minisearch, #search_mini_form';
    const OBSERVER_KEY = '__awaSearchCompatBootObserver';
    const SCHEDULE_KEY = '__awaSearchCompatBootScheduled';
    const INIT_KEY = '__awaSearchCompatBootInit';
    const HEADER_SCOPE_SELECTOR = '.awa-site-header, #header.header-container, .page-header, header.page-header';

    function flagEnabled(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function onReady(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }
        $(callback);
    }

    function runOnce(flag, callback) {
        if (window[flag]) {
            return;
        }
        window[flag] = true;
        callback();
    }

    function isSearchFormNode(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }
        const $node = $(node);
        return $node.is(SEARCH_FORM_SELECTOR) || $node.find(SEARCH_FORM_SELECTOR).length > 0;
    }

    function mutationsTouchSearch(mutations) {
        if (!mutations || !mutations.length) {
            return false;
        }

        for (let i = 0; i < mutations.length; i += 1) {
            const mutation = mutations[i];
            if (!mutation) {
                continue;
            }

            const added = mutation.addedNodes || [];
            for (let j = 0; j < added.length; j += 1) {
                if (isSearchFormNode(added[j])) {
                    return true;
                }
            }

            const removed = mutation.removedNodes || [];
            for (let k = 0; k < removed.length; k += 1) {
                if (isSearchFormNode(removed[k])) {
                    return true;
                }
            }
        }

        return false;
    }

    function scheduleSearchBoot() {
        if (window[SCHEDULE_KEY]) {
            return;
        }
        window[SCHEDULE_KEY] = true;

        const run = function () {
            window[SCHEDULE_KEY] = false;
            require(['js/awa-search-autocomplete-compat'], function (initCompat) {
                $(SEARCH_FORM_SELECTOR).each(function () {
                    initCompat({}, this);
                });
            });
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(run);
        } else {
            window.setTimeout(run, 0);
        }
    }

    function bootSearchCompat() {
        runOnce(INIT_KEY, function () {
            require(['js/awa-search-autocomplete-compat'], function () {
                onReady(function () {
                    scheduleSearchBoot();

                    $(document).on('contentUpdated.awaSearchCompatBootstrap', function (event) {
                        if (event && event.target && !isSearchFormNode(event.target)) {
                            return;
                        }
                        scheduleSearchBoot();
                    });

                    const headerScope = document.querySelector(HEADER_SCOPE_SELECTOR);
                    if (!window.MutationObserver || !headerScope || window[OBSERVER_KEY]) {
                        return;
                    }

                    const observer = new window.MutationObserver(function (records) {
                        if (!mutationsTouchSearch(records)) {
                            return;
                        }
                        scheduleSearchBoot();
                        const $forms = $(SEARCH_FORM_SELECTOR);
                        if ($forms.length && $forms.filter('[data-awa-search-compat-init="1"]').length >= $forms.length) {
                            observer.disconnect();
                            window[OBSERVER_KEY] = null;
                        }
                    });

                    window[OBSERVER_KEY] = observer;
                    observer.observe(headerScope, { childList: true, subtree: true });
                });
            });
        });
    }

    function bootB2bCheckoutCompat() {
        runOnce('__awaB2bCheckoutCompatBootInit', function () {
            require(['js/awa-custom-b2b-cart-checkout-compat'], function (init) {
                onReady(function () {
                    init();
                });
            });
        });
    }

    function bootHomeCategoryCompat() {
        runOnce('__awaHomeCategoryCompatBootInit', function () {
            const run = function () {
                require(['js/awa-custom-home-category-compat'], function (init) {
                    onReady(function () {
                        init();
                    });
                });
            };

            if (document.body && (document.body.classList.contains('cms-index-index') || document.body.classList.contains('cms-home'))) {
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(run, { timeout: 4500 });
                } else {
                    window.setTimeout(run, 3200);
                }
                return;
            }
            run();
        });
    }

    return function (config) {
        const cfg = config || {};

        if (flagEnabled(cfg.load_search_compat_js)) {
            bootSearchCompat();
        }
        if (flagEnabled(cfg.load_b2b_checkout_compat_js)) {
            bootB2bCheckoutCompat();
        }
        if (flagEnabled(cfg.load_home_category_compat_js)) {
            bootHomeCategoryCompat();
        }
    };
});
