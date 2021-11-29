define([
    "mage/utils/wrapper",
    "jquery",
    "Magento_Checkout/js/model/quote"
], function (wrapper, $, quote) {
    "use strict";

    let mixin = {
        handleHash: function (originalFn) {
            var hashString = window.location.hash.replace("#", "");
            
            if (hashString.indexOf("_=_") > -1) {
            	window.location.hash = "shipping";
            	if (quote.isVirtual()) {
            		window.location.hash = "payment";
            	}
            }

            return originalFn();
        }
    };

    return function (target) {
        return wrapper.extend(target, mixin);
    };
});
