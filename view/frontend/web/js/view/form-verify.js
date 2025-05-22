/**
 * Copyright © O2TI. All rights reserved.
 */
define([
    'ko',
    'uiComponent',
    'jquery',
    'mage/translate',
    'mage/validation'
], function (ko, Component, $, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'O2TI_SocialLogin/verification-code',
            visible: true
        },

        showingCodeRequestForm: ko.observable(false),
        showingCodeVerificationForm: ko.observable(false),
        email: ko.observable(''),
        verificationCode: ko.observable(''),
        customerId: ko.observable(''),
        message: ko.observable(''),
        isSuccess: ko.observable(false),
        isLoading: ko.observable(false),
        
        /**
         * Initialize component
         * 
         * @returns {Object}
         */
        initialize() {
            this._super();

            if (this.data && this.data.hasOwnProperty('socialLogin')) {
                this.referer = this.data.socialLogin.referer;
            }

            if (window.hasOwnProperty('socialLogin')){
                this.referer = window.checkoutConfig?.socialLogin?.referer || 'account';
            }

            return this;
        },
        
        /**
         * Show code request form
         */
        showCodeRequestForm() {
            this.showingCodeRequestForm(true);
            this.showingCodeVerificationForm(false);
            this.message('');
            
            setTimeout(() => {
                $('#code-request-form').validation();
            }, 0);
        },
        
        /**
         * Hide code request form
         */
        hideCodeRequestForm() {
            this.showingCodeRequestForm(false);
            this.email('');
            this.message('');
        },
        
        /**
         * Show code verification form
         * 
         * @param {String} customerId
         */
        showVerificationForm(customerId) {
            this.showingCodeRequestForm(false);
            this.showingCodeVerificationForm(true);
            this.customerId(customerId);
            this.verificationCode('');
            
            setTimeout(() => {
                $('#code-verification-form').validation();
            }, 0);
        },
        
        /**
         * Hide code verification form
         */
        hideVerificationForm() {
            this.showingCodeVerificationForm(false);
            this.customerId('');
            this.verificationCode('');
        },
        
        /**
         * Submit code request form
         * 
         * @returns {Boolean}
         */
        submitCodeRequestForm() {
            var self = this;
            var form = $('#code-request-form');

            if (!form.validation('isValid')) {
                return false;
            }

            this.isLoading(true);
            this.message('');

            $.ajax({
                url: '/sociallogin/ajax/requestverify',
                type: 'POST',
                dataType: 'json',
                data: {
                    email: this.email(),
                    form_key: $.mage.cookies.get('form_key'),
                    referer: this.referer
                },
                success: function(response) {
                    self.isLoading(false);
                    if (response.success) {
                        console.log(response.success);
                        self.message(response.message);
                        self.isSuccess(true);
                        
                        // Get customer ID and show verification form
                        $.ajax({
                            url: '/sociallogin/ajax/customerid',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                email: self.email(),
                                form_key: $.mage.cookies.get('form_key')
                            },
                            success: function(customerResponse) {
                                if (customerResponse.success) {
                                    self.showVerificationForm(customerResponse.customer_id);
                                }
                            }
                        });
                    } else {
                        self.message(response.message);
                        self.isSuccess(false);
                    }
                },
                error: function() {
                    self.isLoading(false);
                    self.message($t('Error sending verification code. Please try again.'));
                    self.isSuccess(false);
                }
            });

            return false;
        },
        
        /**
         * Submit verification code form
         * 
         * @returns {Boolean}
         */
        submitVerificationForm() {
            var self = this;
            var form = $('#code-verification-form');

            if (!form.validation('isValid')) {
                return false;
            }

            this.isLoading(true);
            this.message('');

            $.ajax({
                url: '/sociallogin/ajax/verify',
                type: 'POST',
                dataType: 'json',
                data: {
                    customer_id: this.customerId(),
                    code: this.verificationCode(),
                    form_key: $.mage.cookies.get('form_key')
                },
                success: function(response) {
                    self.isLoading(false);
                    if (response.success) {
                        self.message(response.message);
                        self.isSuccess(true);
                        
                        // Reload the page after successful login
                        setTimeout(function() {
                            window.location.href = response.redirect || window.location.href;
                        }, 2000);
                    } else {
                        self.message(response.message);
                        self.isSuccess(false);
                    }
                },
                error: function() {
                    self.isLoading(false);
                    self.message($t('Error verifying code. Please try again.'));
                    self.isSuccess(false);
                }
            });

            return false;
        }
    });
});
