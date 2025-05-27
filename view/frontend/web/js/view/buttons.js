/**
 * Copyright © 2019 O2TI. All rights reserved.
 * See LICENSE.txt for license details.
 */
define([
    "ko", 
    "uiComponent",
    "uiRegistry"
], function (ko, Component, registry) {
    "use strict";

    return Component.extend({
        isVisible: ko.observable(true),
        
        initialize: function() {
            this._super();
            
            if (this.data && this.data.socialLogin) {
                this.isVisible(this.data.socialLogin.enabled);
            }
            
            return this;
        },
        
        isEnabled: function(provider) {
            if (!this.data || !this.data.socialLogin || !this.data.socialLogin.providers) {
                return false;
            }
            
            if (provider === "facebook") {
                return this.data.socialLogin.providers.facebook;
            }
            if (provider === "google") {
                return this.data.socialLogin.providers.google;
            }
            if (provider === "WindowsLive") {
                return this.data.socialLogin.providers.WindowsLive;
            }
            if (provider === "verify_code") {
                return this.data.socialLogin.providers.verify_code;
            }
            return false;
        },
        
        getRedirectUrl: function(provider) {
            if (!this.data || !this.data.socialLogin) {
                return '/sociallogin/endpoint/index/provider/' + provider;
            }
            return this.data.socialLogin.redirectUrl + "provider/" + provider;
        },
        
        openVerifyCodeForm: function() {
            var verifyCodeComponent = registry.get(this.name + '.form-verify-component');
            
            if (verifyCodeComponent) {
                verifyCodeComponent.showCodeRequestForm();
            }
        }
    });
});
