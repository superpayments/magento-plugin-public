<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Model\Config\Source\ProductSyncStatus;
use Superpayments\SuperPayment\Model\ProductSync\ProductSyncHandler;
use Throwable;
use Zend_Db_Expr;

class ProductSyncSendQueue
{
    public const DB_TABLE_PRODUCT_SYNC_QUEUE = 'superpayments_productsync_queue';
    public const DB_TABLE_PRODUCT_SYNC_STATUS = 'superpayments_productsync_status';

    public const BATCH_SIZE = 20;
    public const MAX_RUN_TIME_SECONDS = 55;
    public const MAX_ATTEMPTS = 7;

    /** @var ResourceConnection */
    private $resourceConnection;
    /** @var ProductSyncHandler */
    private $productSyncHandler;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        ProductSyncHandler $productSyncHandler,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productSyncHandler = $productSyncHandler;
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            $this->logger->debug('[SuperPayments] SendQueue Cron Start.');

            $startTime = time();
            $connection = $this->resourceConnection->getConnection();
            $queueTable = $connection->getTableName(self::DB_TABLE_PRODUCT_SYNC_QUEUE);

            while (true) {
                if ((time() - $startTime) >= self::MAX_RUN_TIME_SECONDS) {
                    $this->logger->debug(
                        '[SuperPayments] SendQueue: Time limit reached (50s). Stopping processing.'
                    );
                    break;
                }

                $select = $connection->select()
                    ->from($queueTable)
                    ->where('status = ?', ProductSyncStatus::PENDING)
                    ->where('attempts < ?', self::MAX_ATTEMPTS)
                    ->order('attempts ASC')
                    ->order('created_at ASC')
                    ->limit(self::BATCH_SIZE);

                $rows = $connection->fetchAll($select);
                if (empty($rows)) {
                    $this->logger->debug('[SuperPayments] SendQueue: No pending items found. Done.');
                    break;
                }

                $queueIds = [];
                foreach ($rows as $r) {
                    $queueIds[] = $r['queue_id'];
                }
                $connection->update(
                    $queueTable,
                    [
                        'status' => 'processing',
                        'attempts' => new Zend_Db_Expr('attempts + 1'),
                    ],
                    ['queue_id IN (?)' => $queueIds]
                );

                $updates = [];
                $deletions = [];
                foreach ($rows as $r) {
                    $item = [
                        'product_id' => (int) $r['product_id'],
                        'store_id' => (int) $r['store_id'],
                        'queue_id' => (int) $r['queue_id'],
                    ];
                    if ($r['action_type'] === 'delete') {
                        $deletions[] = $item;
                    } else {
                        $updates[] = $item;
                    }
                }

                if (!empty($updates)) {
                    $result = $this->productSyncHandler->syncUpdatedProducts($updates);
                    $this->handleApiResult($updates, $result);
                }

                if (!empty($deletions)) {
                    $result = $this->productSyncHandler->syncDeletedProducts($deletions);
                    $this->handleApiResult($deletions, $result);
                }
            }

            $this->logger->debug('[SuperPayments] SendQueue Cron End.');
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] Error in SendQueue cron: ' . $e->getMessage() . "\n" . $e->getTraceAsString()
            );
        }
    }

    protected function handleApiResult(array $items, array $result)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $queueTable = $connection->getTableName(self::DB_TABLE_PRODUCT_SYNC_QUEUE);

            $successQueueIds = [];
            $failedQueueIds = [];
            if (isset($result['success_ids'])) {
                $successQueueIds = $this->extractQueueIds($items, $result['success_ids']);
            }
            if (isset($result['failed_ids'])) {
                $failedQueueIds = $this->extractQueueIds($items, $result['failed_ids']);
            }

            if (!empty($successQueueIds)) {
                $connection->delete($queueTable, ['queue_id IN (?)' => $successQueueIds]);
                $this->logger->debug(
                    '[SuperPayments] SendQueue: Removed ' . count($successQueueIds) . ' successful items from queue.'
                );
            }

            if (!empty($failedQueueIds)) {
                $connection->update(
                    $queueTable,
                    ['status' => 'pending'],
                    ['queue_id IN (?)' => $failedQueueIds]
                );
                $this->logger->warning(
                    '[SuperPayments] SendQueue: Re-queued ' . count($failedQueueIds) . ' items for retry.'
                );
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] Error in SendQueue handleApiResult: ' .
                $e->getMessage() . "\n" . $e->getTraceAsString()
            );
        }
    }

    protected function extractQueueIds(array $items, array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $queueIds = [];
        $productIds = array_map('intval', $productIds);

        foreach ($items as $item) {
            if (in_array($item['product_id'], $productIds)) {
                $queueIds[] = $item['queue_id'];
            }
        }

        return $queueIds;
    }
}
