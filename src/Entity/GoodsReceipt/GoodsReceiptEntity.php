<?php declare(strict_types=1);

namespace PPOBase\Entity\GoodsReceipt;

use PPOBase\Entity\GoodsReceiptItem\GoodsReceiptItemCollection;
use PPOBase\Entity\PurchaseOrder\PurchaseOrderEntity;
use PPOBase\Entity\Supplier\SupplierEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class GoodsReceiptEntity extends Entity
{
    use EntityIdTrait;

    protected string $receiptNumber;
    protected string $purchaseOrderId;
    protected string $supplierId;
    protected string $status;
    protected ?\DateTimeInterface $receiptDate = null;
    protected ?string $invoiceReference = null;
    protected ?string $notes = null;
    protected ?string $createdBy = null;
    protected ?string $updatedBy = null;
    protected ?PurchaseOrderEntity $purchaseOrder = null;
    protected ?SupplierEntity $supplier = null;
    protected ?GoodsReceiptItemCollection $items = null;

    public function getReceiptNumber(): string
    {
        return $this->receiptNumber;
    }

    public function setReceiptNumber(string $receiptNumber): void
    {
        $this->receiptNumber = $receiptNumber;
    }

    public function getPurchaseOrderId(): string
    {
        return $this->purchaseOrderId;
    }

    public function setPurchaseOrderId(string $purchaseOrderId): void
    {
        $this->purchaseOrderId = $purchaseOrderId;
    }

    public function getSupplierId(): string
    {
        return $this->supplierId;
    }

    public function setSupplierId(string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getReceiptDate(): ?\DateTimeInterface
    {
        return $this->receiptDate;
    }

    public function setReceiptDate(?\DateTimeInterface $receiptDate): void
    {
        $this->receiptDate = $receiptDate;
    }

    public function getInvoiceReference(): ?string
    {
        return $this->invoiceReference;
    }

    public function setInvoiceReference(?string $invoiceReference): void
    {
        $this->invoiceReference = $invoiceReference;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function getPurchaseOrder(): ?PurchaseOrderEntity
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?PurchaseOrderEntity $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    public function getSupplier(): ?SupplierEntity
    {
        return $this->supplier;
    }

    public function setSupplier(?SupplierEntity $supplier): void
    {
        $this->supplier = $supplier;
    }

    public function getItems(): ?GoodsReceiptItemCollection
    {
        return $this->items;
    }

    public function setItems(?GoodsReceiptItemCollection $items): void
    {
        $this->items = $items;
    }
}
