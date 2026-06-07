<?php declare(strict_types=1);

namespace PPOBase\Entity\GoodsReceipt;

use PPOBase\Entity\GoodsReceiptItem\GoodsReceiptItemDefinition;
use PPOBase\Entity\PurchaseOrder\PurchaseOrderDefinition;
use PPOBase\Entity\Supplier\SupplierDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GoodsReceiptDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_goods_receipt';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GoodsReceiptEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GoodsReceiptCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new StringField('receipt_number', 'receiptNumber', 50))
                ->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            (new FkField('purchase_order_id', 'purchaseOrderId', PurchaseOrderDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new FkField('supplier_id', 'supplierId', SupplierDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new StringField('status', 'status', 50))
                ->addFlags(new ApiAware(), new Required()),

            (new DateTimeField('receipt_date', 'receiptDate'))
                ->addFlags(new ApiAware()),

            (new StringField('invoice_reference', 'invoiceReference', 255))
                ->addFlags(new ApiAware()),

            (new LongTextField('notes', 'notes'))
                ->addFlags(new ApiAware()),

            (new StringField('created_by', 'createdBy', 255))
                ->addFlags(new ApiAware()),

            (new StringField('updated_by', 'updatedBy', 255))
                ->addFlags(new ApiAware()),

            // Associations
            new ManyToOneAssociationField('purchaseOrder', 'purchase_order_id', PurchaseOrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('supplier', 'supplier_id', SupplierDefinition::class, 'id', false),

            (new OneToManyAssociationField('items', GoodsReceiptItemDefinition::class, 'goods_receipt_id'))
                ->addFlags(new ApiAware(), new CascadeDelete()),
        ]);
    }
}
