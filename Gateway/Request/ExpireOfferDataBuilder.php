<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

class ExpireOfferDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        $urlV3 = $this->config->getUrlV3();
        $urlPath = sprintf(
            '%s/%s/expire',
            self::ENDPOINT_OFFERS,
            $buildSubject['reward_calculation_id']
        );
        return $urlV3 . $urlPath;
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
        return $headers;
    }
}
