<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;

class TransferFactory implements TransferFactoryInterface
{
    /** @var TransferBuilder */
    private $transferBuilder;

    public function __construct(
        TransferBuilder $transferBuilder
    ) {
        $this->transferBuilder = $transferBuilder;
    }

    /**
     * @inheritdoc
     */
    public function create(array $request)
    {
        return $this->transferBuilder
            ->setMethod($request['method'])
            ->setHeaders($this->unpackHeaders($request['headers']))
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
