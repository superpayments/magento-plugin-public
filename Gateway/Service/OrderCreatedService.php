<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class OrderCreatedService implements ApiServiceInterface
{
    /** @var CommandPoolInterface $commandPool */
    private $commandPool;

    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CommandPoolInterface $commandPool,
        DataObjectFactory $dataObjectFactory,
        LoggerInterface $logger
    ) {
        $this->commandPool = $commandPool;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->logger = $logger;
    }

    /**
     * @param array $subject
     * @throws InvalidArgumentException
     * @throws ApiServiceException
     */
    public function execute(array $subject): DataObject
    {
        $subject['result'] = $this->dataObjectFactory->create();

        if (
            !isset($subject['order'])
            || !$subject['order'] instanceof OrderInterface
        ) {
            throw new InvalidArgumentException('Order data object should be provided');
        }

        if (!$subject['order']->getIncrementId()) {
            throw new InvalidArgumentException('Invalid order data object provided');
        }

        $subject['store'] = $subject['order']->getStore();

        try {
            $this->commandPool->get('order_created')->execute($subject);
        } catch (Throwable $e) {
            $this->logger->error('[SuperPayment] OrderCreatedService ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
            throw new ApiServiceException(__($e->getMessage()));
        }

        return $subject['result'];
    }
}
