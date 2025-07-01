<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductSyncService implements ApiServiceInterface
{
    /** @var CommandPoolInterface $commandPool */
    private $commandPool;

    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @var StoreManagerInterface $storeManager */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CommandPoolInterface $commandPool,
        DataObjectFactory $dataObjectFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->commandPool = $commandPool;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->storeManager = $storeManager;
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

        if (!isset($subject['event'])) {
            throw new InvalidArgumentException('Event name should be provided');
        }

        if (!isset($subject['payload'])) {
            throw new InvalidArgumentException('Payload data should be provided');
        }

        if (!isset($subject['storeId'])) {
            throw new InvalidArgumentException('Store object should be provided');
        }
        $subject['store'] = $this->storeManager->getStore($subject['storeId']);

        try {
            $this->commandPool->get('product_sync')->execute($subject);
        } catch (Throwable $e) {
            $this->logger->error('[SuperPayment] ProductSyncService ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
            throw new ApiServiceException(__($e->getMessage()));
        }

        return $subject['result'];
    }
}
