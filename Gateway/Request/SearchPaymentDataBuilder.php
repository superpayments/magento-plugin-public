<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Sales\Api\Data\OrderInterface;
use Superpayments\SuperPayment\Model\Config\Source\Environment;

class SearchPaymentDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrl() . sprintf(
            '%s/search',
            self::ENDPOINT_PAYMENTS
        );
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_GET;
    }

    protected function getBody(array $buildSubject): ?array
    {
        /** @var OrderInterface $order */
        $order = $buildSubject['order'];

        return [
            'externalReference' => $order->getIncrementId(),
            'test' => ($this->config->getEnvironment() == Environment::SANDBOX),
        ];
    }
}
