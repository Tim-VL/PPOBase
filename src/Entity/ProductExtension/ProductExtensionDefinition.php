<?php declare(strict_types=1);

namespace PPOBase\Entity\ProductExtension;

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
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductExtensionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_product_extension';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductExtensionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductExtensionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))
                ->addFlags(new ApiAware(), new Required()),

            (new IntField('items_per_carton', 'itemsPerCarton'))
                ->addFlags(new ApiAware()),

            (new IntField('cartons_per_layer', 'cartonsPerLayer'))
                ->addFlags(new ApiAware()),

            (new FloatField('carton_length', 'cartonLength'))
                ->addFlags(new ApiAware()),

            (new FloatField('carton_height', 'cartonHeight'))
                ->addFlags(new ApiAware()),

            (new FloatField('carton_width', 'cartonWidth'))
                ->addFlags(new ApiAware()),

            (new FloatField('carton_weight_net', 'cartonWeightNet'))
                ->addFlags(new ApiAware()),

            (new FloatField('carton_weight_gross', 'cartonWeightGross'))
                ->addFlags(new ApiAware()),

            (new LongTextField('rule_notes', 'ruleNotes'))
                ->addFlags(new ApiAware()),

            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
