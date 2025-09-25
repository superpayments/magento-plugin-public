<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config as SuperPaymentsConfig;
use Throwable;

class SaveOfferId implements ObserverInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var Order */
    private $orderRepository;

    /** @var SuperPaymentsConfig */
    private $config;

    public function __construct(
        Session $checkoutSession,
        SuperPaymentsConfig $config,
        LoggerInterface $logger,
        Order $orderRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->config = $config;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->config->isActive()) {
                return $this;
            }
            $order = $observer->getEvent()->getOrder();
            $offerid = $this->checkoutSession->getLastCashbackOfferId();
            $order->setOfferId($offerid);

            return $this;
        } catch (Throwable $e) {
            $this->logger->error(
                '[Superpayments] SaveOfferId ' . $e->getMessage() . "\n" . $e->getTraceAsString()
            );
        }
    }
}
