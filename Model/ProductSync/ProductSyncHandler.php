<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\ProductSync;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Cron\ProductSyncSendQueue;
use Superpayments\SuperPayment\Gateway\Service\ApiServiceException;
use Superpayments\SuperPayment\Gateway\Service\ProductSyncService;
use Throwable;

class ProductSyncHandler
{
    public const EVENT_NAME_UPSERT = 'ProductsUpserted';
    public const EVENT_NAME_DELETE = 'ProductsDeleted';

    /** @var ResourceConnection */
    protected $resourceConnection;
    /** @var CollectionFactory */
    protected $productCollectionFactory;
    /** @var StoreManagerInterface */
    protected $storeManager;
    /** @var ProductDataMapper */
    protected $productDataMapper;
    /** @var ProductSyncService */
    protected $productSyncService;
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        ProductDataMapper $productDataMapper,
        ProductSyncService $productSyncService,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->productDataMapper = $productDataMapper;
        $this->productSyncService = $productSyncService;
        $this->logger = $logger;
    }

    public function syncUpdatedProducts(array $productList): array
    {
        if (empty($productList)) {
            return ['success_ids' => [], 'failed_ids' => []];
        }

        $sortedByStore = [];
        foreach ($productList as $row) {
            $sortedByStore[$row['store_id']][] = $row['product_id'];
        }

        $overallSuccess = [];
        $overallFail = [];

        foreach ($sortedByStore as $storeId => $productIds) {
            try {
                $productData = $this->prepareStoreProductData((int) $storeId, $productIds);
                if (empty($productData)) {
                    $overallFail = array_merge($overallFail, $productIds);
                    continue;
                }

                $success = $this->submitProducts($productData, (int) $storeId, self::EVENT_NAME_UPSERT);
            } catch (Throwable $e) {
                $success = false;
                $this->logger->error(
                    '[SuperPayments] ProductSyncHandler::syncUpdatedProducts' . $e->getMessage() . "\n" . $e->getTraceAsString()
                );
            } finally {
                if ($success) {
                    $overallSuccess = array_merge($overallSuccess, $productIds);
                } else {
                    $overallFail = array_merge($overallFail, $productIds);
                }
            }
        }

        return [
            'success_ids' => $overallSuccess,
            'failed_ids' => $overallFail,
        ];
    }

    public function syncDeletedProducts(array $productList): array
    {
        if (empty($productList)) {
            return ['success_ids' => [], 'failed_ids' => []];
        }

        $sortedByStore = [];
        foreach ($productList as $row) {
            $sortedByStore[$row['store_id']][] = $row['product_id'];
        }

        $overallSuccess = [];
        $overallFail = [];

        foreach ($sortedByStore as $storeId => $productIds) {
            try {
                $payload = [];
                foreach ($productIds as $productId) {
                    $payload[] = $this->productDataMapper->mapDelete((int) $productId, (int) $storeId);
                }
                $success = $this->submitProducts($payload, (int) $storeId, self::EVENT_NAME_DELETE);
            } catch (Throwable $e) {
                $success = false;
                $this->logger->error(
                    '[SuperPayments] ProductSyncHandler::syncDeletedProducts' . $e->getMessage() . "\n" . $e->getTraceAsString()
                );
            } finally {
                if ($success) {
                    $overallSuccess = array_merge($overallSuccess, $productIds);
                } else {
                    $overallFail = array_merge($overallFail, $productIds);
                }
            }
        }

        return [
            'success_ids' => $overallSuccess,
            'failed_ids' => $overallFail,
        ];
    }

    protected function prepareStoreProductData(int $storeId, array $productIds): array
    {
        $this->storeManager->setCurrentStore($storeId);
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in' => $productIds]);

        $output = [];
        foreach ($collection as $product) {
            $output[] = $this->productDataMapper->mapUpsert($product, $storeId);
        }
        return $output;
    }

    protected function submitProducts(array $productData, int $storeId, string $eventName = ''): bool
    {
        try {
            $data = [
                'payload' => $productData,
                'event' => $eventName,
                'storeId' => $storeId,
            ];

            $result = $this->productSyncService->execute($data);

            if ($result) {
                $this->updateSpLastSyncedAt($productData, $storeId);
                return true;
            }
        } catch (ApiServiceException $e) {
            $this->logger->error(
                "[SuperPayments] ProductSyncHandler Non-200 response for store $storeId: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] ProductSyncHandler sending product data: ' . $e->getMessage() . "\n" . $e->getTraceAsString()
            );
        }
        return false;
    }

    protected function updateSpLastSyncedAt(array $productData, $storeId)
    {
        try {
            $rowsToInsert = [];
            $now = date('Y-m-d H:i:s');

            foreach ($productData as $pd) {
                // skip "deleted" items
                if (empty($pd['id']) || (isset($pd['status']) && $pd['status'] === 'deleted')) {
                    continue;
                }
                $rowsToInsert[] = [
                    'product_id' => (int) $pd['id'],
                    'store_id' => (int) $storeId,
                    'sp_last_synced_at' => $now,
                ];
            }

            if (empty($rowsToInsert)) {
                return;
            }

            $connection = $this->resourceConnection->getConnection();
            $statusTable = $connection->getTableName(ProductSyncSendQueue::DB_TABLE_PRODUCT_SYNC_STATUS);
            $connection->insertOnDuplicate(
                $statusTable,
                $rowsToInsert,
                ['sp_last_synced_at']
            );
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] ProductSyncHandler updateSpLastSyncedAt: ' . $e->getMessage()
            );
        }
    }
}
