<?php declare(strict_types=1);

namespace PPOBase\Entity\ActivityLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ActivityLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $entityType;
    protected string $entityId;
    protected string $action;
    protected ?string $description = null;
    protected ?string $oldValues = null;
    protected ?string $newValues = null;
    protected ?string $referenceNumber = null;
    protected ?string $userId = null;
    protected ?string $userName = null;
    protected ?string $ipAddress = null;
    protected \DateTimeInterface $loggedAt;

    public function getEntityType(): string { return $this->entityType; }
    public function setEntityType(string $entityType): void { $this->entityType = $entityType; }
    public function getEntityId(): string { return $this->entityId; }
    public function setEntityId(string $entityId): void { $this->entityId = $entityId; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): void { $this->action = $action; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function getOldValues(): ?string { return $this->oldValues; }
    public function setOldValues(?string $oldValues): void { $this->oldValues = $oldValues; }
    public function getNewValues(): ?string { return $this->newValues; }
    public function setNewValues(?string $newValues): void { $this->newValues = $newValues; }
    public function getReferenceNumber(): ?string { return $this->referenceNumber; }
    public function setReferenceNumber(?string $referenceNumber): void { $this->referenceNumber = $referenceNumber; }
    public function getUserId(): ?string { return $this->userId; }
    public function setUserId(?string $userId): void { $this->userId = $userId; }
    public function getUserName(): ?string { return $this->userName; }
    public function setUserName(?string $userName): void { $this->userName = $userName; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): void { $this->ipAddress = $ipAddress; }
    public function getLoggedAt(): \DateTimeInterface { return $this->loggedAt; }
    public function setLoggedAt(\DateTimeInterface $loggedAt): void { $this->loggedAt = $loggedAt; }
}
