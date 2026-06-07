<?php declare(strict_types=1);

namespace PPOBase\Entity\ProductExtension;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(ProductExtensionEntity $entity)
 * @method void set(string $key, ProductExtensionEntity $entity)
 * @method ProductExtensionEntity[] getIterator()
 * @method ProductExtensionEntity[] getElements()
 * @method ProductExtensionEntity|null get(string $key)
 * @method ProductExtensionEntity|null first()
 * @method ProductExtensionEntity|null last()
 */
class ProductExtensionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductExtensionEntity::class;
    }
}
