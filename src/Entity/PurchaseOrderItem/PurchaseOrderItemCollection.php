<?php declare(strict_types=1);

namespace PPOBase\Entity\PurchaseOrderItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(PurchaseOrderItemEntity $entity)
 * @method void set(string $key, PurchaseOrderItemEntity $entity)
 * @method PurchaseOrderItemEntity[] getIterator()
 * @method PurchaseOrderItemEntity[] getElements()
 * @method PurchaseOrderItemEntity|null get(string $key)
 * @method PurchaseOrderItemEntity|null first()
 * @method PurchaseOrderItemEntity|null last()
 */
class PurchaseOrderItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PurchaseOrderItemEntity::class;
    }
}