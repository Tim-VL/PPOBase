<?php declare(strict_types=1);

namespace PPOBase\Entity\StockBooking;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class StockBookingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_stock_booking';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StockBookingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StockBookingCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new IntField('old_stock', 'oldStock'))
                ->addFlags(new ApiAware()),

            (new IntField('new_stock', 'newStock'))
                ->addFlags(new ApiAware()),

            (new IntField('stock_change', 'stockChange'))
                ->addFlags(new ApiAware()),

            (new StringField('reason', 'reason', 255))
                ->addFlags(new ApiAware()),

            (new LongTextField('notes', 'notes'))
                ->addFlags(new ApiAware()),

            (new StringField('reference_type', 'referenceType', 50))
                ->addFlags(new ApiAware()),

            (new StringField('reference_id', 'referenceId', 32))
                ->addFlags(new ApiAware()),

            (new StringField('user_id', 'userId', 32))
                ->addFlags(new ApiAware()),

            (new StringField('user_name', 'userName', 255))
                ->addFlags(new ApiAware()),

            (new DateTimeField('booked_at', 'bookedAt'))
                ->addFlags(new ApiAware(), new Required()),

            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
