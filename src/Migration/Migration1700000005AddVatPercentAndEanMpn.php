<?php declare(strict_types=1);

namespace PPOBase\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Deprecated migration stub.
 *
 * The schema changes that were previously applied by this migration
 * are now part of the original table creation migrations. Keep a
 * no-op migration here to preserve migration ordering for existing
 * installations that already ran this migration in the past.
 */
class Migration1700000005AddVatPercentAndEanMpn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000005;
    }

    public function update(Connection $connection): void
    {
        // No-op: columns are created in parent migrations now.
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
