<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Resolver;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;

class CallbackResolver implements ResolverInterface
{
    /** @var OrderFactory */
    private $orderFactory;

    /** @var OrderRepository $orderRepository */
    private $orderRepository;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var Config $config */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    /** @var ManagerInterface */
    private $eventManager;

    public function __construct(
        OrderFactory $orderFactory,
        OrderRepository $orderRepository,
        CartRepositoryInterface $quoteRepository,
        ManagerInterface $eventManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->eventManager = $eventManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (empty($args['input']['order_number'])) {
            throw new GraphQlInputException(__('"Order Number" value should be specified'));
        }

        if (empty($args['input']['callback_status'])) {
            throw new GraphQlInputException(__('"Callback Status" value should be specified'));
        }

        $orderIncrementId = $args['input']['order_number'] ?? null;
        $callbackStatus = $args['input']['callback_status'] ?? null;

        if (empty($orderIncrementId) || empty($callbackStatus)) {
            return ['success' => false];
        }

        $order = $this->getOrder((string) $orderIncrementId);
        if ($order === null || $order->getPayment()->getMethod() !== Config::PAYMENT_CODE) {
            return ['success' => false];
        }

        switch ($callbackStatus) {
            case 'SUCCESS':
                $this->processSuccess($order);
                break;
            case 'FAILURE':
                $this->processFailure($order);
                break;
            case 'CANCEL':
                $this->processCancel($order);
                break;
        }

        return ['success' => true];
    }

    private function getOrder(string $orderIncrementId): ?OrderInterface
    {
        try {
            return $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    private function processSuccess(OrderInterface $order): void
    {
        if ($this->config->isDebugEnabled()) {
            $this->logger->info(
                '[SuperPayments] Success page' .
                'Order State: ' . $order->getState() .
                'Order Status: ' . $order->getState()
            );
        }

        if (
            $order->getState() == Order::STATE_PENDING_PAYMENT
            || $order->getStatus() == Order::STATE_PENDING_PAYMENT
        ) {
            $order->addCommentToStatusHistory(
                __('Customer has returned to checkout success page. '
                    . 'Payment is delayed or Webhook not received.')
            );
        } elseif (
            $order->getState() == Order::STATE_CANCELED
            || $order->getStatus() == Order::STATE_CANCELED
        ) {
            $order->addCommentToStatusHistory(
                __('Order was canceled by administrator during payment step by customer. '
                    . 'Payment is now complete.')
            );
        } else {
            $order->addCommentToStatusHistory(
                'Customer has completed checkout. Payment successful. Redirecting to confirmation page.'
            );
        }
        $this->orderRepository->save($order);
    }

    private function processFailure(OrderInterface $order): void
    {
        if (!$order->isCanceled()) {
            $order->cancel();
            $order->addCommentToStatusHistory(
                'Customer has completed checkout. Super Payment was not successful. Order canceled.'
            );
        }
        $this->orderRepository->save($order);
        $this->restoreQuote($order);
    }

    private function processCancel(OrderInterface $order): void
    {
        if (!$order->isCanceled()) {
            $order->cancel();
            $order->addCommentToStatusHistory(
                'Customer has completed checkout. Super Payment was not completed / cancel by customer.'
                . ' Order canceled.'
            );
        }
        $this->orderRepository->save($order);
        $this->restoreQuote($order);
    }

    private function restoreQuote($order): bool
    {
        if ($order->getId()) {
            try {
                $quote = $this->quoteRepository->get($order->getQuoteId());
                $quote->setIsActive(1)->setReservedOrderId(null);
                $this->quoteRepository->save($quote);
                $this->eventManager->dispatch('restore_quote', ['order' => $order, 'quote' => $quote]);
                return true;
            } catch (NoSuchEntityException $e) {
                $this->logger->critical($e);
            }
        }

        return false;
    }
}
