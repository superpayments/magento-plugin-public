<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Controller\Adminhtml\System\Config;

use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Gateway\Request\AbstractDataBuilder;
use Superpayments\SuperPayment\Gateway\Service\ValidateApiKeyService;

class Validate implements ActionInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ValidateApiKeyService
     */
    private $validate;

    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        Context $context,
        ValidateApiKeyService $validate,
        Config $config,
        JsonFactory $jsonResultFactory,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->validate = $validate;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->logger = $logger;
        $this->request = $context->getRequest();
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonResultFactory->create();
        $storeId = (int) $this->request->getParam('storeId', 0);
        $this->config->setStoreId($storeId);

        try {
            // Create the dummy quote object
            $quote = [
                'method' => AbstractDataBuilder::HTTP_POST,
                'body' => [
                    'cart' => [
                        'items' => [
                            [
                                'name' => 'Validation Link',
                                'url' => 'https://superpayments.com',
                                'quantity' => 1,
                                'minorUnitAmount' => 100,
                            ],
                        ],
                        'id' => 'cart101',
                    ],
                    'grossAmount' => 10,
                ],
                'url' => $this->config->getUrlV3() . AbstractDataBuilder::ENDPOINT_OFFERS,
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'version' => $this->config->getModuleVersion(),
                    'magento-version' => $this->config->getMagentoVersion(),
                    'magento-edition' => $this->config->getMagentoEdition(),
                    'checkout-api-key' => $this->config->getApiKey(),
                ],
            ];

            $response = $this->validate->execute($quote);
        } catch (Exception $e) {
            $this->logger->error('SuperPayments Validate Credentials: ' . $e->getMessage(), ['exception' => $e]);
        }
        $result->setData($response);
        return $result;
    }
}
