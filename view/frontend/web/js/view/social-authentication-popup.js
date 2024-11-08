/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */
define([
    'jquery',
    'ko',
    'Magento_Ui/js/form/form'
], function ($, ko, Component) {
    "use strict";

    return Component.extend({
        isVisible: ko.observable(true),
        isEnabled: ko.observable(true),
        defaults: {
            template: "O2TI_SocialLogin/social-login-authentication-popup",
        },
        initialize() {
            var self = this;
            self._super();
            isVisible: true;
        },
        isEnabled(provider) {
            if (!window.hasOwnProperty('socialLogin')){
                return false;
            }

            if (provider === "facebook") {
                return window.socialLogin.providers.facebook;
            }
            if (provider === "google") {
                return window.socialLogin.providers.google;
            }
            if (provider === "WindowsLive") {
                return window.socialLogin.providers.WindowsLive;
            }
        },
        getRedirectUrl(provider) {
            return window.socialLogin.redirectUrl + "provider/" + provider;
        },
    });
});
