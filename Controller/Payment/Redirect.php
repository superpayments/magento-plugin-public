<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;
use Superpayments\SuperPayment\Model\CheckoutSessionRepository;
use Throwable;

class Redirect implements ActionInterface, HttpGetActionInterface
{
    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var ApiServiceInterface $apiService */
    private $apiService;

    /** @var Order $order */
    private $order;

    /** @var OrderRepository $orderRepository */
    private $orderRepository;

    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @var CurrentCustomer $currentCustomer */
    private $currentCustomer;

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

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CheckoutSessionRepository
     */
    private $checkoutSessionRepository;

    public function __construct(
        Context $context,
        ApiServiceInterface $apiService,
        Session $checkoutSession,
        OrderRepository $orderRepository,
        DataObjectFactory $dataObjectFactory,
        CurrentCustomer $currentCustomer,
        Config $config,
        CheckoutSessionRepository $checkoutSessionRepository,
        LoggerInterface $logger
    ) {
        $this->apiService = $apiService;
        $this->messageManager = $context->getMessageManager();
        $this->request = $context->getRequest();
        $this->response = $context->getResponse();
        $this->redirect = $context->getRedirect();
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->currentCustomer = $currentCustomer;
        $this->config = $config;
        $this->checkoutSessionRepository = $checkoutSessionRepository;
        $this->logger = $logger;
    }

    public function execute(): ResponseInterface
    {
        try {
            $this->order = $this->checkoutSession->getLastRealOrder();
            $this->validateRequest();

            $superCheckoutSession = $this->checkoutSessionRepository->getByQuoteId(
                $this->order->getQuoteId()
            );
            $superCheckoutSessionId = $superCheckoutSession->getData('checkout_session_id');

            $this->order->setState(Order::STATE_PENDING_PAYMENT);
            $this->order->setStatus(Order::STATE_PENDING_PAYMENT);
            if ($this->order->getPayment()->getMethod() == Config::PAYMENT_CODE) {
                $this->order->setCanSendNewEmailFlag(false);
            }
            $this->orderRepository->save($this->order);

            $data = [
                'order' => $this->order,
                'payment' => $this->order->getPayment(),
                'customer' => $this->currentCustomer,
                'rewardCalculationId' => $this->order->getPayment()->getAdditionalInformation('superpaymentsOfferId'),
                'superCheckoutSessionId' => $superCheckoutSessionId,
            ];

            $response = $this->apiService->execute($data);

            if ($response->hasData('redirectUrl') && $response->getData('isSuccessful')) {
                return $this->handleSuccess($response);
            }

            return $this->handlePaymentError($response);
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('There was a problem redirecting to payment gateway. Please try again.')
            );
            if ($this->order->getId()) {
                $this->order->addCommentToStatusHistory(
                    __('Error occurred during payment redirect step: ' . $e->getMessage()),
                    Order::STATE_PENDING_PAYMENT,
                    false
                );
                $this->orderRepository->save($this->order);
            }
            $this->logger->critical($e->getMessage(), ['exception' => $e]);
            $this->logger->critical($e->getTraceAsString());
        }
        return $this->redirect('checkout/cart');
    }

    private function redirect(string $path, array $arguments = []): ResponseInterface
    {
        $this->redirect->redirect($this->response, $path, $arguments);
        return $this->response;
    }

    /**
     * @throws Exception
     */
    private function validateRequest(): void
    {
        $orderIncrementId = $this->order->getIncrementId();
        if (empty($orderIncrementId)) {
            throw new Exception('Order not found');
        }

        if ($this->order->getPayment()->getMethod() !== Config::PAYMENT_CODE) {
            throw new Exception('Invalid payment method');
        }

        $lastSuperPaymentRedirect = $this->checkoutSession->getLastSuperPaymentRedirect();

        if (!empty($lastSuperPaymentRedirect) && $lastSuperPaymentRedirect == $orderIncrementId) {
            if (!$this->order->isCanceled()) {
                $this->order->cancel();
            }
            $this->checkoutSession->restoreQuote();
            $this->checkoutSession->setLastRealOrderId($orderIncrementId);
            throw new Exception('Customer likely clicked browser Back Button at Super Payments page.');
        }

        $this->checkoutSession->unsLastSuperPaymentRedirect();
    }

    private function rememberLastSession(): void
    {
        try {
            $time = (string) time();
            $this->checkoutSession->setLastSuperPaymentSession(
                ($this->checkoutSession->getLastSuccessQuoteId() ?? '') .
                '??' .
                ($this->checkoutSession->getLastQuoteId() ?? '') .
                '??' .
                ($this->checkoutSession->getLastOrderId() ?? '') .
                '??' .
                ($this->checkoutSession->getLastRealOrderId() ?? '') .
                '??' .
                $time
            );
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }

    private function handleSuccess(DataObject $response): ResponseInterface
    {
        $this->order->getPayment()->setAdditionalInformation(
            'paymentIntentId',
            $response->getData('paymentIntentId')
        );
        $this->order->getPayment()->setAdditionalInformation(
            'superCheckoutSessionId',
            $response->getData('checkoutSessionId')
        );
        $this->orderRepository->save($this->order);

        $this->checkoutSession->setLastSuperPaymentRedirect($this->order->getIncrementId());
        $this->rememberLastSession();

        return $this->redirect($response->getData('redirectUrl'));
    }

    private function handlePaymentError(DataObject $response): ResponseInterface
    {
        try {
            $orderIncrementId = $this->order->getIncrementId();
            if (!$this->order->isCanceled()) {
                $this->order->cancel();
            }
            $this->order->addCommentToStatusHistory(
                'SuperPayment: There was a problem with taking payment. ' . $response->getData('message')
            );
            $this->orderRepository->save($this->order);
            $this->checkoutSession->restoreQuote();
            $this->checkoutSession->setLastRealOrderId($orderIncrementId);
            $this->messageManager->addErrorMessage(
                __(
                    'Payment error: %1 Please try again.',
                    $response->getData('message') ?? 'There was a problem with your payment.'
                )
            );
        } catch (Exception $e) {
            $this->logger->critical('[SuperPayments] ' . $e->getMessage(), ['exception' => $e]);
        }

        return $this->redirect('checkout/cart', ['_secure' => $this->config->isWebsiteSecure()]);
    }
}
