/**
 * awa-search-clear-init — botão limpar busca (síncrono, sem AMD).
 * Necessário porque awa-ux-enhancements só carrega após interação (home bootstrap defer).
 */
(function () {
    'use strict';

    function shouldSkipSearchClear() {
        // AWA 2026-07-22: campo de busca exibe somente a lupa (sem botão X).
        return true;
    }

    function setSearchClearVisible(clearBtn, visible) {
        var ariaHidden = visible ? 'false' : 'true';

        if (clearBtn.hidden === visible) {
            clearBtn.hidden = !visible;
        }
        clearBtn.style.removeProperty('display');
        if (clearBtn.getAttribute('aria-hidden') !== ariaHidden) {
            clearBtn.setAttribute('aria-hidden', ariaHidden);
        }
    }

    function initSearchClear() {
        if (shouldSkipSearchClear()) {
            return;
        }

        try {
            var searchInput = document.querySelector(
                '#search, .header-search input[type="text"], .block-search input.input-text'
            );
            if (!searchInput || document.getElementById('awa-search-clear')) {
                return;
            }

            var clearBtn = document.createElement('button');
            clearBtn.id = 'awa-search-clear';
            clearBtn.type = 'button';
            clearBtn.className = 'awa-search-clear-btn';
            clearBtn.setAttribute('aria-label', 'Limpar busca');
            clearBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

            var control = searchInput.closest(
                '.control[data-awa-search-control], .field.search .control, .control'
            ) || searchInput.parentElement;

            if (control) {
                if (window.getComputedStyle(control).position === 'static') {
                    control.style.position = 'relative';
                }
                control.appendChild(clearBtn);
            }

            setSearchClearVisible(clearBtn, false);
            searchInput.classList.add('awa-search-input--clearable');

            searchInput.addEventListener('input', function () {
                setSearchClearVisible(clearBtn, !!this.value);
            });
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                setSearchClearVisible(clearBtn, false);
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                searchInput.focus();
            });
        } catch (e) {
            /* ignore */
        }
    }

    function boot() {
        initSearchClear();
    }

    function isHome() {
        var body = document.body;
        return !!(
            body &&
            (body.classList.contains('cms-index-index') ||
                body.classList.contains('cms-home') ||
                body.classList.contains('cms-homepage_ayo_home5'))
        );
    }

    if (shouldSkipSearchClear()) {
        return;
    }

    if (isHome()) {
        var intentEvents = ['pointerdown', 'keydown', 'touchstart'];
        intentEvents.forEach(function (eventName) {
            window.addEventListener(eventName, boot, {
                capture: true,
                passive: eventName !== 'keydown',
                once: true
            });
        });
        return;
    }

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    }

    window.setTimeout(boot, 500);
    window.setTimeout(boot, 2000);
})();
