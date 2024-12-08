<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Response;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;

class RefundHandler implements HandlerInterface
{
    /** @var Config $config */
    private $config;

    /** @var Json $json */
    private $json;

    /** @var Logger */
    private $logger;

    public function __construct(Config $config, Json $json, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        try {
            if ($this->config->isDebugEnabled()) {
                $this->logger->info('[SuperPayment] ' . $this->json->serialize($response['body']));
            }

            /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
            $paymentData = $handlingSubject['payment'];
            $payment = $paymentData->getPayment();
            if ($response['body']['transactionId']) {
                $payment->setAdditionalInformation('refundTransactionId', $response['body']['transactionId']);
                $payment->setAdditionalInformation(
                    'refundTransactionReference',
                    $response['body']['transactionReference']
                );
            }
        } catch (Exception $e) {
            $this->logger->error('[SuperPayment] ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
