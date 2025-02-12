<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Request;

use Magento\Sales\Api\Data\OrderInterface;
use Superpayments\SuperPayment\Model\Config\Source\Environment;

class ReturnFundsDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['store'])) {
            $paymentData = $this->subjectReader->readPayment($buildSubject);
            $buildSubject['store'] = $paymentData->getPayment()->getOrder()->getStore();
        }

        return parent::build($buildSubject);
    }

    protected function getUrl(array $buildSubject): string
    {
        return $this->config->getUrl() . self::ENDPOINT_REFUNDS;
    }

    protected function getMethod(array $buildSubject): string
    {
        return self::HTTP_POST;
    }

    protected function getBody(array $buildSubject): ?array
    {
        $paymentData = $this->subjectReader->readPayment($buildSubject);
        /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
        $payment = $paymentData->getPayment();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $transactionAmount = (int) $payment->getAdditionalInformation('transactionAmount');
        $grossAmount = (int) $payment->getAdditionalInformation('grossAmount');

        $amount = $transactionAmount ?? $this->priceConverter->minorUnitAmount($order->getGrandTotal());
        if ($amount > $grossAmount) {
            $amount = $grossAmount;
        }

        $transactionId = $payment->getAdditionalInformation('transactionId') ?? $payment->getTransactionId();
        $currency = $order->getOrderCurrency()->getCode();

        return [
            'transactionId' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'externalReference' => $order->getIncrementId(),
            'brandId' => $this->getBrandId(),
            'test' => ($this->config->getEnvironment() == Environment::SANDBOX),
        ];
    }
}
