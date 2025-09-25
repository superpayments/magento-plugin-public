<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Plugin;

use Exception;
use Magento\Checkout\Controller\Index\Index;
use Magento\Checkout\Model\Session;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;

class HandleRedirectBackButton
{
    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var OrderRepository $orderRepository */
    private $orderRepository;

    /** @var ManagerInterface $messageManager */
    private $messageManager;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var OrderInterface */
    private $order;

    /** @var Config */
    private $config;

    public function __construct(
        Session $checkoutSession,
        OrderRepository $orderRepository,
        ManagerInterface $messageManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function beforeExecute(Index $subject): void
    {
        try {
            if (!$this->config->isActive()) {
                return;
            }

            if ($lastSuperPaymentRedirect = $this->checkoutSession->getLastSuperPaymentRedirect()) {
                $orderId = $this->checkoutSession->getLastRealOrderId();
                $this->order = $this->checkoutSession->getLastRealOrder();
                if (!empty($lastSuperPaymentRedirect) && $lastSuperPaymentRedirect == $orderId) {
                    if (!$this->order->isCanceled()) {
                        $this->order->cancel();
                        $this->order->addCommentToStatusHistory(
                            'Customer did not successfully complete payment flow on the 3DS/Wallet redirect url (likely clicked the browser Back button), ' .
                            'sending them back to the checkout page. This incomplete order has been canceled to avoid duplicate orders.'
                        );
                        $this->orderRepository->save($this->order);
                    }
                    $this->checkoutSession->restoreQuote();
                    $this->checkoutSession->setLastRealOrderId($orderId);
                    $this->checkoutSession->unsLastSuperPaymentRedirect();
                    $this->logger->info(
                        '[SuperPayments] ' . $orderId . ' customer clicked back button on redirect url'
                    );
                }
            }
        } catch (Exception $e) {
            $this->logger->critical(
                '[SuperPayments] HandleRedirectBackButton ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
