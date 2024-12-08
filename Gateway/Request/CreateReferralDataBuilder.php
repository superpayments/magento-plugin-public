<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

class CreateReferralDataBuilder extends AbstractDataBuilder
{
    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrlV3() . self::ENDPOINT_REFERRAL;
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_POST;
    }

    protected function getBody(array $buildSubject): ?array
    {
        return [];
    }
}
