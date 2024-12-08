<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

class GuestEmailTokenDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        $urlPath = sprintf(
            '/pay/external/payment-intent/%s/guest-email-token',
            $buildSubject['paymentIntentId']
        );
        return $this->config->getUrlV3() . $urlPath;
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
        unset($headers['Authorization']);
        unset($headers['Content-Type']);
        return $headers;
    }
}
