<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\FlagManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\Config\Source\ProductSyncActionType;
use Superpayments\SuperPayment\Model\Config\Source\ProductSyncStatus;
use Throwable;

class ProductSyncFullSync
{
    public const INSERT_BATCH_SIZE = 50;

    /** @var FlagManager */
    private $flagManager;
    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var StoreManagerInterface */
    private $storeManager;
    /** @var ProductCollectionFactory */
    private $productCollectionFactory;
    /** @var ResourceConnection */
    private $resourceConnection;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        FlagManager $flagManager,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ProductCollectionFactory $productCollectionFactory,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->flagManager = $flagManager;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            $this->logger->debug('[SuperPayments] FullSync Cron Start.');

            $connection = $this->resourceConnection->getConnection();
            $queueTable = $connection->getTableName(ProductSyncSendQueue::DB_TABLE_PRODUCT_SYNC_QUEUE);

            foreach ($this->storeManager->getStores() as $store) {
                $storeId = (int) $store->getId();

                $enabled = $this->scopeConfig->isSetFlag(
                    'payment/super_payment_gateway/' . Config::KEY_PRODUCT_SYNC_ENABLED,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                $completed = $this->flagManager->getFlagData(
                    'super_payment_gateway/store_' . $storeId . '/' . Config::KEY_PRODUCT_FULL_SYNC_COMPLETED
                );

                if (!$enabled || $completed) {
                    continue;
                }

                $collection = $this->productCollectionFactory->create();
                $collection->setStoreId($storeId)
                    ->addAttributeToSelect(['entity_id'])
                    ->addStoreFilter($storeId);

                $storeProductIds = $collection->getAllIds();
                $count = count($storeProductIds);

                if ($count > 0) {
                    $this->logger->info(
                        "[SuperPayments] Product Full Sync: Found $count products for store $storeId. Queuing now..."
                    );

                    $batchData = [];
                    $processed = 0;

                    foreach ($storeProductIds as $pid) {
                        $batchData[] = [
                            'product_id' => $pid,
                            'store_id' => $storeId,
                            'action_type' => ProductSyncActionType::UPDATE,
                            'status' => ProductSyncStatus::PENDING,
                            'attempts' => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        $processed++;

                        if ($processed % self::INSERT_BATCH_SIZE === 0) {
                            $this->bulkInsert($connection, $queueTable, $batchData);
                            $batchData = [];
                        }
                    }

                    if (!empty($batchData)) {
                        $this->bulkInsert($connection, $queueTable, $batchData);
                    }

                    $this->logger->info(
                        "[SuperPayments] Product Full Sync: Successfully queued $processed products for store $storeId."
                    );
                }

                // Mark full sync as completed for this store
                $this->flagManager->saveFlag(
                    'super_payment_gateway/store_' . $storeId . '/' . Config::KEY_PRODUCT_FULL_SYNC_COMPLETED,
                    true
                );
            }

            $this->logger->debug('[SuperPayments] FullSync Cron End.');
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] Error in Product Full Sync cron: ' . $e->getMessage() . "\n" . $e->getTraceAsString()
            );
        }
    }

    /**
     * Perform a bulk insert for the given rows.
     * Each row is an associative array with keys matching table columns.
     *
     * @param AdapterInterface $connection
     * @param string $tableName
     * @param array $rows
     * @return void
     */
    protected function bulkInsert($connection, $tableName, array $rows)
    {
        if (empty($rows)) {
            return;
        }

        $connection->insertMultiple($tableName, $rows);
    }
}
