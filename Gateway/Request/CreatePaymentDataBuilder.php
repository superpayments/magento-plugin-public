<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Sales\Api\Data\OrderInterface;
use Superpayments\SuperPayment\Model\Config\Source\Environment;

class CreatePaymentDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrl() . self::ENDPOINT_PAYMENTS;
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
            'rewardCalculationId' => $buildSubject['rewardCalculationId'],
            'successUrl' => $this->getSuccessUrl((string) $order->getIncrementId()),
            'cancelUrl' => $this->getCancelUrl((string) $order->getIncrementId()),
            'failureUrl' => $this->getFailureUrl((string) $order->getIncrementId()),
            'amount' => $this->priceConverter->minorUnitAmount($order->getGrandTotal()),
            'currency' => $order->getOrderCurrency()->getCode(),
            'brandId' => $this->getBrandId(),
            'externalReference' => $order->getIncrementId(),
            'email' => $order->getCustomerEmail(),
            'test' => ($this->config->getEnvironment() == Environment::SANDBOX),
            'flowdata' => [
                'flowType' => 'EXPRESS',
            ],
        ];
    }

    private function getSuccessUrl(string $incrementId): string
    {
        if ($this->config->isGraphQLEnabled() && !empty($this->config->getGraphQLPaymentSuccessURL())) {
            return $this->config->getGraphQLPaymentSuccessURL() .
                '?ref=' . rawurlencode($incrementId) .
                '&transactionReference=' . rawurlencode($incrementId);
        }

        return $this->urlBuilder->getUrl(
            'superpayment/callback/success/ref/' . rawurlencode($incrementId) . '/',
            [
                '_secure' => $this->config->isWebsiteSecure(),
                '_query' => ['transactionReference' => rawurlencode($incrementId)]
            ]
        );
    }

    private function getCancelUrl(string $incrementId): string
    {
        if ($this->config->isGraphQLEnabled() && !empty($this->config->getGraphQLPaymentCancelURL())) {
            return $this->config->getGraphQLPaymentCancelURL() . '?ref=' . rawurlencode($incrementId);
        }
        return $this->urlBuilder->getUrl(
            'superpayment/callback/cancel/ref/' . $incrementId . '/',
            ['_secure' => $this->config->isWebsiteSecure()]
        );
    }

    private function getFailureUrl(string $incrementId): string
    {
        if ($this->config->isGraphQLEnabled() && !empty($this->config->getGraphQLPaymentFailureURL())) {
            return $this->config->getGraphQLPaymentFailureURL() . '?ref=' . rawurlencode($incrementId);
        }
        return $this->urlBuilder->getUrl(
            'superpayment/callback/failure/ref/' . $incrementId . '/',
            ['_secure' => $this->config->isWebsiteSecure()]
        );
    }
}
