<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000010AddGroupedProductActive extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000010;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
ALTER TABLE `ppobase_product_extension`
    ADD COLUMN IF NOT EXISTS `is_grouped_active` TINYINT(1) NOT NULL DEFAULT 1
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
