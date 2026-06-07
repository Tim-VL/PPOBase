<?php declare(strict_types=1);

namespace PPOBase\Entity\SupplierProduct;

use PPOBase\Entity\Supplier\SupplierDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * SupplierProduct Entity Definition
 * 
 * Maps products to suppliers (many-to-many relationship).
 * A product can have multiple suppliers.
 * Stores supplier-specific product data like SKU and price.
 * 
 * @package PPOBase\Entity\SupplierProduct
 */
class SupplierProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_supplier_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SupplierProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SupplierProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('supplier_id', 'supplierId', SupplierDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            // Supplier-specific SKU for this product
            (new StringField('supplier_sku', 'supplierSku', 100))
                ->addFlags(new ApiAware()),

            // Supplier's price for this product
            (new FloatField('supplier_price', 'supplierPrice'))
                ->addFlags(new ApiAware()),

            // Supplier's currency for this product
            (new StringField('supplier_currency', 'supplierCurrency', 3))
                ->addFlags(new ApiAware()),

            // Associations
            new ManyToOneAssociationField('supplier', 'supplier_id', SupplierDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
