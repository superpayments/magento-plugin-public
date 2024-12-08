<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Observer;

use Magento\Framework\App\ScopeInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\PaymentConfigChangedService;
use Throwable;

class ConfigSave implements ObserverInterface
{
    /** @var PaymentConfigChangedService */
    private $paymentConfigChangedService;

    /** @var LoggerInterface */
    private $logger;

    /** @var Config */
    private $config;

    public function __construct(
        PaymentConfigChangedService $paymentConfigChangedService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->paymentConfigChangedService = $paymentConfigChangedService;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->config->isActive()) {
                return $this;
            }

            $subject = [];
            $scopeId = $observer->getEvent()->getStore();
            $scope = StoreScopeInterface::SCOPE_STORE;
            if (!$scopeId && ($scopeId = $observer->getEvent()->getWebsite())) {
                $scope = StoreScopeInterface::SCOPE_WEBSITE;
            }
            if (!$scopeId) {
                $scope = ScopeInterface::SCOPE_DEFAULT;
            }
            $subject['scope'] = $scope;
            $subject['scopeId'] = $scopeId;
            $this->paymentConfigChangedService->execute($subject);
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] ConfigSave ' . $e->getMessage() ."\n". $e->getTraceAsString()
            );
        }

        return $this;
    }
}
