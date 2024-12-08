/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/translate',
    ],
    function (
        $,
        Component,
        url,
        redirectOnSuccess,
        quote,
        fullScreenLoader,
        $t,
    ) {
        'use strict';

        var mode = window.checkoutConfig.payment.super_payment_gateway.mode;
        var debug = window.checkoutConfig.payment.super_payment_gateway.debug;
        var paymentLogo = window.checkoutConfig.payment.super_payment_gateway.paymentLogo;
        var isDefault = window.checkoutConfig.payment.super_payment_gateway.isDefault;
        var loaded = false;

        return Component.extend({
            defaults: {
                template: 'Superpayments_SuperPayment/payment/form',
                redirectAfterPlaceOrder: true
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'canPlaceOrder'
                    ]);

                if (isDefault) {
                    this.selectPaymentMethod();
                }

                quote.totals.subscribe(function (totals)
                {
                    if (!totals || !totals.grand_total) {
                        return;
                    }

                    var minorUnitAmount = Math.round(totals.grand_total*100);
                    if (totals.quote_currency_code === totals.base_currency_code && totals.grand_total !== totals.base_grand_total) {
                        //fix for magento versions prior 2.4.6
                        minorUnitAmount = Math.round(totals.base_grand_total*100);
                    }

                    $('super-payment-method-title').attr('cartAmount', minorUnitAmount);
                    $('super-checkout').attr('amount', minorUnitAmount);
                    $('super-payment-method-description').attr('cartAmount', minorUnitAmount);
                }, this);

                return this;
            },

            getCode: function() {
                return 'super_payment_gateway';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                    }
                };
            },
            getLogo: function(){
                return paymentLogo;
            },

            getSuperDiscountBanner(){
                var offerurl =  url.build('superpayment/discount/offerbanner');
                if(loaded) return;
                $.ajax({
                    url: offerurl,
                    dataType : 'json',
                    type : 'POST',
                    async: false,
                    cache: false,
                    beforeSend: function() {
                        $('body').trigger('processStart');
                    },
                    success: function(data, status, xhr) {
                        $('body').trigger('processStop');
                        $(".superpaymentmethod").show();
                        var description = data.description;
                        var title = data.title;
                        $(".superblockcontent").html(description);
                        $(".superpayment_title").html(title);
                    },
                    error: function (xhr, status, errorThrown) {
                        console.log('Error happens. Try again.');
                        console.log(errorThrown);
                        $('body').trigger('processStop');
                    }
                });
                loaded = true;
            },

            // isPlaceOrderActionAllowed: function() {
            //     return true;
            // },

            afterPlaceOrder: function() {
                redirectOnSuccess.redirectUrl = url.build('superpayment/payment/redirect');
                this.redirectAfterPlaceOrder = true;
            },

            placeOrderClick: function (data, event) {
                var self = this;

                if (this.isProcessing) {
                    return false;
                } else {
                    this.isProcessing = true;
                }

                fullScreenLoader.startLoader();

                window.superCheckout.submit().then(function (response) {
                    if (response.status === 'SUCCESS') {
                        self.placeOrder();
                    } else if (response.status === 'FAILURE') {
                        self.messageContainer.addErrorMessage({message: $t(response.errorMessage)});
                    }

                    if (response.status !== 'PENDING') {
                        fullScreenLoader.stopLoader();
                        self.isProcessing = false;
                    }
                }).catch(function (err) {
                    console.log(err);
                    self.messageContainer.addErrorMessage({message: 'An error occurred on the server. Please try again, if the problem persists please contact us. (' + err + ')'});

                    fullScreenLoader.stopLoader();
                    self.isProcessing = false;
                });

                return false;
            },
        });
    }
);
