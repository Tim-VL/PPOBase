<?php declare(strict_types=1);

namespace PPOBase\Entity\GoodsReceipt;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(GoodsReceiptEntity $entity)
 * @method void set(string $key, GoodsReceiptEntity $entity)
 * @method GoodsReceiptEntity[] getIterator()
 * @method GoodsReceiptEntity[] getElements()
 * @method GoodsReceiptEntity|null get(string $key)
 * @method GoodsReceiptEntity|null first()
 * @method GoodsReceiptEntity|null last()
 */
class GoodsReceiptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GoodsReceiptEntity::class;
    }
}
