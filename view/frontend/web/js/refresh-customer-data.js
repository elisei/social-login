define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Customer/js/section-config',
    'jquery/jquery-storageapi'
], function ($, customerData, sectionConfig) {
    'use strict';
    
    return function (config) {
        $(() => {
            var invalidationSections = $.cookieStorage.get('social-login-refresh-sessions');

            if (invalidationSections === true) {
                customerData.reload(sectionConfig.getSectionNames()).done(function () {
                    $.cookieStorage.set('social-login-refresh-sessions', {});
                });
            }
        });
    };
});