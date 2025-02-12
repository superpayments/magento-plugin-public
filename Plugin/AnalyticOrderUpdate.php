<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Plugin;

use Magento\Sales\Model\ResourceModel\Order;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Service\OrderCreatedService;
use Superpayments\SuperPayment\Gateway\Service\OrderStatusChangedService;
use Throwable;

class AnalyticOrderUpdate
{
    /** @var OrderCreatedService */
    private $orderCreatedService;

    /** @var OrderStatusChangedService */
    private $orderStatusChangedService;

    /** @var Config */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        OrderCreatedService $orderCreatedService,
        OrderStatusChangedService $orderStatusChangedService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->orderCreatedService = $orderCreatedService;
        $this->orderStatusChangedService = $orderStatusChangedService;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function afterSave(Order $subject, $result, $object)
    {
        try {
            if (!$this->config->isActive()) {
                return $result;
            }

            $oldStatus = $object->getOrigData('status');
            $status = $object->getData('status');
            $data = [
                'order' => $object,
            ];

            if (empty($status) || $oldStatus == $status || !$data['order']->getIncrementId()) {
                return $result;
            }

            if (empty($oldStatus)) {
                $this->orderCreatedService->execute($data);
            } else {
                $this->orderStatusChangedService->execute($data);
            }
        } catch (Throwable $e) {
            $this->logger->warning(
                '[SuperPayments] AnalyticOrderUpdate ' . $e->getMessage() ."\n". $e->getTraceAsString()
            );
        }

        return $result;
    }
}
