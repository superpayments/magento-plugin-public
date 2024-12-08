<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\PriceConverter;

abstract class AbstractDataBuilder implements BuilderInterface
{
    public const ENDPOINT_CHECKOUT_SESSIONS = '/checkout-sessions';
    public const ENDPOINT_PAYMENTS = '/payments';
    public const ENDPOINT_OFFERS = '/marketing-offers';
    public const ENDPOINT_REWARD_CALCULATIONS = '/reward-calculations';
    public const ENDPOINT_REFUNDS = '/refunds';
    public const ENDPOINT_REFERRAL = '/referral/generate-referral-link/element';
    public const BUSINESS_CONFIG = '/business-config';
    public const CUSTOM_EVENTS = '/custom-events';
    public const HTTP_POST = 'POST';
    public const HTTP_GET = 'GET';

    /** @var Config $config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var UrlInterface */
    protected $urlBuilder;

    /** @var SubjectReader $subjectReader */
    protected $subjectReader;

    /** @var PriceConverter $priceConverter */
    protected $priceConverter;

    public function __construct(
        Config $config,
        UrlInterface $urlBuilder,
        SubjectReader $subjectReader,
        PriceConverter $priceConverter,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->subjectReader = $subjectReader;
        $this->priceConverter = $priceConverter;
        $this->logger = $logger;
    }

    abstract protected function getUrl(array $buildSubject): string;

    abstract protected function getMethod(array $buildSubject): string;

    /** @return array|string */
    abstract protected function getBody(array $buildSubject): ?array;

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        if (isset($buildSubject['store'])) {
            $this->config->setStoreId((int) $buildSubject['store']->getId());
        }

        return [
            'url' => $this->getUrl($buildSubject),
            'method' => $this->getMethod($buildSubject),
            'headers' => $this->getHeaders($buildSubject),
            'body' => $this->getBody($buildSubject),
        ];
    }

    protected function getHeaders(array $buildSubject): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Version' => $this->config->getModuleVersion(),
            'Magento-Version' => $this->config->getMagentoVersion(),
            'Magento-Edition' => $this->config->getMagentoEdition(),
            'Authorization' => $this->config->getApiKey(),
            'Referer' => $buildSubject['store']->getBaseUrl(),
        ];
    }

    protected function getBrandId(): ?string
    {
        return $this->config->getBrandId();
    }
}
