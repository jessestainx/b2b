/**
 * @deprecated Use awa-home-hero-tabs-ui.js — mantido para URLs em cache de merged bundles.
 */
(function (w) {
    'use strict';

    if (w.__awaHomeHeroTabsUiInit || w.__awaRound2HomeOwlTabsUiInit) {
        return;
    }

    var current = w.document.currentScript;
    var src = current && current.src
        ? current.src.replace('awa-home-owl-tabs-ui', 'awa-home-hero-tabs-ui')
        : '';

    if (!src) {
        return;
    }

    var script = w.document.createElement('script');
    script.src = src;
    script.defer = true;
    (w.document.head || w.document.documentElement).appendChild(script);
}(window));
