<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use Exception;
use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class ExpireOfferService implements ApiServiceInterface
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

        if (!isset($subject['reward_calculation_id'])) {
            throw new InvalidArgumentException('reward_calculation_id should be provided');
        }

        if (
            !isset($subject['store'])
            || !$subject['store'] instanceof StoreInterface
        ) {
            throw new InvalidArgumentException('Store data object should be provided');
        }

        try {
            $this->commandPool->get('expire_offer')->execute($subject);
        } catch (Exception $e) {
            $this->logger->error('[SuperPayment] ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
            throw new ApiServiceException(__('SuperPayments error on expire offer. Please try again later.'));
        }

        return $subject['result'];
    }
}
