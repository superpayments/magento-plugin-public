<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Sales\Api\Data\OrderInterface;
use Superpayments\SuperPayment\Model\Config\Source\Environment;

class OrderStatusChangedDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrlBase() . self::CUSTOM_EVENTS;
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_POST;
    }

    protected function getBody(array $buildSubject): ?array
    {
        /** @var OrderInterface $order */
        $order = $buildSubject['order'];

        return [
            'event' => 'OrderStatusChanged',
            'payload' => [
                'orderId' => $order->getIncrementId(),
                'oldStatus' => $order->getOrigData('status'),
                'newStatus' => $order->getData('status'),
                'orderAmount' => $this->priceConverter->minorUnitAmount($order->getGrandTotal()),
                'orderPaymentMethod' => $order->getPayment() ? $order->getPayment()->getMethod() : null,
                'orderCreatedAt' => $order->getCreatedAt() ? $order->getCreatedAt() : null,
                'superCartId' => $order->getOfferId() ?? null,
                'superTransactionId' => $order->getPayment()
                    ? $order->getPayment()->getAdditionalInformation('transactionId') : null,
            ],
            'metadata' => [
                'platform' => 'magento',
                'pluginVersion' => $this->config->getModuleVersion(),
                'magentoVersion' => $this->config->getMagentoVersion(),
                'magentoEdition' => $this->config->getMagentoEdition(),
                'integrationId' => $this->config->getIntegrationId(),
                'phpVersion' => phpversion(),
                'siteUrl' => $buildSubject['store']->getBaseUrl(),
            ],
        ];
    }
}
