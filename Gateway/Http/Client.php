<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Http;

use Exception;
use InvalidArgumentException;
use Magento\Framework\HTTP\LaminasClient;
use Magento\Framework\HTTP\ZendClient;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Superpayments\SuperPayment\Gateway\Config\Config;

class Client implements ClientInterface
{
    public const POST = 'POST';

    /** @var ClientFactory */
    private $clientFactory;

    /** @var Config */
    private $config;

    /** @var Json */
    private $jsonSerializer;

    /** @var Logger */
    private $logger;

    public function __construct(
        ClientFactory $clientFactory,
        Config $config,
        Json $jsonSerializer,
        Logger $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $log = [];

        try {
            /** @var LaminasClient | ZendClient $client */
            $client = $this->clientFactory->create();

            $log['requestMethod'] = $transferObject->getMethod();
            $log['requestUrl'] = $transferObject->getUri();
            $log['requestHeaders'] = $transferObject->getHeaders();
            $log['requestJsonData'] = $transferObject->getBody();
            $log['timeout'] = $this->config->getGatewayTimeout();

            $client->setUri($transferObject->getUri());
            $client->setMethod($transferObject->getMethod());

            if (get_class($client) == 'Magento\Framework\HTTP\ZendClient') {
                $client->setHeaders($transferObject->getHeaders());
                if ($transferObject->getMethod() == self::POST) {
                    $client->setRawData(
                        $this->jsonSerializer->serialize($transferObject->getBody()),
                        'application/json'
                    );
                } else {
                    $client->setParameterGet($transferObject->getBody());
                }
                $client->setConfig([
                    'timeout' => $this->config->getGatewayTimeout(),
                    'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
                ]);

                $responseObject = $client->request($transferObject->getMethod());
            } else {
                $headers = $client->getRequest()->getHeaders();
                foreach ($transferObject->getHeaders() as $headerKey => $headerValue) {
                    $headers->addHeaderLine($headerKey, $headerValue);
                }

                if ($transferObject->getMethod() == self::POST) {
                    $client->setRawBody($this->jsonSerializer->serialize($transferObject->getBody()));
                    $client->setEncType('application/json');
                } else {
                    $client->setParameterGet($transferObject->getBody());
                }
                $client->setOptions([
                    'timeout' => $this->config->getGatewayTimeout(),
                    'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
                ]);
                $responseObject = $client->send();
            }

            $response = [];
            $response['body'] = $responseObject->getBody();
            if (get_class($responseObject) == 'Zend_Http_Response') {
                $response['isSuccessful'] = $responseObject->isSuccessful();
                $response['headers'] = $responseObject->getHeaders();
                $response['statusCode'] = $responseObject->getStatus();
            } else {
                $response['isSuccessful'] = $responseObject->isSuccess();
                $response['headers'] = $responseObject->getHeaders()->toArray();
                $response['statusCode'] = $responseObject->getStatusCode();
            }
            $log['responseSuccess'] = $response['isSuccessful'];
            $log['responseHeaders'] = $response['headers'];
            $log['responseStatusCode'] = $response['statusCode'];

            try {
                if (!empty($response['body'])) {
                    $bodyJson = $this->jsonSerializer->unserialize($response['body']);
                    if ($bodyJson !== null) {
                        $response['body'] = $bodyJson;
                    }
                }
            } catch (InvalidArgumentException $e) {
                $log['unserializeError'] = 'None JSON response data';
            }
            $log['responseBody'] = $response['body'];

            $client->resetParameters(true);
            return $response;
        } catch (Exception $e) {
            $log['exceptionMessage'] = $e->getMessage();
            $log['traceAsString'] = $e->getTraceAsString();
            throw new ClientException(
                __($e->getMessage()),
                $e
            );
        } finally {
            $this->logger->debug($log);
        }
    }
}
