define([], function () {
    'use strict';

    function ensureTrailingSlash(url)
    {
        if (typeof url !== 'string' || url === '') {
            return '/';
        }

        return url.charAt(url.length - 1) === '/' ? url : (url + '/');
    }

    return function (config) {
        var existingConfig = window.authenticationPopup || {};
        var nextConfig = config || {};
        var popupConfig = Object.assign({}, existingConfig, nextConfig);
        var baseUrl = ensureTrailingSlash(
            popupConfig.awaBaseUrl || window.BASE_URL || '/'
        );

        popupConfig.awaBaseUrl = baseUrl;
        popupConfig.awaB2bForgotPasswordUrl = popupConfig.awaB2bForgotPasswordUrl
            || (baseUrl + 'b2b/account/forgotpassword/');
        popupConfig.awaB2bRegisterUrl = popupConfig.awaB2bRegisterUrl
            || (baseUrl + 'b2b/register/');

        window.authenticationPopup = popupConfig;
    };
});
