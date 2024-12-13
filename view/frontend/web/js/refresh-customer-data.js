define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'jquery/jquery-storageapi'
], function ($, customerData) {
    'use strict';
    
    return function (config) {
        $(() => {
            var invalidationSections = $.cookieStorage.get('social-login-refresh-sessions');

            if (invalidationSections) {
                customerData.invalidate('customer');

                customerData.reload(invalidationSections, true).done(function () {
                    $.cookieStorage.set('social-login-refresh-sessions', null);
                });
            }
        });
    };
});