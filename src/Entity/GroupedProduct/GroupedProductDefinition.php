<?php declare(strict_types=1);

namespace PPOBase\Entity\GroupedProduct;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * DAL definition for the ppobase_grouped_product entity.
 *
 * @package PPOBase
 */
class GroupedProductDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_grouped_product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GroupedProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GroupedProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('parent_product_id', 'parentProductId', ProductDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new FkField('child_product_id', 'childProductId', ProductDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            // How many units of the child product are included per parent
            (new IntField('quantity', 'quantity'))
                ->addFlags(new ApiAware()),

            // Controls display order in admin and storefront
            (new IntField('position', 'position'))
                ->addFlags(new ApiAware()),

            new ManyToOneAssociationField('parentProduct', 'parent_product_id', ProductDefinition::class, 'id', false),
            new ManyToOneAssociationField('childProduct', 'child_product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
