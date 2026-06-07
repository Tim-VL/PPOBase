<?php declare(strict_types=1);

namespace PPOBase\Entity\PurchaseOrder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PurchaseOrderEntity $entity)
 * @method void set(string $key, PurchaseOrderEntity $entity)
 * @method PurchaseOrderEntity[] getIterator()
 * @method PurchaseOrderEntity[] getElements()
 * @method PurchaseOrderEntity|null get(string $key)
 * @method PurchaseOrderEntity|null first()
 * @method PurchaseOrderEntity|null last()
 */
class PurchaseOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PurchaseOrderEntity::class;
    }
}