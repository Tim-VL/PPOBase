<?php declare(strict_types=1);

namespace PPOBase\Entity\PurchaseOrderItem;

use PPOBase\Entity\PurchaseOrder\PurchaseOrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PurchaseOrderItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $purchaseOrderId;
    protected ?string $productId = null;
    protected ?int $lineNumber = null;
    protected ?string $productNumber = null;
    protected string $productName;
    protected ?string $supplierSku = null;
    protected ?string $ean = null;
    protected ?string $mpn = null;
    protected int $quantityOrdered;
    protected ?int $quantityReceived = 0;
    protected ?string $unit = 'pcs';
    protected ?float $unitPrice = null;
    protected ?float $taxRate = null;
    protected ?float $taxAmount = null;
    protected ?float $lineTotal = null;
    protected ?float $discountPercent = null;
    protected ?float $discountAmount = null;
    protected ?string $notes = null;
    protected ?PurchaseOrderEntity $purchaseOrder = null;
    protected ?ProductEntity $product = null;

    public function getPurchaseOrderId(): string
    {
        return $this->purchaseOrderId;
    }

    public function setPurchaseOrderId(string $purchaseOrderId): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(?int $lineNumber): void
    {
        $this->lineNumber = $lineNumber;
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function setProductNumber(?string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): void
    {
        $this->productName = $productName;
    }

    public function getSupplierSku(): ?string
    {
        return $this->supplierSku;
    }

    public function setSupplierSku(?string $supplierSku): void
    {
        $this->supplierSku = $supplierSku;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function getMpn(): ?string
    {
        return $this->mpn;
    }

    public function setMpn(?string $mpn): void
    {
        $this->mpn = $mpn;
    }

    public function getQuantityOrdered(): int
    {
        return $this->quantityOrdered;
    }

    public function setQuantityOrdered(int $quantityOrdered): void
    {
        $this->quantityOrdered = $quantityOrdered;
    }

    public function getQuantityReceived(): ?int
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(?int $quantityReceived): void
    {
        $this->quantityReceived = $quantityReceived;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): void
    {
        $this->unit = $unit;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    public function setTaxRate(?float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getTaxAmount(): ?float
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?float $taxAmount): void
    {
        $this->taxAmount = $taxAmount;
    }

    public function getLineTotal(): ?float
    {
        return $this->lineTotal;
    }

    public function setLineTotal(?float $lineTotal): void
    {
        $this->lineTotal = $lineTotal;
    }

    public function getDiscountPercent(): ?float
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?float $discountPercent): void
    {
        $this->discountPercent = $discountPercent;
    }

    public function getDiscountAmount(): ?float
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?float $discountAmount): void
    {
        $this->discountAmount = $discountAmount;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getPurchaseOrder(): ?PurchaseOrderEntity
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?PurchaseOrderEntity $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
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