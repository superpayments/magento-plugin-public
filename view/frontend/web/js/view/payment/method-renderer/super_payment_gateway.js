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
        'Magento_Customer/js/customer-data',
    ],
    function (
        $,
        Component,
        url,
        redirectOnSuccess,
        quote,
        fullScreenLoader,
        $t,
        customerData,
    ) {
        'use strict';

        var mode = window.checkoutConfig.payment.super_payment_gateway.mode;
        var debug = window.checkoutConfig.payment.super_payment_gateway.debug;
        var paymentLogo = window.checkoutConfig.payment.super_payment_gateway.paymentLogo;
        var isDefault = window.checkoutConfig.payment.super_payment_gateway.isDefault;
        var loaderContainerId = window.checkoutConfig.payment.super_payment_gateway.loaderContainerId;
        var loaded = false;

        return Component.extend({
            defaults: {
                template: 'Superpayments_SuperPayment/payment/form',
                redirectAfterPlaceOrder: true
            },

            initialize: function () {
                loaded = false;
                return this._super();
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

                quote.billingAddress.subscribe(function (billingAddress)
                {
                    if (!billingAddress)
                        return;

                    if (!billingAddress.telephone)
                        return;

                    this.insertMemberNumber(billingAddress.telephone);
                }, this);

                quote.shippingAddress.subscribe(function (shippingAddress)
                {
                    if (!shippingAddress)
                        return;

                    if (!shippingAddress.telephone)
                        return;

                    this.insertMemberNumber(shippingAddress.telephone);
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
                var self = this;
                $.ajax({
                    url: offerurl,
                    dataType : 'json',
                    type : 'POST',
                    async: false,
                    cache: false,
                    beforeSend: function() {
                        $(loaderContainerId).trigger('processStart');
                    },
                    success: function(data, status, xhr) {
                        $(loaderContainerId).trigger('processStop');
                        $(".superpaymentmethod").show();
                        var description = data.description;
                        var title = data.title;
                        $(".superblockcontent").html(description);
                        $(".superpayment_title").html(title);
                        self.insertMemberNumber();
                    },
                    error: function (xhr, status, errorThrown) {
                        console.log('Error happens. Try again.');
                        console.log(errorThrown);
                        $(loaderContainerId).trigger('processStop');
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

            handleSuperErrorResponse: function() {
                this.isProcessing = false;
                fullScreenLoader.stopLoader();
            },

            insertMemberNumber: function(phoneNumber = null) {
                try {
                    if (window.superCheckout && $('super-checkout').length > 0 && $('super-checkout').attr('phone-number') === '') {
                        var billingPhone = phoneNumber !== null ? phoneNumber : (quote.billingAddress() ? quote.billingAddress().telephone : '');
                        if (billingPhone && billingPhone.length >= 10) {
                            $('super-checkout').attr('phone-number', billingPhone);
                        }
                    }
                } catch (error) {
                    console.error('Super insert member number error occurred:', error);
                }
            },

            placeOrderClick: function (data, event) {
                var self = this;

                try {
                    if (this.isProcessing) {
                        return false;
                    } else {
                        this.isProcessing = true;
                    }

                    fullScreenLoader.startLoader();

                    if (!window.superCheckout) {
                        self.messageContainer.addErrorMessage({message: 'Please wait for the payment form to load, then try again.'});
                        throw new Error('window.superCheckout is not yet available');
                    }

                    const {
                        cusFirstName,
                        cusLastName,
                        cusEmail,
                        cusPhoneNumber
                    } = this.getCustomerDetails();

                    window.superCheckout.submit({
                        customerInformation: {
                            firstName:   cusFirstName,
                            lastName:    cusLastName,
                            email:       cusEmail,
                            phoneNumber: cusPhoneNumber,
                        }
                    }).then(function (response) {
                        if (response.status === 'SUCCESS') {
                            self.placeOrder();
                        } else if (response.status === 'FAILURE') {
                            self.messageContainer.addErrorMessage({message: $t(response.errorMessage)});
                        }

                        if (response.status !== 'PENDING') {
                            self.handleSuperErrorResponse();
                        }
                    }).catch(function (err) {
                        console.log(err);
                        self.messageContainer.addErrorMessage({message: 'An error occurred on the server. Please try again, if the problem persists please contact us. (' + err + ')'});
                        self.handleSuperErrorResponse();
                    });

                } catch (error) {
                    console.error('Super place order error occurred:', error);
                    self.handleSuperErrorResponse();
                }

                return false;
            },

            getCustomerDetails: function() {
                try {
                    const billing = quote.billingAddress() || {};
                    const {
                        telephone:   cusPhoneNumber = '',
                        firstname:   cusFirstName   = '',
                        lastname:    cusLastName    = ''
                    } = billing;

                    const customer = customerData?.get('customer')() || {};
                    let spContact = '';
                    if (customer.spContact) {
                        try {
                            spContact = atob(customer.spContact);
                        } catch (e) {
                            console.warn('SuperPayments failed to decode sp contact:', e);
                        }
                    }
                    const cusEmail = spContact || quote.guestEmail || '';

                    return { cusFirstName, cusLastName, cusEmail, cusPhoneNumber };
                } catch (error) {
                    console.error('SuperPayments error retrieving customer details:', error);
                    return { cusFirstName: '', cusLastName: '', cusEmail: '', cusPhoneNumber: '' };
                }
            },

        });
    }
);
