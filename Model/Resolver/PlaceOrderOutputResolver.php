<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;

class PlaceOrderOutputResolver implements ResolverInterface
{
    /** @var ApiServiceInterface $apiService */
    private $apiService;

    /** @var OrderRepositoryInterface $orderRepository */
    private $orderRepository;

    /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
    private $searchCriteriaBuilder;

    public function __construct(
        ApiServiceInterface $apiService,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->apiService = $apiService;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
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
        $orderArr = $value['order'] ?? null;
        if (empty($orderArr)) {
            return null;
        }

        $orderIncrementId = $value['order']['order_number'] ?? null;
        if (empty($orderIncrementId)) {
            return null;
        }

        $superCheckoutSessionId = $value['super_checkout_session_id'] ?? null;
        if (empty($superCheckoutSessionId)) {
            return null;
        }

        $order = $this->getOrder((string) $orderIncrementId);
        if ($order === null) {
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

    private function getOrder(string $orderIncrementId): ?OrderInterface
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderIncrementId, 'eq')
                ->create();

            $orderList = $this->orderRepository->getList($searchCriteria);
            $items = $orderList->getItems();

            return !empty($items) ? reset($items) : null;
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
