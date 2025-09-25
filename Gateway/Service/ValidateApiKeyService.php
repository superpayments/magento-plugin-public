<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Http\Client;
use Superpayments\SuperPayment\Gateway\Http\TransferFactory;

class ValidateApiKeyService
{
    /** @var Client */
    private $client;

    /** @var TransferFactory */
    private $transferFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Client $client,
        TransferFactory $transferFactory,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->transferFactory = $transferFactory;
        $this->logger = $logger;
    }

    public function execute(array $quote): array
    {
        try {
            $transferObject = $this->transferFactory->create($quote);
            $response = $this->client->placeRequest($transferObject);
        } catch (Exception $e) {
            // Handle the exception appropriately
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $response = ['result' => 'error', 'exception' => $e->getMessage()];
        }

        return $response;
    }
}
