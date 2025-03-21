<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Total\Creditmemo;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Total\AbstractTotal;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Throwable;

class SuperDiscount extends AbstractTotal
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger, array $data = [])
    {
        parent::__construct($data);
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function collect(Creditmemo $creditMemo)
    {
        try {
            $payment = $creditMemo->getOrder()->getPayment();
            if ($payment->getMethod() !== Config::PAYMENT_CODE) {
                return $this;
            }

            $additionalInformation = $payment->getAdditionalInformation();
            if (!isset($additionalInformation['transactionAmount'])) {
                return $this;
            }
            if ($additionalInformation['grossAmount'] <= $additionalInformation['transactionAmount']) {
                return $this;
            }
            $savingsAmount = ($additionalInformation['grossAmount'] - $additionalInformation['transactionAmount']);
            if ($savingsAmount) {
                $amount = ((float) $savingsAmount) / 100;
                $creditMemo->setGrandTotal($creditMemo->getGrandTotal() - $amount);
                $creditMemo->setBaseGrandTotal($creditMemo->getBaseGrandTotal() - $amount);
            }
        } catch (Throwable $e) {
            $this->logger->error('[SuperPayment] Creditmemo Total Error: ' . $e->getMessage());
        }

        return $this;
    }
}
