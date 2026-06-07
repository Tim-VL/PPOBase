<?php declare(strict_types=1);

namespace PPOBase\Entity\Supplier;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Supplier Entity
 * 
 * This class represents a single supplier record.
 * It provides getters and setters for all supplier properties.
 * 
 * @package PPOBase\Entity\Supplier
 */
class SupplierEntity extends Entity
{
    use EntityIdTrait;

    protected bool $active = true;

    // Supplier details
    protected ?string $supplierNumber = null;
    protected ?string $itemsIdRange = null;
    protected ?string $companyNumber = null;
    protected ?string $companyTradeName = null;
    protected ?string $companyOfficialName = null;
    protected ?string $vatNumber = null;
    protected ?float $vatPercent = null;
    protected ?string $exportNumber = null;
    protected ?string $businessNumber = null;
    protected ?string $companyEmail = null;

    // Primary contact
    protected ?string $contactFirstName = null;
    protected ?string $contactLastName = null;
    protected ?string $contactEmail = null;
    protected ?string $contactPhone = null;

    // General contact
    protected ?string $generalEmail = null;
    protected ?string $generalPhone = null;
    protected ?string $purchaseEmail = null;
    protected ?string $defaultCc = null;
    protected ?string $defaultBcc = null;
    protected ?string $website = null;
    protected ?string $notes = null;

    // Billing address
    protected ?string $billingAddressLine1 = null;
    protected ?string $billingAddressLine2 = null;
    protected ?string $billingCity = null;
    protected ?string $billingPostalCode = null;
    protected ?string $billingCountry = null;
    protected ?string $billingContactPerson = null;
    protected ?string $billingContactEmail = null;
    protected ?string $billingNotes = null;

    // Shipping address
    protected ?string $shippingAddressLine1 = null;
    protected ?string $shippingAddressLine2 = null;
    protected ?string $shippingCity = null;
    protected ?string $shippingPostalCode = null;
    protected ?string $shippingCountry = null;
    protected ?string $shippingContactPerson = null;
    protected ?string $shippingContactEmail = null;
    protected ?string $shippingNotes = null;

    // Logistics
    protected ?int $leadTimeDays = null;
    protected ?string $deliveryDays = null;

    // Commercial
    protected ?string $currency = null;
    protected ?string $paymentTerms = null;
    protected ?float $minimumOrderValue = null;
    protected ?string $incoterms = null;
    protected ?string $taxId = null;
    protected ?string $discountAgreements = null;

    // Audit
    protected ?string $createdBy = null;
    protected ?string $updatedBy = null;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getSupplierNumber(): ?string
    {
        return $this->supplierNumber;
    }

    public function setSupplierNumber(?string $supplierNumber): void
    {
        $this->supplierNumber = $supplierNumber;
    }

    public function getItemsIdRange(): ?string
    {
        return $this->itemsIdRange;
    }

    public function setItemsIdRange(?string $itemsIdRange): void
    {
        $this->itemsIdRange = $itemsIdRange;
    }

    public function getCompanyNumber(): ?string
    {
        return $this->companyNumber;
    }

    public function setCompanyNumber(?string $companyNumber): void
    {
        $this->companyNumber = $companyNumber;
    }

    public function getCompanyTradeName(): ?string
    {
        return $this->companyTradeName;
    }

    public function setCompanyTradeName(?string $companyTradeName): void
    {
        $this->companyTradeName = $companyTradeName;
    }

    public function getCompanyOfficialName(): ?string
    {
        return $this->companyOfficialName;
    }

    public function setCompanyOfficialName(?string $companyOfficialName): void
    {
        $this->companyOfficialName = $companyOfficialName;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $this->vatNumber = $vatNumber;
    }

    public function getVatPercent(): ?float
    {
        return $this->vatPercent;
    }

    public function setVatPercent(?float $vatPercent): void
    {
        $this->vatPercent = $vatPercent;
    }

    public function getExportNumber(): ?string
    {
        return $this->exportNumber;
    }

    public function setExportNumber(?string $exportNumber): void
    {
        $this->exportNumber = $exportNumber;
    }

    public function getBusinessNumber(): ?string
    {
        return $this->businessNumber;
    }

    public function setBusinessNumber(?string $businessNumber): void
    {
        $this->businessNumber = $businessNumber;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(?string $companyEmail): void
    {
        $this->companyEmail = $companyEmail;
    }

    public function getContactFirstName(): ?string
    {
        return $this->contactFirstName;
    }

    public function setContactFirstName(?string $contactFirstName): void
    {
        $this->contactFirstName = $contactFirstName;
    }

    public function getContactLastName(): ?string
    {
        return $this->contactLastName;
    }

    public function setContactLastName(?string $contactLastName): void
    {
        $this->contactLastName = $contactLastName;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): void
    {
        $this->contactEmail = $contactEmail;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): void
    {
        $this->contactPhone = $contactPhone;
    }

    public function getGeneralEmail(): ?string
    {
        return $this->generalEmail;
    }

    public function setGeneralEmail(?string $generalEmail): void
    {
        $this->generalEmail = $generalEmail;
    }

    public function getGeneralPhone(): ?string
    {
        return $this->generalPhone;
    }

    public function setGeneralPhone(?string $generalPhone): void
    {
        $this->generalPhone = $generalPhone;
    }

    public function getPurchaseEmail(): ?string
    {
        return $this->purchaseEmail;
    }

    public function setPurchaseEmail(?string $purchaseEmail): void
    {
        $this->purchaseEmail = $purchaseEmail;
    }

    public function getDefaultCc(): ?string
    {
        return $this->defaultCc;
    }

    public function setDefaultCc(?string $defaultCc): void
    {
        $this->defaultCc = $defaultCc;
    }

    public function getDefaultBcc(): ?string
    {
        return $this->defaultBcc;
    }

    public function setDefaultBcc(?string $defaultBcc): void
    {
        $this->defaultBcc = $defaultBcc;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getBillingAddressLine1(): ?string
    {
        return $this->billingAddressLine1;
    }

    public function setBillingAddressLine1(?string $billingAddressLine1): void
    {
        $this->billingAddressLine1 = $billingAddressLine1;
    }

    public function getBillingAddressLine2(): ?string
    {
        return $this->billingAddressLine2;
    }

    public function setBillingAddressLine2(?string $billingAddressLine2): void
    {
        $this->billingAddressLine2 = $billingAddressLine2;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function setBillingCity(?string $billingCity): void
    {
        $this->billingCity = $billingCity;
    }

    public function getBillingPostalCode(): ?string
    {
        return $this->billingPostalCode;
    }

    public function setBillingPostalCode(?string $billingPostalCode): void
    {
        $this->billingPostalCode = $billingPostalCode;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    public function setBillingCountry(?string $billingCountry): void
    {
        $this->billingCountry = $billingCountry;
    }

    public function getBillingContactPerson(): ?string
    {
        return $this->billingContactPerson;
    }

    public function setBillingContactPerson(?string $billingContactPerson): void
    {
        $this->billingContactPerson = $billingContactPerson;
    }

    public function getBillingContactEmail(): ?string
    {
        return $this->billingContactEmail;
    }

    public function setBillingContactEmail(?string $billingContactEmail): void
    {
        $this->billingContactEmail = $billingContactEmail;
    }

    public function getBillingNotes(): ?string
    {
        return $this->billingNotes;
    }

    public function setBillingNotes(?string $billingNotes): void
    {
        $this->billingNotes = $billingNotes;
    }

    public function getShippingAddressLine1(): ?string
    {
        return $this->shippingAddressLine1;
    }

    public function setShippingAddressLine1(?string $shippingAddressLine1): void
    {
        $this->shippingAddressLine1 = $shippingAddressLine1;
    }

    public function getShippingAddressLine2(): ?string
    {
        return $this->shippingAddressLine2;
    }

    public function setShippingAddressLine2(?string $shippingAddressLine2): void
    {
        $this->shippingAddressLine2 = $shippingAddressLine2;
    }

    public function getShippingCity(): ?string
    {
        return $this->shippingCity;
    }

    public function setShippingCity(?string $shippingCity): void
    {
        $this->shippingCity = $shippingCity;
    }

    public function getShippingPostalCode(): ?string
    {
        return $this->shippingPostalCode;
    }

    public function setShippingPostalCode(?string $shippingPostalCode): void
    {
        $this->shippingPostalCode = $shippingPostalCode;
    }

    public function getShippingCountry(): ?string
    {
        return $this->shippingCountry;
    }

    public function setShippingCountry(?string $shippingCountry): void
    {
        $this->shippingCountry = $shippingCountry;
    }

    public function getShippingContactPerson(): ?string
    {
        return $this->shippingContactPerson;
    }

    public function setShippingContactPerson(?string $shippingContactPerson): void
    {
        $this->shippingContactPerson = $shippingContactPerson;
    }

    public function getShippingContactEmail(): ?string
    {
        return $this->shippingContactEmail;
    }

    public function setShippingContactEmail(?string $shippingContactEmail): void
    {
        $this->shippingContactEmail = $shippingContactEmail;
    }

    public function getShippingNotes(): ?string
    {
        return $this->shippingNotes;
    }

    public function setShippingNotes(?string $shippingNotes): void
    {
        $this->shippingNotes = $shippingNotes;
    }

    public function getLeadTimeDays(): ?int
    {
        return $this->leadTimeDays;
    }

    public function setLeadTimeDays(?int $leadTimeDays): void
    {
        $this->leadTimeDays = $leadTimeDays;
    }

    public function getDeliveryDays(): ?string
    {
        return $this->deliveryDays;
    }

    public function setDeliveryDays(?string $deliveryDays): void
    {
        $this->deliveryDays = $deliveryDays;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): void
    {
        $this->paymentTerms = $paymentTerms;
    }

    public function getMinimumOrderValue(): ?float
    {
        return $this->minimumOrderValue;
    }

    public function setMinimumOrderValue(?float $minimumOrderValue): void
    {
        $this->minimumOrderValue = $minimumOrderValue;
    }

    public function getIncoterms(): ?string
    {
        return $this->incoterms;
    }

    public function setIncoterms(?string $incoterms): void
    {
        $this->incoterms = $incoterms;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getDiscountAgreements(): ?string
    {
        return $this->discountAgreements;
    }

    public function setDiscountAgreements(?string $discountAgreements): void
    {
        $this->discountAgreements = $discountAgreements;
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

    /**
     * Helper method to get the full contact name
     */
    public function getContactFullName(): string
    {
        $parts = array_filter([
            $this->contactFirstName,
            $this->contactLastName
        ]);

        return implode(' ', $parts);
    }

    /**
     * Helper method to get the display name for the supplier
     * Prefers trade name, falls back to official name
     */
    public function getDisplayName(): string
    {
        return $this->companyTradeName ?? $this->companyOfficialName ?? 'Unknown Supplier';
    }
}
