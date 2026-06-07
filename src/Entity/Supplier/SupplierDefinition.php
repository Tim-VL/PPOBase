<?php declare(strict_types=1);

namespace PPOBase\Entity\Supplier;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Supplier Entity Definition
 * database schema and field mappings for the supplier entity.
 * 
 * The supplier entity stores all vendor/supplier information including:
 * - Basic company details (name, VAT, registration numbers)
 * - Contact information (email, phone, website)
 * - Address details (billing and shipping)
 * - Commercial terms (currency, payment terms, minimum order, incoterms)
 * - Logistics info (lead time, delivery days)
 * 
 * @package PPOBase\Entity\Supplier
 */
class SupplierDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ppobase_supplier';

    /**
     * Returns the entity name
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    /**
     * Returns the entity class that will be instantiated for each record
     */
    public function getEntityClass(): string
    {
        return SupplierEntity::class;
    }

    /**
     * Returns the collection class for multiple entities
     */
    public function getCollectionClass(): string
    {
        return SupplierCollection::class;
    }

    /**
     * Defines all fields for the supplier entity
     * 
     * Each field maps to a database column and defines:
     * - Data type (string, int, bool, etc.)
     * - Constraints (required, max length)
     * - Flags (API accessible, searchable, primary key)
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))
                ->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new BoolField('active', 'active'))
                ->addFlags(new ApiAware()),
            
            // Auto-generated supplier number (e.g., SUP-001)
            // We'll generate this in the API controller
            (new StringField('supplier_number', 'supplierNumber', 50))
                ->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            // Items ID range - for SKU assignment rules
            // Stores as string like "1000-1999" for flexibility
            (new StringField('items_id_range', 'itemsIdRange', 50))
                ->addFlags(new ApiAware()),

            // Company registration/identification number
            (new StringField('company_number', 'companyNumber', 100))
                ->addFlags(new ApiAware()),

            // Trade name - the name commonly used for the business
            (new StringField('company_trade_name', 'companyTradeName', 255))
                ->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            // Official registered company name (may differ from trade name)
            (new StringField('company_official_name', 'companyOfficialName', 255))
                ->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),

            // VAT registration number for tax purposes
            (new StringField('vat_number', 'vatNumber', 50))
                ->addFlags(new ApiAware()),

            // VAT percentage applied to purchase orders (0-100)
            (new FloatField('vat_percent', 'vatPercent'))
                ->addFlags(new ApiAware()),

            // Export license or registration number
            (new StringField('export_number', 'exportNumber', 50))
                ->addFlags(new ApiAware()),

            // Business registration number
            (new StringField('business_number', 'businessNumber', 50))
                ->addFlags(new ApiAware()),

            // Company email address
            (new StringField('company_email', 'companyEmail', 255))
                ->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),

            (new StringField('contact_first_name', 'contactFirstName', 100))
                ->addFlags(new ApiAware()),

            (new StringField('contact_last_name', 'contactLastName', 100))
                ->addFlags(new ApiAware()),

            (new StringField('contact_email', 'contactEmail', 255))
                ->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),

            (new StringField('contact_phone', 'contactPhone', 50))
                ->addFlags(new ApiAware()),

            // General company email (not specific contact person)
            (new StringField('general_email', 'generalEmail', 255))
                ->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),

            (new StringField('general_phone', 'generalPhone', 50))
                ->addFlags(new ApiAware()),

            // Purchase email (if empty, use company email for PO)
            (new StringField('purchase_email', 'purchaseEmail', 255))
                ->addFlags(new ApiAware()),

            // Default CC/BCC for PO emails (comma-separated)
            (new LongTextField('default_cc', 'defaultCc'))
                ->addFlags(new ApiAware()),

            (new LongTextField('default_bcc', 'defaultBcc'))
                ->addFlags(new ApiAware()),

            (new StringField('website', 'website', 255))
                ->addFlags(new ApiAware()),

            // General notes about the supplier
            (new LongTextField('notes', 'notes'))
                ->addFlags(new ApiAware()),

            // Address line 1 (street, number)
            (new StringField('billing_address_line1', 'billingAddressLine1', 255))
                ->addFlags(new ApiAware()),

            // Address line 2 (apartment, suite, etc.)
            (new StringField('billing_address_line2', 'billingAddressLine2', 255))
                ->addFlags(new ApiAware()),

            (new StringField('billing_city', 'billingCity', 100))
                ->addFlags(new ApiAware()),

            (new StringField('billing_postal_code', 'billingPostalCode', 20))
                ->addFlags(new ApiAware()),

            (new StringField('billing_country', 'billingCountry', 100))
                ->addFlags(new ApiAware()),

            // Billing contact (may differ from main contact)
            (new StringField('billing_contact_person', 'billingContactPerson', 200))
                ->addFlags(new ApiAware()),

            (new StringField('billing_contact_email', 'billingContactEmail', 255))
                ->addFlags(new ApiAware()),

            (new LongTextField('billing_notes', 'billingNotes'))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_address_line1', 'shippingAddressLine1', 255))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_address_line2', 'shippingAddressLine2', 255))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_city', 'shippingCity', 100))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_postal_code', 'shippingPostalCode', 20))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_country', 'shippingCountry', 100))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_contact_person', 'shippingContactPerson', 200))
                ->addFlags(new ApiAware()),

            (new StringField('shipping_contact_email', 'shippingContactEmail', 255))
                ->addFlags(new ApiAware()),

            (new LongTextField('shipping_notes', 'shippingNotes'))
                ->addFlags(new ApiAware()),


            // Average lead time in days from order to delivery
            (new IntField('lead_time_days', 'leadTimeDays'))
                ->addFlags(new ApiAware()),

            // Days of the week supplier delivers (stored as comma-separated: "Mon,Wed,Fri")
            (new StringField('delivery_days', 'deliveryDays', 100))
                ->addFlags(new ApiAware()),

            // Currency code (EUR, USD, GBP, etc.)
            (new StringField('currency', 'currency', 3))
                ->addFlags(new ApiAware()),

            // Payment terms description (e.g., "Net 30", "50% upfront, 50% on delivery")
            (new StringField('payment_terms', 'paymentTerms', 255))
                ->addFlags(new ApiAware()),

            // Minimum order value in supplier's currency
            (new FloatField('minimum_order_value', 'minimumOrderValue'))
                ->addFlags(new ApiAware()),

            // International Commercial Terms (FOB, CIF, EXW, DDP, etc.)
            (new StringField('incoterms', 'incoterms', 20))
                ->addFlags(new ApiAware()),

            // Tax ID for commercial transactions
            (new StringField('tax_id', 'taxId', 50))
                ->addFlags(new ApiAware()),

            // Discount agreements description
            // Could be percentage, volume-based, or custom arrangements
            (new LongTextField('discount_agreements', 'discountAgreements'))
                ->addFlags(new ApiAware()),

            // Track who created and last modified the record
            (new StringField('created_by', 'createdBy', 255))
                ->addFlags(new ApiAware()),

            (new StringField('updated_by', 'updatedBy', 255))
                ->addFlags(new ApiAware()),
        ]);
    }
}
