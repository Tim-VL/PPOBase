<?php declare(strict_types=1);

namespace PPOBase\Entity\PurchaseOrder;

use PPOBase\Entity\PurchaseOrderItem\PurchaseOrderItemCollection;
use PPOBase\Entity\Supplier\SupplierEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PurchaseOrderEntity extends Entity
{
    use EntityIdTrait;

    protected string $poNumber;
    protected string $supplierId;
    protected ?string $salesChannelId = null;
    protected string $status;
    protected ?\DateTimeInterface $orderDate = null;
    protected ?\DateTimeInterface $expectedDeliveryDate = null;
    protected ?string $currency = null;
    protected ?float $subtotal = null;
    protected ?float $taxAmount = null;
    protected ?float $total = null;
    protected ?int $itemCount = null;
    protected ?string $notes = null;
    protected ?string $internalReference = null;
    protected ?string $shippingAddress = null;
    protected ?bool $emailSent = false;
    protected ?\DateTimeInterface $emailSentAt = null;
    protected ?string $emailSentTo = null;
    protected ?string $createdBy = null;
    protected ?string $updatedBy = null;
    protected ?SupplierEntity $supplier = null;
    protected ?SalesChannelEntity $salesChannel = null;
    protected ?PurchaseOrderItemCollection $items = null;

    public function getPoNumber(): string
    {
        return $this->poNumber;
    }

    public function setPoNumber(string $poNumber): void
    {
        $this->poNumber = $poNumber;
    }

    public function getSupplierId(): string
    {
        return $this->supplierId;
    }

    public function setSupplierId(string $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getOrderDate(): ?\DateTimeInterface
    {
        return $this->orderDate;
    }

    public function setOrderDate(?\DateTimeInterface $orderDate): void
    {
        $this->orderDate = $orderDate;
    }

    public function getExpectedDeliveryDate(): ?\DateTimeInterface
    {
        return $this->expectedDeliveryDate;
    }

    public function setExpectedDeliveryDate(?\DateTimeInterface $expectedDeliveryDate): void
    {
        $this->expectedDeliveryDate = $expectedDeliveryDate;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getSubtotal(): ?float
    {
        return $this->subtotal;
    }

    public function setSubtotal(?float $subtotal): void
    {
        $this->subtotal = $subtotal;
    }

    public function getTaxAmount(): ?float
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?float $taxAmount): void
    {
        $this->taxAmount = $taxAmount;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(?float $total): void
    {
        $this->total = $total;
    }

    public function getItemCount(): ?int
    {
        return $this->itemCount;
    }

    public function setItemCount(?int $itemCount): void
    {
        $this->itemCount = $itemCount;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getInternalReference(): ?string
    {
        return $this->internalReference;
    }

    public function setInternalReference(?string $internalReference): void
    {
        $this->internalReference = $internalReference;
    }

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?string $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getEmailSent(): ?bool
    {
        return $this->emailSent;
    }

    public function setEmailSent(?bool $emailSent): void
    {
        $this->emailSent = $emailSent;
    }

    public function getEmailSentAt(): ?\DateTimeInterface
    {
        return $this->emailSentAt;
    }

    public function setEmailSentAt(?\DateTimeInterface $emailSentAt): void
    {
        $this->emailSentAt = $emailSentAt;
    }

    public function getEmailSentTo(): ?string
    {
        return $this->emailSentTo;
    }

    public function setEmailSentTo(?string $emailSentTo): void
    {
        $this->emailSentTo = $emailSentTo;
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

    public function getSupplier(): ?SupplierEntity
    {
        return $this->supplier;
    }

    public function setSupplier(?SupplierEntity $supplier): void
    {
        $this->supplier = $supplier;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getItems(): ?PurchaseOrderItemCollection
    {
        return $this->items;
    }

    public function setItems(?PurchaseOrderItemCollection $items): void
    {
        $this->items = $items;
    }
}
