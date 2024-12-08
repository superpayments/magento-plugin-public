<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Quote\Api\Data\CartInterface;

class CreateOfferDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrl() . self::ENDPOINT_REWARD_CALCULATIONS;
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_POST;
    }

    protected function getBody(array $buildSubject): ?array
    {
        /** @var CartInterface $quote */
        $quote = $buildSubject['quote'];
        $quote->collectTotals();

        return [
            'amount' => $this->priceConverter->minorUnitAmount($quote->getGrandTotal()) ?: 1,
            'brandId' => $this->getBrandId(),
            'currency' => $quote->getStore()->getCurrentCurrencyCode() ?? 'GBP',
        ];
    }
}
