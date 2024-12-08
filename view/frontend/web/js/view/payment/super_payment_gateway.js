/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'super_payment_gateway',
                component: 'Superpayments_SuperPayment/js/view/payment/method-renderer/super_payment_gateway'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
