<?php declare(strict_types=1);

namespace PPOBase\Entity\StockBooking;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class StockBookingEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected int $oldStock = 0;
    protected int $newStock = 0;
    protected int $stockChange = 0;
    protected ?string $reason = null;
    protected ?string $notes = null;
    protected ?string $referenceType = null;
    protected ?string $referenceId = null;
    protected ?string $userId = null;
    protected ?string $userName = null;
    protected ?\DateTimeInterface $bookedAt = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getOldStock(): int
    {
        return $this->oldStock;
    }

    public function setOldStock(int $oldStock): void
    {
        $this->oldStock = $oldStock;
    }

    public function getNewStock(): int
    {
        return $this->newStock;
    }

    public function setNewStock(int $newStock): void
    {
        $this->newStock = $newStock;
    }

    public function getStockChange(): int
    {
        return $this->stockChange;
    }

    public function setStockChange(int $stockChange): void
    {
        $this->stockChange = $stockChange;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): void
    {
        $this->referenceType = $referenceType;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): void
    {
        $this->referenceId = $referenceId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): void
    {
        $this->userName = $userName;
    }

    public function getBookedAt(): ?\DateTimeInterface
    {
        return $this->bookedAt;
    }

    public function setBookedAt(?\DateTimeInterface $bookedAt): void
    {
        $this->bookedAt = $bookedAt;
    }
}
