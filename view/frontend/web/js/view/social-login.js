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
            template: "O2TI_SocialLogin/social-login"
        },

        isVisible: ko.observable(window.checkoutConfig.socialLogin.enabled),

        initialize() {
            var self = this;
            self._super();
            return this;
        },

        isEnabled(provider) {
            if(provider === "facebook"){
                return window.checkoutConfig.socialLogin.providers.facebook;
            }
            if(provider === "google"){
                return window.checkoutConfig.socialLogin.providers.google;
            }
            if(provider === "WindowsLive"){
                return window.checkoutConfig.socialLogin.providers.WindowsLive;
            }
            if(provider === "WindowsLive"){
                return window.checkoutConfig.socialLogin.providers.WindowsLive;
            }
            if (provider === "verify_code") {
                return window.socialLogin.providers.verify_code;
            }
        },

        getRedirectUrl(provider) {
            return window.checkoutConfig.socialLogin.redirectUrl + "provider/" + provider;
        },
        
        openVerifyCodeForm() {
            var verifyCodeComponent = registry.get(this.name + '.form-verify-component');
            
            if (verifyCodeComponent) {
                verifyCodeComponent.showCodeRequestForm();
            }
        }
    });
});
