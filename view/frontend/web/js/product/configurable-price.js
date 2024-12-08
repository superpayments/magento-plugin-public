require(['jquery', 'Magento_Catalog/js/price-box'], function ($) {
    $(document).ready(function () {
        try {
            var priceBoxes = $('.price-box');
            if (priceBoxes !== undefined && priceBoxes) {
                priceBoxes.each(function () {
                    var priceBox = $(this);
                    priceBox.on('priceUpdated', function (event, displayPrices) {
                        if (displayPrices !== undefined && ('finalPrice' in displayPrices) && ('amount' in displayPrices.finalPrice)) {
                            var updatedPrice = displayPrices.finalPrice.amount;
                            if (updatedPrice !== undefined && updatedPrice > 0) {
                                $('super-product-callout').attr('productamount', Math.round(updatedPrice*100));
                            }
                        }
                    });
                });
            }
        }
        catch(err) {
            console.error('Superpayments update price: ' + err.message);
        }
    });
});
