<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use Exception;
use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class ReturnFundsService implements ApiServiceInterface
{
    /** @var CommandPoolInterface $commandPool */
    private $commandPool;

    /** @var DataObjectFactory $dataObjectFactory */
    private $dataObjectFactory;

    /** @var PaymentDataObjectFactory $paymentDataObjectFactory */
    private $paymentDataObjectFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CommandPoolInterface $commandPool,
        DataObjectFactory $dataObjectFactory,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        LoggerInterface $logger
    ) {
        $this->commandPool = $commandPool;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
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

        $payment = $subject['order']->getPayment();
        if (
            empty($payment)
            || !$payment instanceof InfoInterface
        ) {
            throw new InvalidArgumentException('Payment model should be provided');
        }

        $subject['payment'] = $this->getPaymentDataObject($payment);
        $subject['store'] = $subject['order']->getStore();

        try {
            $this->commandPool->get('order_canceled_return_funds')->execute($subject);
        } catch (Exception $e) {
            $this->logger->error('[SuperPayment] ReturnFundsService ' . $e->getMessage(), ['exception' => $e]);
            $this->logger->error('[SuperPayment] ' . $e->getTraceAsString());
            throw new ApiServiceException(__($e->getMessage()));
        }

        return $subject['result'];
    }

    private function getPaymentDataObject(InfoInterface $payment): ?PaymentDataObjectInterface
    {
        return $this->paymentDataObjectFactory->create($payment);
    }
}
