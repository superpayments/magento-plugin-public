<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order\Payment\Interceptor;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;

class CaptureCommand implements CommandInterface
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var Config $config
     */
    private $config;

    public function __construct(
        SubjectReader $subjectReader,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $commandSubject)
    {
        $paymentData = $this->subjectReader->readPayment($commandSubject);

        /** @var Interceptor $payment */
        $payment = $paymentData->getPayment();

        /** @var OrderAdapterInterface $order */
        $order = $paymentData->getOrder();

        if ($this->config->isDebugEnabled()) {
            $this->logger->info('[SuperPayment] CaptureCommand ' . $order->getOrderIncrementId());
        }
    }
}
