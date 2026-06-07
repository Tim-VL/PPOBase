<?php declare(strict_types=1);

namespace PPOBase\Entity\ActivityLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ActivityLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_activity_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ActivityLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ActivityLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('entity_type', 'entityType', 50))->addFlags(new ApiAware(), new Required()),
            (new StringField('entity_id', 'entityId', 32))->addFlags(new ApiAware(), new Required()),
            (new StringField('action', 'action', 50))->addFlags(new ApiAware(), new Required()),
            (new StringField('description', 'description', 500))->addFlags(new ApiAware()),
            (new LongTextField('old_values', 'oldValues'))->addFlags(new ApiAware()),
            (new LongTextField('new_values', 'newValues'))->addFlags(new ApiAware()),
            (new StringField('reference_number', 'referenceNumber', 100))->addFlags(new ApiAware()),
            (new StringField('user_id', 'userId', 32))->addFlags(new ApiAware()),
            (new StringField('user_name', 'userName', 255))->addFlags(new ApiAware()),
            (new StringField('ip_address', 'ipAddress', 45))->addFlags(new ApiAware()),
            (new DateTimeField('logged_at', 'loggedAt'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
