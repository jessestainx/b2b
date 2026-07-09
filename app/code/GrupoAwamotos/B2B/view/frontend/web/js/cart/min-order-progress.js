define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    function formatCurrency(amount)
    {
        var value = Math.max(0, parseFloat(amount) || 0);

        return 'R$ ' + value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    return function (config, element) {
        var $root = $(element);
        var minAmount = parseFloat(config.minAmount) || 0;

        if (!config.enabled || minAmount <= 0) {
            $root.attr('hidden', 'hidden');
            return;
        }

        function render(subtotal)
        {
            var remaining = Math.max(0, minAmount - subtotal);
            var percent = minAmount > 0 ? Math.min(100, Math.round((subtotal / minAmount) * 100)) : 100;
            var message = 'Faltam ' + formatCurrency(remaining) + ' para atingir o pedido mínimo de ' + formatCurrency(minAmount) + '.';

            if (subtotal <= 0 || remaining <= 0.009) {
                $root.attr('hidden', 'hidden');
                $root.attr('aria-hidden', 'true');
                return;
            }

            $root.removeAttr('hidden');
            $root.removeAttr('aria-hidden');
            $root.toggleClass('awa-b2b-min-order-progress--near', percent >= 80);
            $root.find('[data-role="percent"]').text(percent + '%');
            $root.find('[data-role="fill"]').css('width', percent + '%');
            $root.find('[data-role="message"]').text(message);
        }

        function syncFromCart(cart)
        {
            var subtotal = 0;

            if (cart && cart.subtotalAmount !== undefined && cart.subtotalAmount !== null) {
                subtotal = parseFloat(cart.subtotalAmount) || 0;
            }

            render(subtotal);
        }

        var cartData = customerData.get('cart');

        cartData.subscribe(syncFromCart);
        syncFromCart(cartData());
    };
});
