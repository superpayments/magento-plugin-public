# SuperPayments plugin for Magento 2

## Requirements

This plugin supports Magento2 version

* Magento >=2.4
* PHP >= 7.4


## How to install module using Composer:

1. Open a terminal and run the following command in your Magento directory:
```
composer require superpayments/superpayment "^2.0"
```
2. Set up the module by running the following commands:
```
php bin/magento module:enable Superpayments_SuperPayment
php bin/magento setup:upgrade
php bin/magento cache:flush
```
3. If you run Magento in production mode, you also must compile and deploy the module's static files:
```
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```
