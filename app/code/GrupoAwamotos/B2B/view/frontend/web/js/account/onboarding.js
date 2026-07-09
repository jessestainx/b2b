define([
    'jquery',
    'mage/translate',
    'mage/cookies'
], function ($, $t) {
    'use strict';

    const TOUR_SEEN_KEY = 'awa_b2b_tour_seen';

    return function (config) {
        let tour = null;
        let tourBootstrapping = false;
        let shepherdCssLoaded = false;
        const shepherdCssUrl = config && config.shepherdCssUrl ? config.shepherdCssUrl : '';

        const loadShepherdCss = function (onReady) {
            if (shepherdCssLoaded || !shepherdCssUrl) {
                onReady();
                return;
            }

            if (document.getElementById('awa-shepherd-css')) {
                shepherdCssLoaded = true;
                onReady();
                return;
            }

            const link = document.createElement('link');
            link.id = 'awa-shepherd-css';
            link.rel = 'stylesheet';
            link.href = shepherdCssUrl;
            link.media = 'all';
            link.onload = function () {
                shepherdCssLoaded = true;
                onReady();
            };
            link.onerror = function () {
                shepherdCssLoaded = true;
                onReady();
            };
            document.head.appendChild(link);
        };

        const setTourExpanded = function (expanded) {
            $('.trigger-b2b-tour').attr('aria-expanded', expanded ? 'true' : 'false');
        };

        const markTourSeen = function () {
            const date = new Date();
            date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
            $.mage.cookies.set(TOUR_SEEN_KEY, '1', { expires: date });

            try {
                window.localStorage.setItem(TOUR_SEEN_KEY, '1');
            } catch (e) {
                // ignore storage failures (private mode, etc.)
            }
        };

        const resolveAttachTarget = function (selector, fallbackSelector) {
            if ($(selector).length) {
                return selector;
            }

            if (fallbackSelector && $(fallbackSelector).length) {
                return fallbackSelector;
            }

            return null;
        };

        const buildTour = function (Shepherd) {
            const instance = new Shepherd.Tour({
                useModalOverlay: true,
                defaultStepOptions: {
                    classes: 'shepherd-theme-custom',
                    scrollTo: { behavior: 'smooth', block: 'nearest' },
                    cancelIcon: {
                        enabled: true
                    }
                }
            });

            const addAnchoredStep = function (stepConfig) {
                const selector = resolveAttachTarget(stepConfig.attachTo, stepConfig.fallbackAttachTo);

                if (!selector) {
                    return;
                }

                instance.addStep({
                    id: stepConfig.id,
                    text: stepConfig.text,
                    attachTo: {
                        element: selector,
                        on: stepConfig.on || 'bottom'
                    },
                    buttons: stepConfig.buttons
                });
            };

            addAnchoredStep({
                id: 'welcome',
                text: $t('Bem-vindo ao Portal B2B Grupo AWA! Este é o seu novo centro de comando para compras no atacado.'),
                attachTo: '.b2b-dashboard-welcome',
                fallbackAttachTo: '.b2b-dashboard-header',
                on: 'bottom',
                buttons: [
                    {
                        text: $t('Próximo'),
                        action: instance.next
                }
                ]
            });

            if ($('.b2b-analytics-section').length) {
                addAnchoredStep({
                    id: 'analytics',
                    text: $t('Acompanhe seu desempenho de compras com gráficos em tempo real. Identifique tendências e planeje seu estoque.'),
                    attachTo: '.b2b-analytics-section',
                    on: 'top',
                    buttons: [
                        {
                            text: $t('Anterior'),
                            action: instance.back,
                            classes: 'shepherd-button-secondary'
                    },
                        {
                            text: $t('Próximo'),
                            action: instance.next
                    }
                    ]
                });
            }

            if ($('.b2b-quickorder-shortcut').length) {
                addAnchoredStep({
                    id: 'quickorder',
                    text: $t('Ganhe tempo com o Pedido Rápido. Adicione múltiplos SKUs de uma vez ou importe sua planilha CSV.'),
                    attachTo: '.b2b-quickorder-shortcut',
                    on: 'bottom',
                    buttons: [
                        {
                            text: $t('Anterior'),
                            action: instance.back,
                            classes: 'shepherd-button-secondary'
                    },
                        {
                            text: $t('Próximo'),
                            action: instance.next
                    }
                    ]
                });
            }

            if ($('.b2b-subscriptions-shortcut').length) {
                addAnchoredStep({
                    id: 'subscriptions',
                    text: $t('Configure Assinaturas Recorrentes para seus itens de maior giro e nunca fique sem estoque.'),
                    attachTo: '.b2b-subscriptions-shortcut',
                    on: 'top',
                    buttons: [
                        {
                            text: $t('Anterior'),
                            action: instance.back,
                            classes: 'shepherd-button-secondary'
                    },
                        {
                            text: $t('Próximo'),
                            action: instance.next
                    }
                    ]
                });
            }

            instance.addStep({
                id: 'finish',
                text: $t('Pronto! Você está pronto para decolar. Caso precise de ajuda, entre em contato com seu atendente comercial.'),
                buttons: [
                    {
                        text: $t('Finalizar'),
                        action: instance.complete
                }
                ]
            });

            instance.on('complete', function () {
                setTourExpanded(false);
                markTourSeen();
            });
            instance.on('cancel', function () {
                setTourExpanded(false);
                markTourSeen();
            });

            return instance;
        };

        const startTour = function () {
            if (!tour || tour.isActive()) {
                return;
            }

            setTourExpanded(true);
            tour.start();
        };

        const ensureTour = function (onReady) {
            if (tour) {
                onReady();
                return;
            }

            if (tourBootstrapping) {
                return;
            }

            tourBootstrapping = true;

            loadShepherdCss(function () {
                require(['shepherd'], function (Shepherd) {
                    tour = buildTour(Shepherd);
                    tourBootstrapping = false;
                    onReady();
                });
            });
        };

        $(document).on('click', '.trigger-b2b-tour', function (event) {
            event.preventDefault();
            ensureTour(startTour);
        });
    };
});
