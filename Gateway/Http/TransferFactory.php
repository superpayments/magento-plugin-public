<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Http;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;

class TransferFactory implements TransferFactoryInterface
{
    /** @var TransferBuilder */
    private $transferBuilder;

    /** @var ProductMetadataInterface */
    private $metadata;

    public function __construct(
        TransferBuilder $transferBuilder,
        ProductMetadataInterface $metadata
    ) {
        $this->transferBuilder = $transferBuilder;
        $this->metadata = $metadata;
    }

    /**
     * @inheritdoc
     */
    public function create(array $request)
    {
        $version = $this->metadata->getVersion();

        if (version_compare($version, '2.4.8', '<')) {
            $headers = $this->unpackHeaders($request['headers'] ?? []);
        } else {
            $headers = $request['headers'] ?? [];
        }

        return $this->transferBuilder
            ->setMethod($request['method'])
            ->setHeaders($headers)
            ->setBody($request['body'])
            ->setUri($request['url'])
            ->build();
    }

    private function unpackHeaders(?array $headers): array
    {
        $result = [];
        foreach ($headers as $header => $value) {
            $result [] = sprintf('%s: %s', $header, $value);
        }
        return $result;
    }
}
