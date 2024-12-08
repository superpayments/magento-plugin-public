<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Controller\Update;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\PaymentUpdate;

class Refund implements ActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var Order $order */
    private $order;

    /** @var PaymentUpdate $paymentUpdate */
    private $paymentUpdate;

    /** @var OrderRepository $orderRepository */
    private $orderRepository;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Http
     */
    private $response;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Config */
    private $config;

    /** @var Json */
    private $jsonSerializer;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    public function __construct(
        Context $context,
        PaymentUpdate $paymentUpdate,
        OrderRepository $orderRepository,
        Config $config,
        Json $jsonSerializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->request = $context->getRequest();
        $this->response = $context->getResponse();
        $this->paymentUpdate = $paymentUpdate;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    public function execute(): ResponseInterface
    {
        try {
            if ($this->config->isDebugEnabled()) {
                $this->logger->info('[SuperPayments] Webhook Hit');
            }
            $this->addHeaders();
            if ($data = $this->getRequestData()) {
                if (!$data['isDuplicateWebhook']) {
                    $this->paymentUpdate->execute($this->order, $data);
                }
                $this->response->setStatusCode(Http::STATUS_CODE_200)->setContent('OK');
            }
        } catch (Exception $e) {
            $this->logger->critical('[SuperPayments] ' . $e->getMessage(), ['exception' => $e]);
            $this->response->setStatusCode(Http::STATUS_CODE_500)->setContent('FAIL');
        }

        return $this->response;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function getRequestData(): ?array
    {
        $data = [];

        $httpSuperSignature = $this->request->getServerValue('HTTP_SUPER_SIGNATURE');
        if (empty($httpSuperSignature)) {
            $this->logger->error('[SuperPayments Webhook] Missing signature');
            return $this->unauthorizedResponse();
        } elseif ($this->config->isDebugEnabled()) {
            $this->logger->info('[SuperPayments Webhook] HSS - ' . $httpSuperSignature);
        }

        try {
            $requestBody = file_get_contents('php://input');
            $requestJsonData = $this->jsonSerializer->unserialize($requestBody);
            if ($this->config->isDebugEnabled()) {
                $this->logger->info('[SuperPayments Webhook] RB - ' . $requestBody);
                $this->logger->info('[SuperPayments Webhook] RD - ' . var_export($requestJsonData, true));
            }
        } catch (Exception $e) {
            $this->logger->error('[SuperPayments Webhook] Data decode - ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            return $this->unauthorizedResponse();
        }

        $confirmationKey = $this->config->getConfirmationKey();
        $signatureHeader = filter_var($httpSuperSignature);
        $signatureHeaderParts = explode(',', $signatureHeader);
        $timestampParts = explode(':', $signatureHeaderParts[0]);
        $signatureParts = explode(':', $signatureHeaderParts[1]);

        if (!isset($timestampParts[1])) {
            $this->logger->error('[SuperPayments Webhook] Missing signature timestamp section');
            return $this->unauthorizedResponse();
        }

        $generatedSignature = $this->generateSignature($requestBody, $timestampParts[1], $confirmationKey);

        if ($generatedSignature != $signatureParts[1]) {
            $this->logger->error('[SuperPayments Webhook] Failed signature verification');
            return $this->unauthorizedResponse();
        }

        if (!isset($requestJsonData['externalReference'])) {
            $this->logger->error('[SuperPayments Webhook] Refund Webhook missing external reference');
            return $this->unauthorizedResponse();
        }

        $data['orderIncrementId'] = $requestJsonData['externalReference'];
        $data['transactionStatus'] = $requestJsonData['transactionStatus'];
        $data['transactionId'] = $requestJsonData['transactionId'];
        $data['transactionReference'] = $requestJsonData['transactionReference'];

        if (empty($data['orderIncrementId']) || empty($data['transactionStatus'])) {
            $this->logger->error('[SuperPayments Webhook] Invalid webhook data received');
            return $this->unauthorizedResponse();
        }

        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter(OrderInterface::INCREMENT_ID, $data['orderIncrementId'])
                ->create();
            $this->order = current($this->orderRepository->getList($criteria)->getItems());
            if (!$this->order || !$this->order->getId()) {
                $this->logger->error('[SuperPayments Webhook] Not Found Order with ID ' . $data['orderIncrementId']);
                return $this->unauthorizedResponse();
            }

            $additionalInfo = $this->order->getPayment()->getAdditionalInformation();
            $data['isDuplicateWebhook'] = (
                isset($additionalInfo['refundTransactionId']) &&
                $additionalInfo['refundTransactionId'] == $data['transactionId'] &&
                $additionalInfo['refundTransactionStatus'] == PaymentUpdate::STATUS_REFUNDED
            );
            if ($data['isDuplicateWebhook']) {
                $this->logger->debug('[SuperPayments Webhook] Duplicate Webhook for ID ' . $data['orderIncrementId']);
            }
        } catch (Exception $e) {
            $this->logger->error('[SuperPayments Webhook] Error retrieving Order with ID ' . $data['orderIncrementId']);
            return $this->unauthorizedResponse();
        }

        return $data;
    }

    private function unauthorizedResponse()
    {
        $this->response->setStatusCode(Http::STATUS_CODE_400)->setContent('400');
        return null;
    }

    private function generateSignature($message, $timestamp, $secret)
    {
        $payload = $timestamp . $message;
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return $signature;
    }

    private function addHeaders(): void
    {
        $headers = $this->response->getHeaders();
        $headers->addHeaders([
            'X-Super-Platform-Type' => 'magento',
            'X-Super-Platform-Version' => $this->config->getMagentoVersion(),
            'X-Super-Plugin-Version' => $this->config->getModuleVersion(),
        ]);
    }
}
