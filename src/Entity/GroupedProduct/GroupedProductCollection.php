<?php declare(strict_types=1);

namespace PPOBase\Entity\GroupedProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Collection of GroupedProductEntity objects.
 *
 * @method void                          add(GroupedProductEntity $entity)
 * @method void                          set(string $key, GroupedProductEntity $entity)
 * @method GroupedProductEntity[]        getIterator()
 * @method GroupedProductEntity[]        getElements()
 * @method GroupedProductEntity|null     get(string $key)
 * @method GroupedProductEntity|null     first()
 * @method GroupedProductEntity|null     last()
 *
 * @package PPOBase
 */
class GroupedProductCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GroupedProductEntity::class;
    }

    /**
     * Returns a new collection containing only rows whose parentProductId matches.
     */
    public function filterByParentProductId(string $id): self
    {
        return $this->filter(fn (GroupedProductEntity $entity) => $entity->getParentProductId() === $id);
    }
}
