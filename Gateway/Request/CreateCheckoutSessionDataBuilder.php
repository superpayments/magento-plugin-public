<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

class CreateCheckoutSessionDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrlV3() . self::ENDPOINT_CHECKOUT_SESSIONS;
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_POST;
    }

    protected function getBody(array $buildSubject): ?array
    {
        return null;
    }

    protected function getHeaders(array $buildSubject): array
    {
        $headers = parent::getHeaders($buildSubject);
        $headers['Checkout-Api-Key'] = $headers['Authorization'];
        unset($headers['Content-Type']);
        return $headers;
    }
}
