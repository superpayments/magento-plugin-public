<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

class RetrievePaymentDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrl() . sprintf(
            '%s/%s',
            self::ENDPOINT_PAYMENTS,
            $buildSubject['transaction_id']
        );
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_GET;
    }

    protected function getBody(array $buildSubject): ?array
    {
        return null;
    }
}
