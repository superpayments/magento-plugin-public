<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Controller\Callback;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Throwable;

class Success implements ActionInterface, HttpGetActionInterface
{
    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var Order $order */
    private $order;

    /** @var OrderRepository $orderRepository */
    private $orderRepository;

    /**
     * @var MessageManagerInterface
     */
    private $messageManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Config */
    private $config;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderRepository $orderRepository,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->messageManager = $context->getMessageManager();
        $this->request = $context->getRequest();
        $this->response = $context->getResponse();
        $this->redirect = $context->getRedirect();
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(): ResponseInterface
    {
        try {
            if ($this->isSecondRedirect()) {
                return $this->redirect();
            }

            $this->order = $this->checkoutSession->getLastRealOrder();
            $this->checkoutSession->unsLastSuperPaymentRedirect();

            if (
                !$this->checkoutSession->getLastSuccessQuoteId() ||
                !$this->order->getId() ||
                $this->order->getPayment()->getMethod() !== Config::PAYMENT_CODE
            ) {
                return $this->redirect();
            }

            if ($this->config->isDebugEnabled()) {
                $this->logger->info(
                    '[SuperPayments] Success callback page. ' .
                    'Order State: ' . $this->order->getState() . ' ' .
                    'Order Status: ' . $this->order->getState()
                );
            }

            if (
                $this->order->getState() == Order::STATE_PENDING_PAYMENT
                || $this->order->getStatus() == Order::STATE_PENDING_PAYMENT
            ) {
                $this->order->addCommentToStatusHistory(
                    __('Customer has returned to success callback page. '
                        . 'Payment is delayed or Webhook not received.')
                );
            } elseif (
                $this->order->getState() == Order::STATE_CANCELED
                || $this->order->getStatus() == Order::STATE_CANCELED
            ) {
                $this->order->addCommentToStatusHistory(
                    __('Customer has returned to success callback page. '
                        . 'Order is in a canceled state.')
                );
            } else {
                $this->order->addCommentToStatusHistory(
                    'Customer has completed checkout. Payment successful. Redirecting to confirmation/handoff page.'
                );
            }
            $this->orderRepository->save($this->order);
            $this->checkoutSession->setLastQuoteId($this->order->getQuoteId());
            $this->checkoutSession->unsQuoteId();
        } catch (Exception $e) {
            $this->logger->critical('[SuperPayments] ' . $e->getMessage(), ['exception' => $e]);
        }

        return $this->redirect();
    }

    private function redirect(): ResponseInterface
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

    private function isSecondRedirect(): bool
    {
        try {
            if (
                $this->checkoutSession->getLastSuccessQuoteId() &&
                $this->checkoutSession->getLastQuoteId() &&
                $this->checkoutSession->getLastOrderId() &&
                $this->checkoutSession->getLastRealOrderId()
            ) {
                return false;
            }

            $data = $this->checkoutSession->getLastSuperPaymentSession();
            if (empty($data)) {
                return false;
            }

            $sessionIds = explode('??', $data);
            if (empty($sessionIds) || !is_array($sessionIds) || count($sessionIds) != 5) {
                return false;
            }

            $interval = time() - ((int) $sessionIds[4]);
            if ($interval > 600) {
                $this->checkoutSession->unsLastSuperPaymentSession();
                return false;
            }

            $orderIncrementId = $this->request->getParam('ref');
            if ($orderIncrementId != $sessionIds[3]) {
                return false;
            }

            $this->checkoutSession->setLastSuccessQuoteId($sessionIds[0]);
            $this->checkoutSession->setLastQuoteId($sessionIds[1]);
            $this->checkoutSession->setLastOrderId($sessionIds[2]);
            $this->checkoutSession->setLastRealOrderId($sessionIds[3]);
            return true;
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return false;
    }
}
