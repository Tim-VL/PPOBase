<?php declare(strict_types=1);

namespace PPOBase\Entity\SupplierProduct;

use PPOBase\Entity\Supplier\SupplierEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * SupplierProduct Entity
 * 
 * Represents the mapping between a supplier and a product.
 * 
 * @package PPOBase\Entity\SupplierProduct
 */
class SupplierProductEntity extends Entity
{
    use EntityIdTrait;

    protected string $supplierId;
    protected string $productId;
    protected ?string $supplierSku = null;
    protected ?float $supplierPrice = null;
    protected ?string $supplierCurrency = null;
    protected ?SupplierEntity $supplier = null;
    protected ?ProductEntity $product = null;

    public function getSupplierId(): string
    {
        return $this->supplierId;
    }

    public function setSupplierId(string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getSupplierSku(): ?string
    {
        return $this->supplierSku;
    }

    public function setSupplierSku(?string $supplierSku): void
    {
        $this->supplierSku = $supplierSku;
    }

    public function getSupplierPrice(): ?float
    {
        return $this->supplierPrice;
    }

    public function setSupplierPrice(?float $supplierPrice): void
    {
        $this->supplierPrice = $supplierPrice;
    }

    public function getSupplierCurrency(): ?string
    {
        return $this->supplierCurrency;
    }

    public function setSupplierCurrency(?string $supplierCurrency): void
    {
        $this->supplierCurrency = $supplierCurrency;
    }

    public function getSupplier(): ?SupplierEntity
    {
        return $this->supplier;
    }

    public function setSupplier(?SupplierEntity $supplier): void
    {
        $this->supplier = $supplier;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }
}
