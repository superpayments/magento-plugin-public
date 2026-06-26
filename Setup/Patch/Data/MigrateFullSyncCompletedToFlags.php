<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Superpayments\SuperPayment\Gateway\Config\Config;

/**
 * Migrate product full sync completion status from core_config_data to flag table.
 *
 *
 *
 * This patch migrates the full sync completion flag from the config table
 * to the flag table to avoid cache flushing requirements.
 */
class MigrateFullSyncCompletedToFlags implements DataPatchInterface
{
    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var FlagManager */
    private $flagManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        FlagManager $flagManager,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->flagManager = $flagManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $configTable = $connection->getTableName('core_config_data');

            // Find all stores that have completed sync in old format
            $select = $connection->select()
                ->from($configTable, ['scope_id', 'value'])
                ->where('path = ?', 'payment/super_payment_gateway/' . Config::KEY_PRODUCT_FULL_SYNC_COMPLETED)
                ->where('scope = ?', 'stores')
                ->where('value = ?', '1');

            $results = $connection->fetchAll($select);

            if (empty($results)) {
                $this->logger->info('[SuperPayments] No full sync completion flags found to migrate.');
                return $this;
            }

            $migratedCount = 0;
            foreach ($results as $row) {
                $storeId = (int) $row['scope_id'];
                $flagCode = 'super_payment_gateway/store_' . $storeId . '/' . Config::KEY_PRODUCT_FULL_SYNC_COMPLETED;

                // Save to flag table
                $this->flagManager->saveFlag($flagCode, true);
                $migratedCount++;

                $this->logger->info(
                    "[SuperPayments] Migrated full sync completion flag for store $storeId to flag table."
                );
            }

            // Clean up old config entries after successful migration
            $deletedRows = $connection->delete(
                $configTable,
                [
                    'path = ?' => 'payment/super_payment_gateway/' . Config::KEY_PRODUCT_FULL_SYNC_COMPLETED,
                    'scope = ?' => 'stores'
                ]
            );

            $this->logger->info(
                "[SuperPayments] Migration complete: Migrated $migratedCount flag(s), deleted $deletedRows old config row(s)."
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                '[SuperPayments] Error migrating full sync completion flags: ' . $e->getMessage()
            );
            // Don't throw - allow setup:upgrade to continue
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
