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

class Failure implements ActionInterface, HttpGetActionInterface
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
            $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();
            $this->order = $this->checkoutSession->getLastRealOrder();
            $this->checkoutSession->unsLastSuperPaymentRedirect();
            $this->checkoutSession->unsLastSuperPaymentSession();
            if (!$this->order->getId() || $this->order->getPayment()->getMethod() !== Config::PAYMENT_CODE) {
                return $this->redirect('checkout/cart', ['_secure' => $this->config->isWebsiteSecure()]);
            }
            if (!$this->order->isCanceled()) {
                $this->order->cancel();
                $this->order->addCommentToStatusHistory(
                    'Customer has completed checkout. Super Payment was not successful. Order canceled.'
                );
            }
            $this->orderRepository->save($this->order);
            $this->checkoutSession->restoreQuote();
            $this->checkoutSession->setLastRealOrderId($lastRealOrderId);
        } catch (Exception $e) {
            $this->logger->critical('[SuperPayments] ' . $e->getMessage(), ['exception' => $e]);
        }
        $this->messageManager->addErrorMessage(
            __('There was a problem with your payment. Please try again.')
        );
        return $this->redirect('checkout/cart', ['_secure' => $this->config->isWebsiteSecure()]);
    }

    private function redirect(string $path, array $arguments = []): ResponseInterface
    {
        $this->redirect->redirect($this->response, $path, $arguments);
        return $this->response;
    }
}
