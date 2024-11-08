define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';

    return function (target) {
        return target.extend({
            defaults: {
                template: 'O2TI_SocialLogin/authentication-popup'
            }
        });
    };
});