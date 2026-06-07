<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000003CreatePurchaseOrderTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000003;
    }

    public function update(Connection $connection): void
    {
        // Create Purchase Order table
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_purchase_order` (
    `id` BINARY(16) NOT NULL,
    `po_number` VARCHAR(50) NOT NULL,
    `supplier_id` BINARY(16) NOT NULL,
    `sales_channel_id` BINARY(16) NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
    `order_date` DATETIME(3) NULL,
    `expected_delivery_date` DATETIME(3) NULL,
    `currency` VARCHAR(3) NULL,
    `subtotal` DOUBLE NULL DEFAULT 0,
    `tax_amount` DOUBLE NULL DEFAULT 0,
    `total` DOUBLE NULL DEFAULT 0,
    `item_count` INT NULL DEFAULT 0,
    `notes` LONGTEXT NULL,
    `internal_reference` VARCHAR(100) NULL,
    `shipping_address` LONGTEXT NULL,
    `email_sent` TINYINT(1) NULL DEFAULT 0,
    `email_sent_at` DATETIME(3) NULL,
    `created_by` VARCHAR(255) NULL,
    `updated_by` VARCHAR(255) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_po_number` (`po_number`),
    
    CONSTRAINT `fk.ppobase_purchase_order.supplier_id`
        FOREIGN KEY (`supplier_id`)
        REFERENCES `ppobase_supplier` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk.ppobase_purchase_order.sales_channel_id`
        FOREIGN KEY (`sales_channel_id`)
        REFERENCES `sales_channel` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
        
    INDEX `idx_supplier_id` (`supplier_id`),
    INDEX `idx_sales_channel_id` (`sales_channel_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_order_date` (`order_date`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);

        // Create Purchase Order Item table
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_purchase_order_item` (
    `id` BINARY(16) NOT NULL,
    `purchase_order_id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NULL,
    `line_number` INT NULL,
    `product_number` VARCHAR(100) NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `supplier_sku` VARCHAR(100) NULL,
    `ean` VARCHAR(100) NULL,
    `mpn` VARCHAR(100) NULL,
    `quantity_ordered` INT NOT NULL DEFAULT 1,
    `quantity_received` INT NULL DEFAULT 0,
    `unit` VARCHAR(20) NULL DEFAULT 'pcs',
    `unit_price` DOUBLE NULL DEFAULT 0,
    `tax_rate` DOUBLE NULL DEFAULT 0,
    `tax_amount` DOUBLE NULL DEFAULT 0,
    `line_total` DOUBLE NULL DEFAULT 0,
    `discount_percent` DOUBLE NULL DEFAULT 0,
    `discount_amount` DOUBLE NULL DEFAULT 0,
    `notes` LONGTEXT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    
    PRIMARY KEY (`id`),
    
    CONSTRAINT `fk.ppobase_po_item.purchase_order_id`
        FOREIGN KEY (`purchase_order_id`)
        REFERENCES `ppobase_purchase_order` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
        
    CONSTRAINT `fk.ppobase_po_item.product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
        
    INDEX `idx_purchase_order_id` (`purchase_order_id`),
    INDEX `idx_product_id` (`product_id`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
