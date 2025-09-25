<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Sales\Api\Data\OrderInterface;
use Throwable;

class OrderCreatedDataBuilder extends AbstractDataBuilder
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
            'event' => 'OrderCreated',
            'payload' => [
                'orderId' => $order->getIncrementId(),
                'orderAmount' => $this->priceConverter->minorUnitAmount($order->getGrandTotal()),
                'orderPaymentMethod' => $order->getPayment() ? $order->getPayment()->getMethod() : null,
                'orderCreatedAt' => $order->getCreatedAt() ? $order->getCreatedAt() : null,
                'superCartId' => $order->getOfferId() ?? null,
                'cart' => [
                    'id' => $order->getQuoteId() ?: 'unknown-' . time(),
                    'items' => $this->getItems($order),
                ],
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

    protected function getItems(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            try {
                $items[] = [
                    'sku' => $item->getProduct()->getSKU() ?? null,
                    'variationSku' => $item->getSKU(),
                    'name' => $item->getName(),
                    'url' => $item->getProduct()->getUrlModel()->getUrl($item->getProduct()),
                    'quantity' => (int) $item->getQtyOrdered(),
                    'minorUnitAmount' => $this->priceConverter->minorUnitAmount($item->getPrice()),
                    'description' => null,
                ];
            } catch (Throwable $e) {
                $this->logger->error(
                    '[SuperPayment] OrderCreatedDataBuilder::getItems ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        if (empty($items)) {
            $items[] = [
                'name' => 'empty',
                'url' => 'http://empty.com/',
                'quantity' => (int) 1,
                'minorUnitAmount' => 1,
            ];
        }

        return $items;
    }
}
