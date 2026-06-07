<?php declare(strict_types=1);

namespace PPOBase\Entity\GoodsReceiptItem;

use PPOBase\Entity\GoodsReceipt\GoodsReceiptEntity;
use PPOBase\Entity\PurchaseOrderItem\PurchaseOrderItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class GoodsReceiptItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $goodsReceiptId;
    protected ?string $purchaseOrderItemId = null;
    protected ?string $productId = null;
    protected ?string $productNumber = null;
    protected string $productName;
    protected ?string $ean = null;
    protected ?string $mpn = null;
    protected ?int $quantityExpected = 0;
    protected int $quantityReceived = 0;
    protected ?int $quantityRejected = 0;
    protected ?float $unitPrice = 0;
    protected ?float $bookedPrice = 0;
    protected ?float $lineTotal = 0;
    protected ?string $notes = null;
    protected ?GoodsReceiptEntity $goodsReceipt = null;
    protected ?PurchaseOrderItemEntity $purchaseOrderItem = null;
    protected ?ProductEntity $product = null;

    public function getGoodsReceiptId(): string
    {
        return $this->goodsReceiptId;
    }

    public function setGoodsReceiptId(string $goodsReceiptId): void
    {
        $this->goodsReceiptId = $goodsReceiptId;
    }

    public function getPurchaseOrderItemId(): ?string
    {
        return $this->purchaseOrderItemId;
    }

    public function setPurchaseOrderItemId(?string $purchaseOrderItemId): void
    {
        $this->purchaseOrderItemId = $purchaseOrderItemId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
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

    public function getQuantityExpected(): ?int
    {
        return $this->quantityExpected;
    }

    public function setQuantityExpected(?int $quantityExpected): void
    {
        $this->quantityExpected = $quantityExpected;
    }

    public function getQuantityReceived(): int
    {
        return $this->quantityReceived;
    }

    public function setQuantityReceived(int $quantityReceived): void
    {
        $this->quantityReceived = $quantityReceived;
    }

    public function getQuantityRejected(): ?int
    {
        return $this->quantityRejected;
    }

    public function setQuantityRejected(?int $quantityRejected): void
    {
        $this->quantityRejected = $quantityRejected;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getBookedPrice(): ?float
    {
        return $this->bookedPrice;
    }

    public function setBookedPrice(?float $bookedPrice): void
    {
        $this->bookedPrice = $bookedPrice;
    }

    public function getLineTotal(): ?float
    {
        return $this->lineTotal;
    }

    public function setLineTotal(?float $lineTotal): void
    {
        $this->lineTotal = $lineTotal;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getGoodsReceipt(): ?GoodsReceiptEntity
    {
        return $this->goodsReceipt;
    }

    public function setGoodsReceipt(?GoodsReceiptEntity $goodsReceipt): void
    {
        $this->goodsReceipt = $goodsReceipt;
    }

    public function getPurchaseOrderItem(): ?PurchaseOrderItemEntity
    {
        return $this->purchaseOrderItem;
    }

    public function setPurchaseOrderItem(?PurchaseOrderItemEntity $purchaseOrderItem): void
    {
        $this->purchaseOrderItem = $purchaseOrderItem;
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
