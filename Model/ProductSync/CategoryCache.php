<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\ProductSync;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class CategoryCache
{
    /** @var CategoryRepositoryInterface */
    private $categoryRepository;
    /** @var LoggerInterface */
    private $logger;
    /** @var array */
    private $cache = [];

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
    }

    public function getCategoryData(int $categoryId): ?array
    {
        if (isset($this->cache[$categoryId])) {
            return $this->cache[$categoryId];
        }

        try {
            $category = $this->categoryRepository->get($categoryId);
            $data = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getUrlKey(),
            ];
            $this->cache[$categoryId] = $data;
            return $data;
        } catch (Throwable $e) {
            $this->logger->error(
                "[SuperPayments] CategoryCache failed to load category $categoryId: " . $e->getMessage()
            );
        }
        return null;
    }
}
