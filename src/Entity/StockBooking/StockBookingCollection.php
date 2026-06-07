<?php declare(strict_types=1);

namespace PPOBase\Entity\StockBooking;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(StockBookingEntity $entity)
 * @method void set(string $key, StockBookingEntity $entity)
 * @method StockBookingEntity[] getIterator()
 * @method StockBookingEntity[] getElements()
 * @method StockBookingEntity|null get(string $key)
 * @method StockBookingEntity|null first()
 * @method StockBookingEntity|null last()
 */
class StockBookingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return StockBookingEntity::class;
    }
}
