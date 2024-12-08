<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Validator;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;

class ResponseValidator implements ValidatorInterface
{
    /** @var Config $config */
    private $config;

    /** @var Logger */
    private $logger;

    /** @var Json */
    private $json;

    /**
     * @var ResultInterfaceFactory
     */
    private $resultInterfaceFactory;

    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Config $config,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->resultInterfaceFactory = $resultFactory;
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $response = $validationSubject['response'];
        $isValid = $response['isSuccessful'];
        $errorMessages = [];
        $errorCodes = [];

        if (!$isValid) {
            $errorCodes = (is_array($response['body']) && isset($response['body']['statusCode']))
                ? [$response['body']['statusCode']] : [$response['statusCode']];
            $errorMessages = (is_array($response['body']) && isset($response['body']['errorMessage']))
                ? explode("\n", $response['body']['errorMessage']) : [];
        }

        $result = [
            'isValid' => $isValid,
            'failsDescription' => $errorMessages,
            'errorCodes' => $errorCodes,
        ];

        if (!$isValid && $this->config->isDebugEnabled()) {
            $this->logger->info('[SuperPayment] ResponseValidator ' . $this->json->serialize($result));
        }

        return $this->resultInterfaceFactory->create($result);
    }
}
