<?php declare(strict_types=1);

namespace PPOBase\Entity\GoodsReceiptItem;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(GoodsReceiptItemEntity $entity)
 * @method void set(string $key, GoodsReceiptItemEntity $entity)
 * @method GoodsReceiptItemEntity[] getIterator()
 * @method GoodsReceiptItemEntity[] getElements()
 * @method GoodsReceiptItemEntity|null get(string $key)
 * @method GoodsReceiptItemEntity|null first()
 * @method GoodsReceiptItemEntity|null last()
 */
class GoodsReceiptItemCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GoodsReceiptItemEntity::class;
    }
}
