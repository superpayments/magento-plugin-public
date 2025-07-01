<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Cron\ProductSyncSendQueue;
use Superpayments\SuperPayment\Gateway\Config\Config;
use Superpayments\SuperPayment\Model\Config\Source\ProductSyncActionType;
use Superpayments\SuperPayment\Model\Config\Source\ProductSyncStatus;
use Throwable;

class ProductSaveObserver implements ObserverInterface
{
    /** @var ResourceConnection */
    private $resourceConnection;
    /** @var ScopeConfigInterface */
    private $scopeConfig;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();
            if (!$product->hasDataChanges()) {
                return $this;
            }
            $productId = (int) $product->getId();
            $associatedStoreIds = $product->getStoreIds();
            if (empty($associatedStoreIds)) {
                return $this;
            }

            $connection = $this->resourceConnection->getConnection();
            $queueTable = $connection->getTableName(ProductSyncSendQueue::DB_TABLE_PRODUCT_SYNC_QUEUE);

            foreach ($associatedStoreIds as $storeId) {
                $isEnabled = $this->scopeConfig->isSetFlag(
                    'payment/super_payment_gateway/' . Config::KEY_PRODUCT_SYNC_ENABLED,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );

                if ($isEnabled) {
                    $select = $connection->select()
                        ->from($queueTable, 'queue_id')
                        ->where('product_id = ?', $productId)
                        ->where('store_id = ?', $storeId)
                        ->where('status IN (?)', [ProductSyncStatus::PENDING, ProductSyncStatus::FAILED]);

                    $existingQueueId = $connection->fetchOne($select);
                    if (!$existingQueueId) {
                        $connection->insert($queueTable, [
                            'product_id' => $productId,
                            'store_id' => $storeId,
                            'action_type' => ProductSyncActionType::UPDATE,
                            'status' => ProductSyncStatus::PENDING,
                            'attempts' => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[SuperPayments] ProductSaveObserver: ' . $e->getMessage() . "\n" . $e->getTraceAsString()
            );
        }

        return $this;
    }
}
