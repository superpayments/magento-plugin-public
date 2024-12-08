<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;

class RedirectUrlResolver implements ResolverInterface
{
    /** @var OrderFactory */
    private $orderFactory;

    /** @var ApiServiceInterface $apiService */
    private $apiService;

    /** @var OrderRepository $orderRepository */
    private $orderRepository;

    public function __construct(
        OrderFactory $orderFactory,
        ApiServiceInterface $apiService,
        OrderRepository $orderRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->apiService = $apiService;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (empty($args['input']['order_number'])) {
            throw new GraphQlInputException(__('"Order number" value should be specified'));
        }

        if (empty($args['input']['super_checkout_session_id'])) {
            throw new GraphQlInputException(__('"Super checkout session_id" value should be specified'));
        }

        $orderIncrementId = $args['input']['order_number'] ?? null;
        if (empty($orderIncrementId)) {
            return null;
        }

        $order = $this->getOrder((string) $orderIncrementId);
        if (is_null($order)) {
            return null;
        }

        $superCheckoutSessionId = $args['input']['super_checkout_session_id'] ?? null;
        if (empty($superCheckoutSessionId)) {
            return null;
        }

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        if ($order->getPayment()->getMethod() == Config::PAYMENT_CODE) {
            $order->setCanSendNewEmailFlag(false);
        }
        $this->orderRepository->save($order);

        $data = [
            'order' => $order,
            'payment' => $order->getPayment(),
            'rewardCalculationId' => $order->getPayment()->getAdditionalInformation('superpaymentsOfferId'),
            'superCheckoutSessionId' => $superCheckoutSessionId,
        ];

        $response = $this->apiService->execute($data);

        $order->getPayment()->setAdditionalInformation(
            'paymentIntentId',
            $response->getData('transactionId')
        );
        $order->getPayment()->setAdditionalInformation(
            'superCheckoutSessionId',
            $response->getData('checkoutSessionId')
        );
        $this->orderRepository->save($order);

        return ['redirect_url' => $response->getData('redirectUrl')];
    }

    protected function getOrder(string $orderIncrementId): ?OrderInterface
    {
        try {
            return $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
