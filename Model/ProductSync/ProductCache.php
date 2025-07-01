<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\ProductSync;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductCache
{
    /** @var ProductRepositoryInterface */
    private $productRepository;
    /** @var LoggerInterface */
    private $logger;
    /** @var array */
    private $cache = [];

    public function __construct(
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    public function getProductData(int $productId, int $storeId): ?array
    {
        if (isset($this->cache[$productId])) {
            return $this->cache[$productId];
        }

        try {
            $parent = $this->productRepository->getById($productId, false, $storeId);
            $data = [
                'id' => $productId,
                'sku' => $parent->getSku(),
                'url' => $parent->getUrlModel()->getUrl($parent, ['_store' => $storeId]),
            ];
            $this->cache[$productId] = $data;
            return $data;
        } catch (Throwable $e) {
            $this->logger->error(
                "[SuperPayments] ProductCache failed to load category $productId: " . $e->getMessage()
            );
        }
        return null;
    }
}
