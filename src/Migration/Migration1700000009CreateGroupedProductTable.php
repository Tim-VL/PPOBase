<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates the ppobase_grouped_product table which stores parent→child
 * product relationships for the Grouped Products feature.
 *
 * @package PPOBase
 */
class Migration1700000009CreateGroupedProductTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000009;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
CREATE TABLE IF NOT EXISTS `ppobase_grouped_product` (
    `id`                BINARY(16)   NOT NULL,
    `parent_product_id` BINARY(16)   NOT NULL,
    `child_product_id`  BINARY(16)   NOT NULL,
    `quantity`          INT(11)      NOT NULL DEFAULT 1,
    `position`          INT(11)      NOT NULL DEFAULT 0,
    `created_at`        DATETIME(3)  NOT NULL,
    `updated_at`        DATETIME(3)  NULL,

    PRIMARY KEY (`id`),

    -- Prevent duplicate parent→child assignments
    UNIQUE KEY `uniq_parent_child` (`parent_product_id`, `child_product_id`),

    INDEX `idx_parent_product_id` (`parent_product_id`),
    INDEX `idx_child_product_id`  (`child_product_id`),

    CONSTRAINT `fk_ppobase_grouped_parent`
        FOREIGN KEY (`parent_product_id`) REFERENCES `product` (`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_ppobase_grouped_child`
        FOREIGN KEY (`child_product_id`) REFERENCES `product` (`id`)
        ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
