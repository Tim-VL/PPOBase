<?php declare(strict_types=1);

namespace PPOBase\Entity\PurchaseOrder;

use PPOBase\Entity\GoodsReceipt\GoodsReceiptDefinition;
use PPOBase\Entity\PurchaseOrderItem\PurchaseOrderItemDefinition;
use PPOBase\Entity\Supplier\SupplierDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class PurchaseOrderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_purchase_order';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PurchaseOrderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PurchaseOrderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            // PO Number: PO2026-0001
            (new StringField('po_number', 'poNumber', 50))
                ->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            // Supplier reference
            (new FkField('supplier_id', 'supplierId', SupplierDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))
                ->addFlags(new ApiAware()),

            // Status: draft, sent, partial, received, cancelled
            (new StringField('status', 'status', 50))
                ->addFlags(new ApiAware(), new Required()),

            // Order date
            (new DateTimeField('order_date', 'orderDate'))
                ->addFlags(new ApiAware()),

            // Expected delivery date
            (new DateTimeField('expected_delivery_date', 'expectedDeliveryDate'))
                ->addFlags(new ApiAware()),

            // Currency (from supplier or override)
            (new StringField('currency', 'currency', 3))
                ->addFlags(new ApiAware()),

            // Totals
            (new FloatField('subtotal', 'subtotal'))
                ->addFlags(new ApiAware()),

            (new FloatField('tax_amount', 'taxAmount'))
                ->addFlags(new ApiAware()),

            (new FloatField('total', 'total'))
                ->addFlags(new ApiAware()),

            // Item count
            (new IntField('item_count', 'itemCount'))
                ->addFlags(new ApiAware()),

            // Notes (HTML)
            (new LongTextField('notes', 'notes'))
                ->addFlags(new ApiAware()),

            // Internal reference
            (new StringField('internal_reference', 'internalReference', 100))
                ->addFlags(new ApiAware()),

            // Shipping address (can override supplier default)
            (new LongTextField('shipping_address', 'shippingAddress'))
                ->addFlags(new ApiAware()),

            // Email sent tracking
            (new BoolField('email_sent', 'emailSent'))
                ->addFlags(new ApiAware()),

            (new DateTimeField('email_sent_at', 'emailSentAt'))
                ->addFlags(new ApiAware()),

            // Tracks which email address the PO was sent to
            (new StringField('email_sent_to', 'emailSentTo', 255))
                ->addFlags(new ApiAware()),

            // Audit fields
            (new StringField('created_by', 'createdBy', 255))
                ->addFlags(new ApiAware()),

            (new StringField('updated_by', 'updatedBy', 255))
                ->addFlags(new ApiAware()),

            // Associations
            new ManyToOneAssociationField('supplier', 'supplier_id', SupplierDefinition::class, 'id', false),
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))
                ->addFlags(new ApiAware()),
            
            (new OneToManyAssociationField('items', PurchaseOrderItemDefinition::class, 'purchase_order_id'))
                ->addFlags(new ApiAware(), new CascadeDelete()),

            (new OneToManyAssociationField('goodsReceipts', GoodsReceiptDefinition::class, 'purchase_order_id'))
                ->addFlags(new ApiAware()),
        ]);
    }
}
