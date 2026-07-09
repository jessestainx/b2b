/**
 * AWA B2B Header — legacy shim (superseded by inline awa-header-account-prompt script).
 * Kept for RequireJS map compatibility; no-op when inline boot already ran.
 */
define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    if (window.__awaHeaderAccountPromptBooted) {
        return;
    }

    var promptSelector = '.awa-header-account-prompt';

    function isLoggedIn(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }

        return !!(
            data.firstname
            || data.fullname
            || data.email
            || data.id
            || data.entity_id
            || (data.websiteId !== undefined && data.websiteId !== null && data.websiteId !== '')
        );
    }

    function syncAuthPrompt(data) {
        var el = document.querySelector(promptSelector);
        if (!el) {
            return;
        }

        var loggedIn = isLoggedIn(data);
        el.setAttribute('data-awa-auth-state', loggedIn ? 'customer' : 'guest');
        el.removeAttribute('data-awa-auth-pending');

        var guest = el.querySelector('.awa-header-account-prompt__guest');
        var cust = el.querySelector('.awa-header-account-prompt__customer');

        if (loggedIn) {
            if (guest) {
                guest.style.setProperty('display', 'none', 'important');
            }
            if (cust) {
                cust.style.removeProperty('display');
            }
        } else {
            if (guest) {
                guest.style.removeProperty('display');
            }
            if (cust) {
                cust.style.setProperty('display', 'none', 'important');
            }
        }
    }

    var customer = customerData.get('customer');
    syncAuthPrompt(customer());
    customer.subscribe(syncAuthPrompt);
});
