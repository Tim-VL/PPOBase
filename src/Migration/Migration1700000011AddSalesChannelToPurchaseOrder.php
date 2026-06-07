<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000011AddSalesChannelToPurchaseOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000011;
    }

    public function update(Connection $connection): void
    {
        $columnExists = (bool) $connection->fetchOne(
            'SHOW COLUMNS FROM `ppobase_purchase_order` LIKE :column',
            ['column' => 'sales_channel_id']
        );

        if (!$columnExists) {
            $connection->executeStatement(
                'ALTER TABLE `ppobase_purchase_order`
                    ADD COLUMN `sales_channel_id` BINARY(16) NULL AFTER `supplier_id`,
                    ADD INDEX `idx_sales_channel_id` (`sales_channel_id`)'
            );
        }

        $foreignKeyExists = (bool) $connection->fetchOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tableName
               AND COLUMN_NAME = :columnName
               AND REFERENCED_TABLE_NAME = :referencedTableName',
            [
                'tableName' => 'ppobase_purchase_order',
                'columnName' => 'sales_channel_id',
                'referencedTableName' => 'sales_channel',
            ]
        );

        if (!$foreignKeyExists) {
            $connection->executeStatement(
                'ALTER TABLE `ppobase_purchase_order`
                    ADD CONSTRAINT `fk.ppobase_purchase_order.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
