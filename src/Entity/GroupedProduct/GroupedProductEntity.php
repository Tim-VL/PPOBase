<?php declare(strict_types=1);

namespace PPOBase\Entity\GroupedProduct;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Represents a single grouped-product relationship row.
 *
 * @package PPOBase
 */
class GroupedProductEntity extends Entity
{
    use EntityIdTrait;

    protected string $parentProductId;

    protected string $childProductId;

    /** @var int How many units of the child product are included per parent */
    protected int $quantity = 1;

    /** @var int Sort order for display */
    protected int $position = 0;

    protected ?ProductEntity $parentProduct = null;

    protected ?ProductEntity $childProduct = null;

    public function getParentProductId(): string
    {
        return $this->parentProductId;
    }

    public function setParentProductId(string $parentProductId): void
    {
        $this->parentProductId = $parentProductId;
    }

    public function getChildProductId(): string
    {
        return $this->childProductId;
    }

    public function setChildProductId(string $childProductId): void
    {
        $this->childProductId = $childProductId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getParentProduct(): ?ProductEntity
    {
        return $this->parentProduct;
    }

    public function setParentProduct(?ProductEntity $parentProduct): void
    {
        $this->parentProduct = $parentProduct;
    }

    public function getChildProduct(): ?ProductEntity
    {
        return $this->childProduct;
    }

    public function setChildProduct(?ProductEntity $childProduct): void
    {
        $this->childProduct = $childProduct;
    }
}
