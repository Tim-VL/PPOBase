<?php declare(strict_types=1);

namespace PPOBase\Entity\Supplier;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Supplier Collection
 * 
 * This class holds multiple SupplierEntity instances.
 * Used when fetching multiple suppliers from the database.
 * EntityCollection which provides helpful methods like:
 * - filter(): Filter entities by a callback
 * - map(): Transform entities
 * - getIds(): Get all entity IDs
 * - first(): Get the first entity
 * 
 * @package PPOBase\Entity\Supplier
 * @method void add(SupplierEntity $entity)
 * @method void set(string $key, SupplierEntity $entity)
 * @method SupplierEntity[] getIterator()
 * @method SupplierEntity[] getElements()
 * @method SupplierEntity|null get(string $key)
 * @method SupplierEntity|null first()
 * @method SupplierEntity|null last()
 */
class SupplierCollection extends EntityCollection
{
    /**
     * Returns the expected class of entities in this collection
     */
    protected function getExpectedClass(): string
    {
        return SupplierEntity::class;
    }

    /**
     * Filter collection to only active suppliers
     */
    public function filterActive(): self
    {
        return $this->filter(function (SupplierEntity $supplier) {
            return $supplier->isActive();
        });
    }

    /**
     * Get all supplier IDs as an array
     */
    public function getSupplierIds(): array
    {
        return $this->map(function (SupplierEntity $supplier) {
            return $supplier->getId();
        });
    }

    /**
     * Sort suppliers by trade name alphabetically
     */
    public function sortByTradeName(): self
    {
        $elements = $this->getElements();
        
        usort($elements, function (SupplierEntity $a, SupplierEntity $b) {
            return strcasecmp(
                $a->getCompanyTradeName() ?? '',
                $b->getCompanyTradeName() ?? ''
            );
        });

        return new self($elements);
    }
}
