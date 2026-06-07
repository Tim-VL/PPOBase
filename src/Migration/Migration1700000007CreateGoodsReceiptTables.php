<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000007CreateGoodsReceiptTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000007;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_goods_receipt` (
    `id` BINARY(16) NOT NULL,
    `receipt_number` VARCHAR(50) NOT NULL,
    `purchase_order_id` BINARY(16) NOT NULL,
    `supplier_id` BINARY(16) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
    `receipt_date` DATETIME(3) NULL,
    `invoice_reference` VARCHAR(255) NULL,
    `notes` LONGTEXT NULL,
    `created_by` VARCHAR(255) NULL,
    `updated_by` VARCHAR(255) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_receipt_number` (`receipt_number`),

    CONSTRAINT `fk.ppobase_goods_receipt.purchase_order_id`
        FOREIGN KEY (`purchase_order_id`)
        REFERENCES `ppobase_purchase_order` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT `fk.ppobase_goods_receipt.supplier_id`
        FOREIGN KEY (`supplier_id`)
        REFERENCES `ppobase_supplier` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX `idx_purchase_order_id` (`purchase_order_id`),
    INDEX `idx_supplier_id` (`supplier_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_receipt_date` (`receipt_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_goods_receipt_item` (
    `id` BINARY(16) NOT NULL,
    `goods_receipt_id` BINARY(16) NOT NULL,
    `purchase_order_item_id` BINARY(16) NULL,
    `product_id` BINARY(16) NULL,
    `product_number` VARCHAR(100) NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `ean` VARCHAR(100) NULL,
    `mpn` VARCHAR(100) NULL,
    `quantity_expected` INT NULL DEFAULT 0,
    `quantity_received` INT NOT NULL DEFAULT 0,
    `quantity_rejected` INT NULL DEFAULT 0,
    `unit_price` DOUBLE NULL DEFAULT 0,
    `booked_price` DOUBLE NULL DEFAULT 0,
    `line_total` DOUBLE NULL DEFAULT 0,
    `notes` LONGTEXT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk.ppobase_goods_receipt_item.goods_receipt_id`
        FOREIGN KEY (`goods_receipt_id`)
        REFERENCES `ppobase_goods_receipt` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk.ppobase_goods_receipt_item.purchase_order_item_id`
        FOREIGN KEY (`purchase_order_item_id`)
        REFERENCES `ppobase_purchase_order_item` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,

    INDEX `idx_goods_receipt_id` (`goods_receipt_id`),
    INDEX `idx_purchase_order_item_id` (`purchase_order_item_id`),
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
