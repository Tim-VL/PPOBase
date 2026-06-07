<?php declare(strict_types=1);

namespace PPOBase\Entity\SupplierProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * SupplierProduct Collection
 * 
 * @package PPOBase\Entity\SupplierProduct
 * @method void add(SupplierProductEntity $entity)
 * @method void set(string $key, SupplierProductEntity $entity)
 * @method SupplierProductEntity[] getIterator()
 * @method SupplierProductEntity[] getElements()
 * @method SupplierProductEntity|null get(string $key)
 * @method SupplierProductEntity|null first()
 * @method SupplierProductEntity|null last()
 */
class SupplierProductCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SupplierProductEntity::class;
    }
}
