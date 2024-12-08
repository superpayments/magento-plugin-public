<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

class BusinessConfigDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrlBase() . self::BUSINESS_CONFIG;
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_GET;
    }

    protected function getBody(array $buildSubject): ?array
    {
        return [];
    }
}
