<?php declare(strict_types=1);

namespace PPOBase\Entity\PurchaseOrderItem;

use PPOBase\Entity\PurchaseOrder\PurchaseOrderDefinition;
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

class PurchaseOrderItemDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_purchase_order_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PurchaseOrderItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PurchaseOrderItemCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            // Parent PO
            (new FkField('purchase_order_id', 'purchaseOrderId', PurchaseOrderDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            // Product (optional - can be manual item)
            (new FkField('product_id', 'productId', ProductDefinition::class))
                ->addFlags(new ApiAware()),

            // Line number for ordering
            (new IntField('line_number', 'lineNumber'))
                ->addFlags(new ApiAware()),

            // Product details (stored for history, even if product deleted)
            (new StringField('product_number', 'productNumber', 100))
                ->addFlags(new ApiAware()),

            (new StringField('product_name', 'productName', 255))
                ->addFlags(new ApiAware(), new Required()),

            // Supplier SKU
            (new StringField('supplier_sku', 'supplierSku', 100))
                ->addFlags(new ApiAware()),

            // EAN (European Article Number / barcode)
            (new StringField('ean', 'ean', 100))
                ->addFlags(new ApiAware()),

            // MPN (Manufacturer Part Number)
            (new StringField('mpn', 'mpn', 100))
                ->addFlags(new ApiAware()),

            // Quantities
            (new IntField('quantity_ordered', 'quantityOrdered'))
                ->addFlags(new ApiAware(), new Required()),

            (new IntField('quantity_received', 'quantityReceived'))
                ->addFlags(new ApiAware()),

            // Unit (pcs, kg, box, etc)
            (new StringField('unit', 'unit', 20))
                ->addFlags(new ApiAware()),

            // Pricing
            (new FloatField('unit_price', 'unitPrice'))
                ->addFlags(new ApiAware()),

            (new FloatField('tax_rate', 'taxRate'))
                ->addFlags(new ApiAware()),

            (new FloatField('tax_amount', 'taxAmount'))
                ->addFlags(new ApiAware()),

            (new FloatField('line_total', 'lineTotal'))
                ->addFlags(new ApiAware()),

            // Discount per line
            (new FloatField('discount_percent', 'discountPercent'))
                ->addFlags(new ApiAware()),

            (new FloatField('discount_amount', 'discountAmount'))
                ->addFlags(new ApiAware()),

            // Notes
            (new LongTextField('notes', 'notes'))
                ->addFlags(new ApiAware()),

            // Associations
            new ManyToOneAssociationField('purchaseOrder', 'purchase_order_id', PurchaseOrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}