<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Plugin;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Magento\Checkout\Controller\Index\Index;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\PaymentUpdate;

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

    /** @var ResponseInterface */
    private $response;

    /** @var RedirectInterface */
    private $redirect;

    public function __construct(
        Session $checkoutSession,
        OrderRepository $orderRepository,
        ManagerInterface $messageManager,
        Config $config,
        ResponseInterface $response,
        RedirectInterface $redirect,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->response = $response;
        $this->redirect = $redirect;
        $this->logger = $logger;
    }

    /**
     * @param Index $subject
     * @param callable $proceed
     * @return ResponseInterface
     */
    public function aroundExecute(Index $subject, callable $proceed)
    {
        try {
            if (!$this->config->isActive()) {
                return $proceed();
            }

            if ($lastSuperPaymentRedirect = $this->checkoutSession->getLastSuperPaymentRedirect()) {
                $orderId = $this->checkoutSession->getLastRealOrderId();
                $this->order = $this->checkoutSession->getLastRealOrder();
                if (!empty($lastSuperPaymentRedirect) && $lastSuperPaymentRedirect == $orderId) {
                    if ($this->isPaymentSuccessful($this->order)) {
                        $this->prepareSuccessSession($this->order);
                        $this->checkoutSession->unsLastSuperPaymentRedirect();
                        return $this->redirectToSuccess();
                    }

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

        return $proceed();
    }

    /**
     * Determine if
     * @param OrderInterface|null $order
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function isPaymentSuccessful(?OrderInterface $order): bool
    {
        if (!$order || !$order->getId()) {
            return false;
        }

        if ($order->getPayment()->getMethod() !== Config::PAYMENT_CODE) {
            return false;
        }

        if ($this->checkoutSession->getQuote()->getIsActive()) {
            return false;
        }

        $createdAt = $order->getCreatedAt();
        $utcTimezone = new DateTimeZone('UTC');
        $createdAtDateTime = $createdAt ? date_create_immutable($createdAt, $utcTimezone) : false;

        if ($createdAtDateTime === false ||
            $createdAtDateTime < new DateTimeImmutable('-1 hour', $utcTimezone)
        ) {
            return false;
        }

        if ($order->getState() === \Magento\Sales\Model\Order::STATE_PROCESSING ||
            $order->getState() === \Magento\Sales\Model\Order::STATE_COMPLETE
        ) {
            return true;
        }

        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        return isset($additionalInfo['transactionStatus']) &&
            $additionalInfo['transactionStatus'] === PaymentUpdate::STATUS_SUCCESS;
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    private function prepareSuccessSession(OrderInterface $order): void
    {
        try {
            $this->logger->info(
                '[SuperPayments] Completed checkout session recovered for completed order',
                [
                    'quote_id' => $order->getQuoteId(),
                    'order_id' => $order->getId(),
                    'increment_id' => $order->getIncrementId(),
                ]
            );
            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->unsQuoteId();
        } catch (Exception $e) {
            $this->logger->error(
                '[SuperPayments] Checkout session recovery failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * @return ResponseInterface
     */
    private function redirectToSuccess(): ResponseInterface
    {
        $path = 'checkout/onepage/success';
        $arguments = ['_secure' => $this->config->isWebsiteSecure()];

        if (!empty($this->config->getHandoffSuccessRoute())) {
            $path = $this->config->getHandoffSuccessRoute();
            if (preg_match('/^https?:\/\//i', $path)) {
                $this->response->setRedirect($path);
                return $this->response;
            }
        }

        $this->redirect->redirect($this->response, $path, $arguments);
        return $this->response;
    }
}
