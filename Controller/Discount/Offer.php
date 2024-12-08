<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Controller\Discount;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceInterface;

class Offer implements ActionInterface, HttpGetActionInterface, CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /** @var Session $checkoutSession */
    private $checkoutSession;

    /** @var ApiServiceInterface $apiService */
    private $apiService;

    /** @var CartInterface $quote */
    private $quote;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context $context,
        ApiServiceInterface $apiService,
        Session $checkoutSession,
        JsonFactory $jsonResultFactory,
        LoggerInterface $logger
    ) {
        $this->apiService = $apiService;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->request = $context->getRequest();
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonResultFactory->create();

        try {
            $this->quote = $this->checkoutSession->getQuote();

            $data = [
                'quote' => $this->quote,
            ];

            $response = $this->apiService->execute($data);

            $content = $response->getData();
            $json = ['result' => 'success'];
            if (is_array($content)) {
                $json = array_merge($json, $content);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $json = ['result' => 'error', 'exception' => $e->getMessage()];
        }
        $result->setData($json);
        return $result;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
