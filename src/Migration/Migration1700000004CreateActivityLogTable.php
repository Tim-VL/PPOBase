<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000004CreateActivityLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000004;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_activity_log` (
    `id` BINARY(16) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` VARCHAR(32) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `description` VARCHAR(500) NULL,
    `old_values` LONGTEXT NULL,
    `new_values` LONGTEXT NULL,
    `reference_number` VARCHAR(100) NULL,
    `user_id` VARCHAR(32) NULL,
    `user_name` VARCHAR(255) NULL,
    `ip_address` VARCHAR(45) NULL,
    `logged_at` DATETIME(3) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
