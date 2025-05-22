/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */
define([
    'jquery',
    'ko',
    'Magento_Ui/js/form/form',
    'uiRegistry'
], function ($, ko, Component, registry) {
    "use strict";

    return Component.extend({
        defaults: {
            template: "O2TI_SocialLogin/social-login-authentication-popup",
        },

        isVisible: ko.observable(true),

        initialize() {
            var self = this;
            self._super();
            return this;
        },

        isVisible() {
            if (!window.hasOwnProperty('socialLogin')){
                return false;
            }
            return window.socialLogin.enabled;
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
            if (provider === "verify_code") {
                return window.socialLogin.providers.verify_code;
            }
            return false;
        },

        getRedirectUrl(provider) {
            return window.socialLogin.redirectUrl + "provider/" + provider;
        },

        openVerifyCodeForm() {
            var verifyCodeComponent = registry.get(this.name + '.form-verify-component');
            
            if (verifyCodeComponent) {
                verifyCodeComponent.showCodeRequestForm();
            }
        }
    });
});
