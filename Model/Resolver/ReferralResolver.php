<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Resolver;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;

class ReferralResolver implements ResolverInterface
{
    /** @var OrderFactory */
    private $orderFactory;

    /** @var ApiServiceInterface */
    private $apiService;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        OrderFactory $orderFactory,
        ApiServiceInterface $apiService,
        LoggerInterface $logger
    ) {
        $this->orderFactory = $orderFactory;
        $this->apiService = $apiService;
        $this->logger = $logger;
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
            throw new GraphQlInputException(__('"Order Number" value should be specified'));
        }

        $orderIncrementId = $args['input']['order_number'] ?? null;
        $result = ['content' => ''];

        try {
            $order = $this->getOrder((string) $orderIncrementId);
            if (is_null($order) || $order->getPayment()->getMethod() !== Config::PAYMENT_CODE) {
                return $result;
            }
            $data = [
                'order' => $order,
            ];
            $response = $this->apiService->execute($data);
            $data = $response->getData();

            if (is_array($data)) {
                $result = array_merge($result, $data);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
        return ['content' => $result['content']];
    }

    private function getOrder(string $orderIncrementId): ?OrderInterface
    {
        try {
            return $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
