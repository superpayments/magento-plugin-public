<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Payment\Gateway\Config\Config as PaymentsConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Service\BusinessConfigService;
use Superpayments\SuperPayment\Model\Config\Source\Environment;
use Throwable;

class Config extends PaymentsConfig
{
    public const PAYMENT_CODE = 'super_payment_gateway';
    public const MODULE_CODE = 'Superpayments_SuperPayment';

    public const KEY_ACTIVE = 'active';
    public const KEY_VERSION = 'version';
    public const KEY_ENVIRONMENT = 'environment';
    public const KEY_API_KEY = 'api_key';
    public const KEY_CONFIRMATION_KEY = 'confirmation_key';
    public const KEY_SANDBOX_API_KEY = 'sandbox_api_key';
    public const KEY_SANDBOX_CONFIRMATION_KEY = 'sandbox_confirmation_key';
    public const KEY_PUBLISHABLE_KEY = 'publishable_key';
    public const KEY_INTEGRATION_ID = 'integration_id';
    public const KEY_BRAND_ID = 'brand_id';
    public const KEY_SANDBOX_PUBLISHABLE_KEY = 'sandbox_publishable_key';
    public const KEY_SANDBOX_INTEGRATION_ID = 'sandbox_integration_id';
    public const KEY_SANDBOX_BRAND_ID = 'sandbox_brand_id';
    public const KEY_SORT_ORDER = 'sort_order';
    public const KEY_TITLE = 'title';
    public const KEY_ALLOW_SPECIFIC = 'allowspecific';
    public const KEY_SPECIFIC_COUNTRY = 'specificcountry';
    public const KEY_DEBUG = 'debug';
    public const KEY_USE_HTTPS = 'use_https';
    public const KEY_GATEWAY_TIMEOUT = 'gateway_timeout';
    public const KEY_FORCE_EMAIL_SEND = 'force_email_send';
    public const KEY_AUTO_REGISTER_CAPTURE = 'auto_register_capture';
    public const KEY_IS_DEFAULT = 'default_selected';
    public const KEY_API_URL_BASE = 'api_url_base';
    public const KEY_API_URL_SANDBOX_BASE = 'api_url_sandbox_base';
    public const KEY_CDN_URL = 'cdn_url';
    public const KEY_CDN_URL_SANDBOX = 'cdn_url_sandbox';
    public const KEY_PAYMENT_JS_URL = 'payment_js_url';
    public const KEY_PAYMENT_JS_URL_SANDBOX = 'payment_js_url_sandbox';
    public const KEY_GRAPHQL = 'graphql';
    public const KEY_GRAPHQL_SUCCESS_URL = 'graphql_success_url';
    public const KEY_GRAPHQL_CANCEL_URL = 'graphql_cancel_url';
    public const KEY_GRAPHQL_FAILURE_URL = 'graphql_failure_url';
    public const KEY_FLOW_TYPE = 'flow_type';
    public const KEY_HANDOFF_SUCCESS_ROUTE = 'handoff_success_route';

    /** @var null|int $store */
    private $store;

    /** @var ModuleListInterface */
    private $moduleList;

    /** @var ProductMetadataInterface */
    private $productMetadata;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var BusinessConfigService $businessConfigService */
    private $businessConfigService;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var array */
    private $businessConfigCache = [];

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata,
        BusinessConfigService $businessConfigService,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        string $methodCode = self::PAYMENT_CODE,
        string $pathPattern = PaymentsConfig::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);

        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->businessConfigService = $businessConfigService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->store = null;
    }

    public function setStoreId(int $storeId = null): void
    {
        $this->store = $storeId;
    }

    public function getStoreId(): ?int
    {
        return $this->store;
    }

    public function isActive(): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $this->getStoreId());
    }

    public function getModuleVersion(): ?string
    {
        if ($value = $this->getValue(self::KEY_VERSION, $this->getStoreId())) {
            return $value;
        }
        $moduleInfo = $this->moduleList->getOne(self::MODULE_CODE);
        return $moduleInfo['setup_version'];
    }

    public function getMagentoVersion(): ?string
    {
        if ($value = $this->productMetadata->getVersion()) {
            return (string) $value;
        }
        return null;
    }

    public function getMagentoEdition(): ?string
    {
        if ($value = $this->productMetadata->getEdition()) {
            return (string) $value;
        }
        return null;
    }

    public function getEnvironment(): ?string
    {
        if ($value = $this->getValue(self::KEY_ENVIRONMENT, $this->getStoreId())) {
            return $value;
        }
        return null;
    }

    public function getApiKey(): ?string
    {
        if ($this->getEnvironment() == Environment::SANDBOX) {
            return ($this->getValue(self::KEY_SANDBOX_API_KEY, $this->getStoreId())) ?: null;
        } else {
            return ($this->getValue(self::KEY_API_KEY, $this->getStoreId())) ?: null;
        }
    }

    public function getConfirmationKey(): ?string
    {
        if ($this->getEnvironment() == Environment::SANDBOX) {
            return ($this->getValue(self::KEY_SANDBOX_CONFIRMATION_KEY, $this->getStoreId())) ?: null;
        } else {
            return ($this->getValue(self::KEY_CONFIRMATION_KEY, $this->getStoreId())) ?: null;
        }
    }

    private function saveBusinessConfigResultLocalCache(DataObject $result)
    {
        if (!$this->getApiKey() || $this->getStoreId() === null) {
            return;
        }
        $this->businessConfigCache[$this->getStoreId()][$this->getApiKey()] = $result;
    }

    private function getBusinessConfigResultLocalCache(): ?DataObject
    {
        if (
            isset($this->businessConfigCache[$this->getStoreId()]) &&
            isset($this->businessConfigCache[$this->getStoreId()][$this->getApiKey()]) &&
            $this->businessConfigCache[$this->getStoreId()][$this->getApiKey()] instanceof DataObject
        ) {
            return $this->businessConfigCache[$this->getStoreId()][$this->getApiKey()];
        }
        return null;
    }

    public function getPublishableKey(): ?string
    {
        if ($this->getEnvironment() == Environment::SANDBOX) {
            $publishableKey = ($this->getValue(self::KEY_SANDBOX_PUBLISHABLE_KEY, $this->getStoreId())) ?: null;
        } else {
            $publishableKey = ($this->getValue(self::KEY_PUBLISHABLE_KEY, $this->getStoreId())) ?: null;
        }

        $update = false;
        if (!empty($publishableKey)) {
            $apiKey = $this->getApiKey();
            $parts = explode('||', $publishableKey);
            if (!isset($parts[1]) || $parts[1] != $apiKey) {
                $update = true;
            }
            $publishableKey = $parts[0];
        }

        if ($update && $cachedResult = $this->getBusinessConfigResultLocalCache()) {
            if ($publishableKey = $cachedResult->getPublishableKey()) {
                $update = false;
            }
        }

        if (!$publishableKey || $update) {
            try {
                $result = $this->businessConfigService->setConfig($this)->execute();
                $this->saveBusinessConfigResultLocalCache($result);
                $publishableKey = $result->getPublishableKey();
            } catch (Throwable $e) {
                $this->logger->error('[SuperPayment] no publishable key ' . $e->getMessage());
            }
        }

        return $publishableKey;
    }

    public function getIntegrationId(): ?string
    {
        if ($this->getEnvironment() == Environment::SANDBOX) {
            $integrationId = ($this->getValue(self::KEY_SANDBOX_INTEGRATION_ID, $this->getStoreId())) ?: null;
        } else {
            $integrationId = ($this->getValue(self::KEY_INTEGRATION_ID, $this->getStoreId())) ?: null;
        }

        $update = false;
        if (!empty($integrationId)) {
            $apiKey = $this->getApiKey();
            $parts = explode('||', $integrationId);
            if (!isset($parts[1]) || $parts[1] != $apiKey) {
                $update = true;
            }
            $integrationId = $parts[0];
        }

        if ($update && $cachedResult = $this->getBusinessConfigResultLocalCache()) {
            if ($integrationId = $cachedResult->getIntegrationId()) {
                $update = false;
            }
        }

        if (!$integrationId || $update) {
            try {
                $result = $this->businessConfigService->setConfig($this)->execute();
                $this->saveBusinessConfigResultLocalCache($result);
                $integrationId = $result->getIntegrationId();
            } catch (Throwable $e) {
                $this->logger->error('[SuperPayment] no integration id ' . $e->getMessage());
            }
        }

        return $integrationId;
    }

    public function getBrandId(): ?string
    {
        if ($this->getEnvironment() == Environment::SANDBOX) {
            $brandId = ($this->getValue(self::KEY_SANDBOX_BRAND_ID, $this->getStoreId())) ?: null;
        } else {
            $brandId = ($this->getValue(self::KEY_BRAND_ID, $this->getStoreId())) ?: null;
        }

        $update = false;
        if (!empty($brandId)) {
            $apiKey = $this->getApiKey();
            $parts = explode('||', $brandId);
            if (!isset($parts[1]) || $parts[1] != $apiKey) {
                $update = true;
            }
            $brandId = $parts[0];
        }

        if ($update && $cachedResult = $this->getBusinessConfigResultLocalCache()) {
            if ($brandId = $cachedResult->getBrandId()) {
                $update = false;
            }
        }

        if (!$brandId || $update) {
            try {
                $result = $this->businessConfigService->setConfig($this)->execute();
                $this->saveBusinessConfigResultLocalCache($result);
                $brandId = $result->getBrandId();
            } catch (Throwable $e) {
                $this->logger->error('[SuperPayment] no brand id ' . $e->getMessage());
            }
        }

        return $brandId;
    }

    public function getSortOrder(): int
    {
        return (int) $this->getValue(self::KEY_SORT_ORDER, $this->getStoreId());
    }

    public function getTitle(): ?string
    {
        if ($value = $this->getValue(self::KEY_TITLE, $this->getStoreId())) {
            return $value;
        }
        return null;
    }

    public function getAllowSpecific(): bool
    {
        return (bool) $this->getValue(self::KEY_ALLOW_SPECIFIC, $this->getStoreId());
    }

    public function getSpecificCountry(): ?array
    {
        if ($value = $this->getValue(self::KEY_SPECIFIC_COUNTRY, $this->getStoreId())) {
            return $value;
        }
        return null;
    }

    public function isDebugEnabled(): bool
    {
        return (bool) $this->getValue(self::KEY_DEBUG, $this->getStoreId());
    }

    public function isForceEmailSendEnabled(): bool
    {
        return (bool) $this->getValue(self::KEY_FORCE_EMAIL_SEND, $this->getStoreId());
    }

    public function isAutoRegisterCaptureEnabled(): bool
    {
        return (bool) $this->getValue(self::KEY_AUTO_REGISTER_CAPTURE, $this->getStoreId());
    }

    public function isWebsiteSecure(): bool
    {
        return (bool) $this->getValue(self::KEY_USE_HTTPS, $this->getStoreId());
    }

    public function getUrlBase(): string
    {
        if ($this->getEnvironment() == 'production') {
            return $this->getValue(self::KEY_API_URL_BASE, $this->getStoreId());
        }
        return $this->getValue(self::KEY_API_URL_SANDBOX_BASE, $this->getStoreId());
    }

    public function getCdnUrl(): string
    {
        if ($this->getEnvironment() == 'production') {
            return $this->getValue(self::KEY_CDN_URL, $this->getStoreId());
        }
        return $this->getValue(self::KEY_CDN_URL_SANDBOX, $this->getStoreId());
    }

    public function getPaymentJsUrl(): string
    {
        if ($this->getEnvironment() == 'production') {
            return $this->getValue(self::KEY_PAYMENT_JS_URL, $this->getStoreId());
        }
        return $this->getValue(self::KEY_PAYMENT_JS_URL_SANDBOX, $this->getStoreId());
    }

    public function getUrl(): string
    {
        return $this->getUrlBase() . '/2024-02-01';
    }

    public function getUrlV3(): string
    {
        return $this->getUrlBase() . '/v3';
    }

    public function getGatewayTimeout(): int
    {
        return (int) $this->getValue(self::KEY_GATEWAY_TIMEOUT, $this->getStoreId());
    }

    public function isOrderSendEmailEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'sales_email/order/enabled',
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    public function isInvoiceSendEmailEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'sales_email/invoice/enabled',
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    public function isDefaultSelected(): bool
    {
        return (bool) $this->getValue(self::KEY_IS_DEFAULT, $this->getStoreId());
    }

    public function isGraphQLEnabled(): bool
    {
        return (bool) $this->getValue(self::KEY_GRAPHQL, $this->getStoreId());
    }

    public function getGraphQLPaymentSuccessURL(): ?string
    {
        return $this->getValue(self::KEY_GRAPHQL_SUCCESS_URL, $this->getStoreId());
    }

    public function getGraphQLPaymentCancelURL(): ?string
    {
        return $this->getValue(self::KEY_GRAPHQL_CANCEL_URL, $this->getStoreId());
    }

    public function getGraphQLPaymentFailureURL(): ?string
    {
        return $this->getValue(self::KEY_GRAPHQL_FAILURE_URL, $this->getStoreId());
    }

    public function getHandoffSuccessRoute(): ?string
    {
        return $this->getValue(self::KEY_HANDOFF_SUCCESS_ROUTE, $this->getStoreId());
    }
}
