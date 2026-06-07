<?php declare(strict_types=1);

namespace PPOBase\Entity\ActivityLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(ActivityLogEntity $entity)
 * @method void set(string $key, ActivityLogEntity $entity)
 * @method ActivityLogEntity[] getIterator()
 * @method ActivityLogEntity[] getElements()
 * @method ActivityLogEntity|null get(string $key)
 * @method ActivityLogEntity|null first()
 * @method ActivityLogEntity|null last()
 */
class ActivityLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActivityLogEntity::class;
    }
}
