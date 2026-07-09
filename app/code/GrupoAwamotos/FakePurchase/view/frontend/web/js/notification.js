define([], function () {
    'use strict';

    return function (options, element) {
        let popup = element;
        let config = (options && options.config) || {};
        let products = Array.isArray(options && options.products) ? options.products : [];
        let cities = Array.isArray(options && options.cities) ? options.cities : [];
        let names = Array.isArray(options && options.names) ? options.names : [];
        let closeButton;
        let buyerNameNode;
        let buyerCityNode;
        let productNameNode;
        let imageLinkNode;
        let imageNode;
        let timeNode;
        let notificationCount = 0;
        let isVisible = false;
        let displayTimerId = null;
        let nextShowTimerId = null;
        let hideAnimationTimerId = null;
        let reducedMotion = false;
        let hideAnimationMs;

        function toMs(value, fallback)
        {
            let parsed = Number(value);

            if (Number.isFinite(parsed) && parsed >= 0) {
                return parsed;
            }

            return fallback;
        }

        function clearTimers()
        {
            window.clearTimeout(displayTimerId);
            window.clearTimeout(nextShowTimerId);
            window.clearTimeout(hideAnimationTimerId);
            displayTimerId = null;
            nextShowTimerId = null;
            hideAnimationTimerId = null;
        }

        function getRandomItem(items)
        {
            return items[Math.floor(Math.random() * items.length)];
        }

        function getRandomTime()
        {
            return getRandomItem([
                'há poucos minutos',
                'há 2 minutos',
                'há 5 minutos',
                'agora mesmo'
            ]);
        }

        function scheduleNextShow(delayMs)
        {
            if (notificationCount >= toMs(config.maxNotifications, 10)) {
                return;
            }

            nextShowTimerId = window.setTimeout(showNotification, delayMs);
        }

        function hideNotification()
        {
            if (!isVisible) {
                return;
            }

            popup.classList.add('hiding');

            hideAnimationTimerId = window.setTimeout(function () {
                popup.hidden = true;
                popup.classList.remove('hiding');
                isVisible = false;
                scheduleNextShow(toMs(config.delayTime, 8000));
            }, hideAnimationMs);
        }

        function showNotification()
        {
            let product;
            let buyerName;
            let buyerCity;

            if (notificationCount >= toMs(config.maxNotifications, 10) || !products.length) {
                return;
            }

            product = getRandomItem(products);
            buyerName = getRandomItem(names);
            buyerCity = getRandomItem(cities);

            if (!product || !buyerName || !buyerCity) {
                return;
            }

            buyerNameNode.textContent = buyerName;
            buyerCityNode.textContent = buyerCity;
            productNameNode.textContent = product.name || '';
            productNameNode.href = product.url || '#';
            imageLinkNode.href = product.url || '#';
            imageNode.src = product.image || '';
            imageNode.alt = product.name || '';
            timeNode.textContent = getRandomTime();

            popup.classList.remove('hiding');
            popup.hidden = false;
            isVisible = true;
            notificationCount += 1;

            displayTimerId = window.setTimeout(hideNotification, toMs(config.displayTime, 5000));
        }

        if (!popup || !products.length || !cities.length || !names.length) {
            return;
        }

        closeButton = popup.querySelector('.fake-purchase-close');
        buyerNameNode = popup.querySelector('.buyer-name');
        buyerCityNode = popup.querySelector('.buyer-city');
        productNameNode = popup.querySelector('.product-name');
        imageLinkNode = popup.querySelector('.fake-purchase-image .product-link');
        imageNode = popup.querySelector('.fake-purchase-image img');
        timeNode = popup.querySelector('.fake-purchase-time');

        if (
            !closeButton ||
            !buyerNameNode ||
            !buyerCityNode ||
            !productNameNode ||
            !imageLinkNode ||
            !imageNode ||
            !timeNode
        ) {
            return;
        }

        if (window.matchMedia) {
            reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        hideAnimationMs = reducedMotion ? 0 : 300;

        closeButton.addEventListener('click', function () {
            clearTimers();
            hideNotification();
        });

        scheduleNextShow(toMs(config.delayTime, 8000));
    };
});
