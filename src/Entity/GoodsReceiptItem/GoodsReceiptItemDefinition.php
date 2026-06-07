<?php declare(strict_types=1);

namespace PPOBase\Entity\GoodsReceiptItem;

use PPOBase\Entity\GoodsReceipt\GoodsReceiptDefinition;
use PPOBase\Entity\PurchaseOrderItem\PurchaseOrderItemDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GoodsReceiptItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_goods_receipt_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GoodsReceiptItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GoodsReceiptItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('goods_receipt_id', 'goodsReceiptId', GoodsReceiptDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new FkField('purchase_order_item_id', 'purchaseOrderItemId', PurchaseOrderItemDefinition::class))
                ->addFlags(new ApiAware()),

            (new FkField('product_id', 'productId', ProductDefinition::class))
                ->addFlags(new ApiAware()),

            (new StringField('product_number', 'productNumber', 100))
                ->addFlags(new ApiAware()),

            (new StringField('product_name', 'productName', 255))
                ->addFlags(new ApiAware(), new Required()),

            (new StringField('ean', 'ean', 100))
                ->addFlags(new ApiAware()),

            (new StringField('mpn', 'mpn', 100))
                ->addFlags(new ApiAware()),

            (new IntField('quantity_expected', 'quantityExpected'))
                ->addFlags(new ApiAware()),

            (new IntField('quantity_received', 'quantityReceived'))
                ->addFlags(new ApiAware(), new Required()),

            (new IntField('quantity_rejected', 'quantityRejected'))
                ->addFlags(new ApiAware()),

            (new FloatField('unit_price', 'unitPrice'))
                ->addFlags(new ApiAware()),

            (new FloatField('booked_price', 'bookedPrice'))
                ->addFlags(new ApiAware()),

            (new FloatField('line_total', 'lineTotal'))
                ->addFlags(new ApiAware()),

            (new LongTextField('notes', 'notes'))
                ->addFlags(new ApiAware()),

            // Associations
            new ManyToOneAssociationField('goodsReceipt', 'goods_receipt_id', GoodsReceiptDefinition::class, 'id', false),
            new ManyToOneAssociationField('purchaseOrderItem', 'purchase_order_item_id', PurchaseOrderItemDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
