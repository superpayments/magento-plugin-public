<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\Config\Source\Environment;

class BusinessConfigService implements ApiServiceInterface
{
    /** @var CommandPoolInterface $commandPool */
    private $commandPool;

    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var WriterInterface $configWriter */
    private $configWriter;

    /** @var Config $config */
    private $config;

    /** @var TypeListInterface $typeList */
    private $typeList;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    public function __construct(
        CommandPoolInterface $commandPool,
        DataObjectFactory $dataObjectFactory,
        WriterInterface $configWriter,
        TypeListInterface $typeList,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->commandPool = $commandPool;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->configWriter = $configWriter;
        $this->typeList = $typeList;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function setConfig(Config $config): BusinessConfigService
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @param array $subject
     * @throws InvalidArgumentException
     * @throws ApiServiceException
     */
    public function execute(array $subject = []): DataObject
    {
        $subject['result'] = $this->dataObjectFactory->create();
        $subject['store'] = $this->storeManager->getStore();

        try {
            $this->commandPool->get('business_config')->execute($subject);
        } catch (Exception $e) {
            $this->logger->error('[SuperPayment] BusinessConfigService ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
            throw new ApiServiceException(__('SuperPayments error on business config call. Please try again later.'));
        }

        if ($this->config->getApiKey()) {
            if ($publishableKey = $subject['result']->getPublishableKey()) {
                $configKey = 'payment/super_payment_gateway/' .
                    ($this->config->getEnvironment() == Environment::SANDBOX ?
                        'sandbox_publishable_key' : 'publishable_key');

                $this->configWriter->save(
                    $configKey,
                    $publishableKey . '||' . $this->config->getApiKey(),
                    ScopeInterface::SCOPE_STORES,
                    $this->config->getStoreId()
                );
            }

            if ($integrationId = $subject['result']->getIntegrationId()) {
                $configKey = 'payment/super_payment_gateway/' .
                    ($this->config->getEnvironment() == Environment::SANDBOX ?
                        'sandbox_integration_id' : 'integration_id');

                $this->configWriter->save(
                    $configKey,
                    $integrationId . '||' . $this->config->getApiKey(),
                    ScopeInterface::SCOPE_STORES,
                    $this->config->getStoreId()
                );
            }

            if ($brandId = $subject['result']->getBrandId()) {
                $configKey = 'payment/super_payment_gateway/' .
                    ($this->config->getEnvironment() == Environment::SANDBOX ?
                    'sandbox_brand_id' : 'brand_id');

                $this->configWriter->save(
                    $configKey,
                    $brandId . '||' . $this->config->getApiKey(),
                    ScopeInterface::SCOPE_STORES,
                    $this->config->getStoreId()
                );
            }

            $this->typeList->invalidate(ConfigCacheType::TYPE_IDENTIFIER);
        }

        return $subject['result'];
    }
}
