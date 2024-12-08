require([
    'jquery',
    'ko',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'Superpayments_SuperPayment/js/view/payment/method-renderer/super_payment_gateway'
], function ($, ko, url, quote, SuperPaymentGateway) {
    'use strict';

    var quoteTotals = ko.observable(quote.totals());

    quote.totals.subscribe(function () {
        $('#top-super-banner').load(window.location.href + ' #top-super-banner');

        var offerurl =  url.build('superpayment/discount/offerbanner');

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
    });

    return {
        quoteTotals: quoteTotals
    };
});
