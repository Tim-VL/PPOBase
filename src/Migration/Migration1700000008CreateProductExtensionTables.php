<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000008CreateProductExtensionTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000008;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_product_extension` (
    `id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NOT NULL,
    `items_per_carton` INT NULL DEFAULT 0,
    `cartons_per_layer` INT NULL DEFAULT 0,
    `carton_length` DOUBLE NULL DEFAULT 0,
    `carton_height` DOUBLE NULL DEFAULT 0,
    `carton_width` DOUBLE NULL DEFAULT 0,
    `carton_weight_net` DOUBLE NULL DEFAULT 0,
    `carton_weight_gross` DOUBLE NULL DEFAULT 0,
    `rule_notes` LONGTEXT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_product_id` (`product_id`),
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_stock_booking` (
    `id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NOT NULL,
    `old_stock` INT NOT NULL DEFAULT 0,
    `new_stock` INT NOT NULL DEFAULT 0,
    `stock_change` INT NOT NULL DEFAULT 0,
    `reason` VARCHAR(255) NULL,
    `notes` LONGTEXT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` VARCHAR(32) NULL,
    `user_id` VARCHAR(32) NULL,
    `user_name` VARCHAR(255) NULL,
    `booked_at` DATETIME(3) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_booked_at` (`booked_at`),
    INDEX `idx_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
