<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config as SuperPaymentsConfig;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Throwable;

class SuccessOfferExpire implements ObserverInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var ApiServiceInterface */
    private $apiService;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var SuperPaymentsConfig */
    private $config;

    public function __construct(
        Session $checkoutSession,
        SuperPaymentsConfig $config,
        LoggerInterface $logger,
        ApiServiceInterface $apiService,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->apiService = $apiService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->config->isActive()) {
                return $this;
            }
            $order = $observer->getEvent()->getOrder() ?? $observer->getEvent()->getOrders()[0];
            if ($order->getPayment()->getMethod() == SuperPaymentsConfig::PAYMENT_CODE) {
                return $this;
            }
            $this->expireOffer($order);
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] SuccessOfferExpire ' . $e->getMessage() ."\n". $e->getTraceAsString()
            );
        }
        return $this;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    private function expireOffer($order): void
    {
        try {
            $offerId = $order->getPayment()->getAdditionalInformation('superpaymentsOfferId');
            if ($offerId) {
                $data = [
                    'reward_calculation_id' => $offerId,
                    'store' => $this->storeManager->getStore($order->getStoreId()),
                ];

                $this->apiService->execute($data);
            }
        } catch (Throwable $e) {
            $this->logger->critical('[SuperPayments] expireOffer ' . $e->getMessage(), ['exception' => $e]);
            if ($this->config->isDebugEnabled()) {
                $this->logger->error($e->getTraceAsString());
            }
        }
    }
}
