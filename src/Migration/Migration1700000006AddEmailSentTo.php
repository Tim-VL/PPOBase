<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds email_sent_to column to ppobase_purchase_order table
 * to track which supplier email address the PO was sent to.
 */
class Migration1700000006AddEmailSentTo extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000006;
    }

    public function update(Connection $connection): void
    {
        $columnExists = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_NAME = 'ppobase_purchase_order'
             AND COLUMN_NAME = 'email_sent_to'
             AND TABLE_SCHEMA = DATABASE()"
        );

        if ((int) $columnExists === 0) {
            $connection->executeStatement(
                'ALTER TABLE `ppobase_purchase_order`
                 ADD COLUMN `email_sent_to` VARCHAR(255) NULL AFTER `email_sent_at`'
            );
        }
    }
}
