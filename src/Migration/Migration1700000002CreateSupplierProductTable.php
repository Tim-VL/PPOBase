<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Migration: Create Supplier Product Mapping Table
 * 
 * This migration creates the table for mapping products to suppliers.
 * Supports many-to-many relationship (product can have multiple suppliers).
 * 
 * @package PPOBase\Migration
 */
class Migration1700000002CreateSupplierProductTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000002;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_supplier_product` (
    `id` BINARY(16) NOT NULL,
    `supplier_id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NOT NULL,
    `supplier_sku` VARCHAR(100) NULL,
    `supplier_price` DOUBLE NULL,
    `supplier_currency` VARCHAR(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    
    PRIMARY KEY (`id`),
    
    CONSTRAINT `fk.ppobase_supplier_product.supplier_id` 
        FOREIGN KEY (`supplier_id`) 
        REFERENCES `ppobase_supplier` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
        
    CONSTRAINT `fk.ppobase_supplier_product.product_id` 
        FOREIGN KEY (`product_id`) 
        REFERENCES `product` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    INDEX `idx_supplier_id` (`supplier_id`),
    INDEX `idx_product_id` (`product_id`),
    UNIQUE INDEX `uniq_supplier_product` (`supplier_id`, `product_id`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
