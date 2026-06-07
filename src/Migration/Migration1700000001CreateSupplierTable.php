<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Migration: Create Supplier Table
 * 
 * This migration creates the main supplier table with all fields as defined
 * in the requirements document. The table stores complete supplier information
 * including company details, contacts, addresses, and commercial terms.
 * 
 * Table: ppobase_supplier
 * 
 * @package PPOBase\Migration
 */
class Migration1700000001CreateSupplierTable extends MigrationStep
{
    /**
     * Returns the timestamp for this migration
     */
    public function getCreationTimestamp(): int
    {
        return 1700000001;
    }

    /**
     * Creates the ppobase_supplier table with all required columns
     */
    public function update(Connection $connection): void
    {
                $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `ppobase_supplier` (
            `id` BINARY(16) NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `supplier_number` VARCHAR(50) NULL,
            `items_id_range` VARCHAR(50) NULL,
            `company_number` VARCHAR(100) NULL,
            `company_trade_name` VARCHAR(255) NOT NULL,
            `company_official_name` VARCHAR(255) NULL,
            `vat_number` VARCHAR(50) NULL,
            `vat_percent` DOUBLE NULL DEFAULT 0,
            `export_number` VARCHAR(50) NULL,
            `business_number` VARCHAR(50) NULL,
            `company_email` VARCHAR(255) NULL,
            `contact_first_name` VARCHAR(100) NULL,
            `contact_last_name` VARCHAR(100) NULL,
            `contact_email` VARCHAR(255) NULL,
            `contact_phone` VARCHAR(50) NULL,
            `general_email` VARCHAR(255) NULL,
            `general_phone` VARCHAR(50) NULL,
            `purchase_email` VARCHAR(255) NULL,
            `default_cc` LONGTEXT NULL,
            `default_bcc` LONGTEXT NULL,
            `website` VARCHAR(255) NULL,
            `notes` LONGTEXT NULL,
            `billing_address_line1` VARCHAR(255) NULL,
            `billing_address_line2` VARCHAR(255) NULL,
            `billing_city` VARCHAR(100) NULL,
            `billing_postal_code` VARCHAR(20) NULL,
            `billing_country` VARCHAR(100) NULL,
            `billing_contact_person` VARCHAR(200) NULL,
            `billing_contact_email` VARCHAR(255) NULL,
            `billing_notes` LONGTEXT NULL,
            `shipping_address_line1` VARCHAR(255) NULL,
            `shipping_address_line2` VARCHAR(255) NULL,
            `shipping_city` VARCHAR(100) NULL,
            `shipping_postal_code` VARCHAR(20) NULL,
            `shipping_country` VARCHAR(100) NULL,
            `shipping_contact_person` VARCHAR(200) NULL,
            `shipping_contact_email` VARCHAR(255) NULL,
            `shipping_notes` LONGTEXT NULL,
            `lead_time_days` INT NULL,
            `delivery_days` VARCHAR(100) NULL,
            `currency` VARCHAR(3) NULL,
            `payment_terms` VARCHAR(255) NULL,
            `minimum_order_value` DOUBLE NULL,
            `incoterms` VARCHAR(20) NULL,
            `tax_id` VARCHAR(50) NULL,
            `discount_agreements` LONGTEXT NULL,
            `created_by` VARCHAR(255) NULL,
            `updated_by` VARCHAR(255) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            
            PRIMARY KEY (`id`),
            INDEX `idx_supplier_number` (`supplier_number`),
            INDEX `idx_company_trade_name` (`company_trade_name`),
            INDEX `idx_active` (`active`)
            
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($sql);
    }

    /**
     * updateDestructive
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
