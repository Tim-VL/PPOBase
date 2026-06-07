<?php declare(strict_types=1);

namespace PPOBase\Entity\ProductExtension;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductExtensionEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?int $itemsPerCarton = 0;
    protected ?int $cartonsPerLayer = 0;
    protected ?float $cartonLength = 0.0;
    protected ?float $cartonHeight = 0.0;
    protected ?float $cartonWidth = 0.0;
    protected ?float $cartonWeightNet = 0.0;
    protected ?float $cartonWeightGross = 0.0;
    protected ?string $ruleNotes = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getItemsPerCarton(): ?int
    {
        return $this->itemsPerCarton;
    }

    public function setItemsPerCarton(?int $itemsPerCarton): void
    {
        $this->itemsPerCarton = $itemsPerCarton;
    }

    public function getCartonsPerLayer(): ?int
    {
        return $this->cartonsPerLayer;
    }

    public function setCartonsPerLayer(?int $cartonsPerLayer): void
    {
        $this->cartonsPerLayer = $cartonsPerLayer;
    }

    public function getCartonLength(): ?float
    {
        return $this->cartonLength;
    }

    public function setCartonLength(?float $cartonLength): void
    {
        $this->cartonLength = $cartonLength;
    }

    public function getCartonHeight(): ?float
    {
        return $this->cartonHeight;
    }

    public function setCartonHeight(?float $cartonHeight): void
    {
        $this->cartonHeight = $cartonHeight;
    }

    public function getCartonWidth(): ?float
    {
        return $this->cartonWidth;
    }

    public function setCartonWidth(?float $cartonWidth): void
    {
        $this->cartonWidth = $cartonWidth;
    }

    public function getCartonWeightNet(): ?float
    {
        return $this->cartonWeightNet;
    }

    public function setCartonWeightNet(?float $cartonWeightNet): void
    {
        $this->cartonWeightNet = $cartonWeightNet;
    }

    public function getCartonWeightGross(): ?float
    {
        return $this->cartonWeightGross;
    }

    public function setCartonWeightGross(?float $cartonWeightGross): void
    {
        $this->cartonWeightGross = $cartonWeightGross;
    }

    public function getRuleNotes(): ?string
    {
        return $this->ruleNotes;
    }

    public function setRuleNotes(?string $ruleNotes): void
    {
        $this->ruleNotes = $ruleNotes;
    }
}
