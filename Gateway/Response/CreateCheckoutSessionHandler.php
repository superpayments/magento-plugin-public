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

class CreateCheckoutSessionHandler implements HandlerInterface
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
            /** @var DataObject $result */
            $result = $handlingSubject['result'];
            $data = [];

            if (isset($response['body']['checkoutSessionId'])) {
                $data['checkoutSessionId'] = $response['body']['checkoutSessionId'];
            }

            if (isset($response['body']['checkoutSessionToken'])) {
                $data['checkoutSessionToken'] = $response['body']['checkoutSessionToken'];
            }

            $result->setData($data);

            if ($this->config->isDebugEnabled()) {
                $this->logger->info('[SuperPayment] CreateCheckoutSessionHandler ' . $this->json->serialize($response['body']));
            }
        } catch (Exception $e) {
            $this->logger->error('[SuperPayment] ' . $e->getMessage(), ['exception' => $e]);
            throw new Exception(__($e->getMessage()));
        }
    }
}
