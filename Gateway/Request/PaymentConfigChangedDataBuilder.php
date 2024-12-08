<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Sales\Api\Data\OrderInterface;

class PaymentConfigChangedDataBuilder extends AbstractDataBuilder
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
        $payload = $buildSubject['payload'];

        return [
            'event' => 'PluginSettingsUpdated',
            'payload' => $payload,
            'metadata' => [
                'platform' => 'magento',
                'pluginVersion' => $this->config->getModuleVersion(),
                'magentoVersion' => $this->config->getMagentoVersion(),
                'magentoEdition' => $this->config->getMagentoEdition(),
                'integrationId' => $this->config->getIntegrationId(),
                'siteUrl' => $buildSubject['store']->getBaseUrl(),
            ],
        ];
    }
}
