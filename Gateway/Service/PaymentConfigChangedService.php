<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use InvalidArgumentException;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Helper\Data;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Throwable;

class PaymentConfigChangedService implements ApiServiceInterface
{
    /** @var CommandPoolInterface $commandPool */
    private $commandPool;

    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var ConfigCollectionFactory */
    private $configCollectionFactory;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Data */
    private $paymentHelper;

    public function __construct(
        CommandPoolInterface $commandPool,
        DataObjectFactory $dataObjectFactory,
        ScopeConfigInterface $scopeConfig,
        ConfigCollectionFactory $configCollectionFactory,
        StoreManagerInterface $storeManager,
        Data $paymentHelper,
        LoggerInterface $logger
    ) {
        $this->commandPool = $commandPool;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->configCollectionFactory = $configCollectionFactory;
        $this->paymentHelper = $paymentHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param array $subject
     * @throws InvalidArgumentException
     * @throws ApiServiceException
     */
    public function execute(array $subject): DataObject
    {
        $subject['result'] = $this->dataObjectFactory->create();

        try {
            $subject['store'] = $this->storeManager->getStore();
            $subject['payload'] = $this->getPayload($subject);
            $this->commandPool->get('payment_config_changed')->execute($subject);
        } catch (Throwable $e) {
            $this->logger->error('[SuperPayment] ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
            throw new ApiServiceException(__($e->getMessage()));
        }

        return $subject['result'];
    }

    private function getPayload(array $subject): ?array
    {
        $payload = [];
        try {
            $scope = $subject['scope'];
            $scopeId = !empty($subject['scopeId']) ? $subject['scopeId'] : null;
            $payload['enabled'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_ACTIVE, $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['environment'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_ENVIRONMENT, $scope, $scopeId);
            $payload['success_url'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_GRAPHQL_SUCCESS_URL, $scope, $scopeId);
            $payload['failure_url'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_GRAPHQL_FAILURE_URL, $scope, $scopeId);
            $payload['cancel_url'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_GRAPHQL_CANCEL_URL, $scope, $scopeId);
            $payload['flow_type'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_FLOW_TYPE, $scope, $scopeId);
            $payload['handoff_success_route'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_HANDOFF_SUCCESS_ROUTE, $scope, $scopeId);
            $payload['enable_order_confirmation_page_web_component'] =
                $this->getConfig('payment/super_payment_gateway/confirmation_page_web_component', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['enable_graphql'] =
                $this->getConfig('payment/super_payment_gateway/graphql', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['enable_plp'] =
                $this->getConfig('payment/super_payment_gateway/plp_banner', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['enable_pdp'] =
                $this->getConfig('payment/super_payment_gateway/pdp_banner', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['enable_bp'] =
                $this->getConfig('payment/super_payment_gateway/cart_banner', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['enable_banner_home'] =
                ($this->getConfig('payment/super_payment_gateway/banner_mode', $scope, $scopeId) == 'homepage') ?
                    'yes' : 'no';
            $payload['enable_banner_site'] =
                ($this->getConfig('payment/super_payment_gateway/banner_mode', $scope, $scopeId) == 'allpages') ?
                    'yes' : 'no';
            $payload['update_total'] = null;
            $payload['enable_order_received_page_referral_link'] =
                $this->getConfig('payment/super_payment_gateway/confirmation_page_banner', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['enable_order_email_referral_link'] =
                $this->getConfig('payment/super_payment_gateway/confirmation_email_banner', $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['set_as_default_payment_method'] =
                $this->getConfig('payment/super_payment_gateway/' . Config::KEY_IS_DEFAULT, $scope, $scopeId) ?
                    'yes' : 'no';
            $payload['title'] = $this->getConfig('payment/super_payment_gateway/title', $scope, $scopeId);
            $payload['sort_order'] = $this->getConfig('payment/super_payment_gateway/sort_order', $scope, $scopeId);

            $methods = [];
            foreach ($this->paymentHelper->getPaymentMethods() as $paymentMethodCode => $paymentMethod) {
                $paymentSortOrderConfig = '/payment/' . $paymentMethodCode . '/sort_order';
                if ($value = $this->getConfig($paymentSortOrderConfig, $scope, $scopeId)) {
                    $methods[$paymentMethodCode] = $value;
                } elseif (isset($paymentMethod['sort_order']) && !empty($paymentMethod['sort_order'])) {
                    $methods[$paymentMethodCode] = $paymentMethod['sort_order'];
                }
            }
            $payload['payment_gateway_order'] = $methods;
        } catch (Throwable $e) {
            $this->logger->error('[SuperPayment] ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
        }

        return $payload;
    }

    private function getConfig(string $configPath, $scope = null, $scopeId = null, $skipCache = false): ?string
    {
        $scope = ($scope === null) ? ScopeInterface::SCOPE_STORE : $scope;
        $scopeId = ($scopeId === null) ? $this->storeManager->getStore()->getId() : $scopeId;
        if ($skipCache) {
            if ($scope === ScopeInterface::SCOPE_STORE) {
                $scope = ScopeInterface::SCOPE_STORES;
            } elseif ($scope === ScopeInterface::SCOPE_WEBSITE) {
                $scope = ScopeInterface::SCOPE_WEBSITES;
            }
            $collection = $this->configCollectionFactory->create()
                ->addFieldToFilter('scope', $scope)
                ->addFieldToFilter('scope_id', $scopeId)
                ->addFieldToFilter('path', ['like' => $configPath . '%']);
            if ($collection->count()) {
                return $collection->getFirstItem()->getValue();
            }
        } else {
            return $this->scopeConfig->getValue($configPath, $scope, $scopeId);
        }
        return null;
    }
}
