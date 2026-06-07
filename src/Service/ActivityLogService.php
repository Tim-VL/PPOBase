<?php declare(strict_types=1);

namespace PPOBase\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogService
{
    /**
     * Fields that should be excluded from change logging as they're internal
     */
    private const EXCLUDED_FIELDS = ['id', 'createdAt', 'updatedAt', 'createdBy', 'updatedBy', 'versionId'];

    /**
     * Human-readable labels for common field names
     */
    private const FIELD_LABELS = [
        'companyTradeName' => 'Company Name',
        'companyOfficialName' => 'Official Name',
        'companyEmail' => 'Company Email',
        'contactEmail' => 'Contact Email',
        'contactFirstName' => 'Contact First Name',
        'contactLastName' => 'Contact Last Name',
        'contactPhone' => 'Contact Phone',
        'generalEmail' => 'General Email',
        'generalPhone' => 'General Phone',
        'purchaseEmail' => 'Purchase Email',
        'billingAddressLine1' => 'Billing Address',
        'billingCity' => 'Billing City',
        'billingPostalCode' => 'Billing Postal Code',
        'billingCountry' => 'Billing Country',
        'shippingAddressLine1' => 'Shipping Address',
        'shippingCity' => 'Shipping City',
        'shippingPostalCode' => 'Shipping Postal Code',
        'shippingCountry' => 'Shipping Country',
        'currency' => 'Currency',
        'paymentTerms' => 'Payment Terms',
        'minimumOrderValue' => 'Min. Order Value',
        'vatPercent' => 'VAT %',
        'vatNumber' => 'VAT Number',
        'leadTimeDays' => 'Lead Time (Days)',
        'deliveryDays' => 'Delivery Days',
        'incoterms' => 'Incoterms',
        'notes' => 'Notes',
        'active' => 'Active',
        'supplierNumber' => 'Supplier Number',
        'itemsIdRange' => 'Items ID Range',
        'status' => 'Status',
        'poNumber' => 'PO Number',
        'orderDate' => 'Order Date',
        'expectedDeliveryDate' => 'Expected Delivery',
        'subtotal' => 'Subtotal',
        'taxAmount' => 'Tax Amount',
        'total' => 'Total',
        'itemCount' => 'Item Count',
        'receiptNumber' => 'Receipt Number',
        'receiptDate' => 'Receipt Date',
        'invoiceReference' => 'Invoice Reference',
        'quantityExpected' => 'Qty Expected',
        'quantityReceived' => 'Qty Received',
        'quantityRejected' => 'Qty Rejected',
        'bookedPrice' => 'Booked Price',
        'quantityOrdered' => 'Qty Ordered',
        'itemsPerCarton' => 'Items per Carton',
        'cartonsPerLayer' => 'Cartons per Layer',
        'cartonLength' => 'Carton Length (cm)',
        'cartonHeight' => 'Carton Height (cm)',
        'cartonWidth' => 'Carton Width (cm)',
        'cartonWeightNet' => 'Weight Netto (kg)',
        'cartonWeightGross' => 'Weight Gross (kg)',
        'ruleNotes' => 'Rule Notes',
    ];

    public function __construct(
        private readonly EntityRepository $activityLogRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    public function log(
        string $entityType,
        string $entityId,
        string $action,
        Context $context,
        ?string $description = null,
        ?string $referenceNumber = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
        ?string $userName = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        if ($userName === null) {
            $userName = $this->extractUserName($context);
        }
        if ($userId === null) {
            $userId = $this->extractUserId($context);
        }

        // Make values human-readable
        $oldValuesJson = $oldValues ? json_encode($this->humanizeValues($oldValues)) : null;
        $newValuesJson = $newValues ? json_encode($this->humanizeValues($newValues)) : null;

        $data = [
            'id' => Uuid::randomHex(),
            'entityType' => $entityType,
            'entityId' => str_replace('-', '', $entityId),
            'action' => $action,
            'description' => $description,
            'referenceNumber' => $referenceNumber,
            'oldValues' => $oldValuesJson,
            'newValues' => $newValuesJson,
            'userId' => $userId,
            'userName' => $userName ?? 'System',
            'ipAddress' => $request?->getClientIp(),
            'loggedAt' => new \DateTime(),
        ];

        $this->activityLogRepository->create([$data], $context);
    }

    /**
     * Convert field names to human-readable labels and format values
     */
    private function humanizeValues(array $values): array
    {
        $humanized = [];
        foreach ($values as $key => $value) {
            // Skip internal fields
            if (in_array($key, self::EXCLUDED_FIELDS, true)) {
                continue;
            }

            $label = self::FIELD_LABELS[$key] ?? $this->camelToTitle($key);

            // Format the value for readability
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            } elseif ($value === null) {
                $value = '(empty)';
            }

            $humanized[$label] = $value;
        }
        return $humanized;
    }

    /**
     * Convert camelCase to Title Case
     */
    private function camelToTitle(string $input): string
    {
        $result = preg_replace('/([A-Z])/', ' $1', $input);
        return ucfirst(trim($result));
    }

    private function extractUserId(Context $context): ?string
    {
        $source = $context->getSource();
        if (method_exists($source, 'getUserId')) {
            return $source->getUserId();
        }
        return null;
    }

    private function extractUserName(Context $context): string
    {
        $source = $context->getSource();
        if (method_exists($source, 'getUserId') && $source->getUserId()) {
            return 'Admin';
        }
        return 'System';
    }

    // Supplier Methods
    public function logSupplierCreated(string $supplierId, string $supplierNumber, string $companyName, Context $context): void
    {
        $this->log('supplier', $supplierId, 'created', $context, "Supplier {$supplierNumber} ({$companyName}) created", $supplierNumber, null, ['companyName' => $companyName]);
    }

    public function logSupplierUpdated(string $supplierId, string $supplierNumber, array $changedFields, Context $context): void
    {
        $readable = [];
        foreach ($changedFields as $key => $value) {
            if (!in_array($key, self::EXCLUDED_FIELDS, true)) {
                $label = self::FIELD_LABELS[$key] ?? $this->camelToTitle($key);
                $readable[] = $label;
            }
        }
        $fieldList = implode(', ', $readable);
        $this->log('supplier', $supplierId, 'updated', $context, "Supplier {$supplierNumber} updated: {$fieldList}", $supplierNumber, null, $changedFields);
    }

    public function logSupplierDeleted(string $supplierId, string $supplierNumber, string $companyName, Context $context): void
    {
        $this->log('supplier', $supplierId, 'deleted', $context, "Supplier {$supplierNumber} ({$companyName}) deleted", $supplierNumber, ['companyName' => $companyName], null);
    }

    // Purchase Order Methods
    public function logPurchaseOrderCreated(string $poId, string $poNumber, ?string $supplierName, Context $context): void
    {
        $this->log('purchase_order', $poId, 'created', $context, "Purchase Order {$poNumber} created" . ($supplierName ? " for {$supplierName}" : ''), $poNumber, null, ['supplierName' => $supplierName]);
    }

    public function logPurchaseOrderUpdated(string $poId, string $poNumber, array $changedFields, Context $context): void
    {
        $readable = [];
        foreach ($changedFields as $key => $value) {
            if (!in_array($key, self::EXCLUDED_FIELDS, true)) {
                $label = self::FIELD_LABELS[$key] ?? $this->camelToTitle($key);
                $readable[] = $label;
            }
        }
        $fieldList = implode(', ', $readable);
        $this->log('purchase_order', $poId, 'updated', $context, "Purchase Order {$poNumber} updated: {$fieldList}", $poNumber, null, $changedFields);
    }

    public function logPurchaseOrderStatusChanged(string $poId, string $poNumber, string $oldStatus, string $newStatus, Context $context): void
    {
        $this->log('purchase_order', $poId, 'status_changed', $context, "PO {$poNumber} status: {$oldStatus} → {$newStatus}", $poNumber, ['status' => $oldStatus], ['status' => $newStatus]);
    }

    public function logPurchaseOrderDeleted(string $poId, string $poNumber, Context $context): void
    {
        $this->log('purchase_order', $poId, 'deleted', $context, "Purchase Order {$poNumber} deleted", $poNumber, null, null);
    }

    public function logPurchaseOrderExported(string $poId, string $poNumber, string $format, Context $context): void
    {
        $this->log('purchase_order', $poId, 'exported', $context, "PO {$poNumber} exported as {$format}", $poNumber, null, ['format' => $format]);
    }

    public function logPurchaseOrderEmailed(string $poId, string $poNumber, string $email, Context $context): void
    {
        $this->log('purchase_order', $poId, 'emailed', $context, "PO {$poNumber} emailed to {$email}", $poNumber, null, ['email' => $email]);
    }

    public function logPurchaseOrderItemsChanged(string $poId, string $poNumber, string $action, int $count, Context $context): void
    {
        $this->log('purchase_order', $poId, $action, $context, "{$count} item(s) {$action} on PO {$poNumber}", $poNumber, null, ['itemCount' => $count]);
    }

    // Supplier-Product Mapping
    public function logProductMapped(string $supplierId, string $supplierNumber, string $productName, Context $context): void
    {
        $this->log('supplier_product', $supplierId, 'product_mapped', $context, "Product \"{$productName}\" mapped to Supplier {$supplierNumber}", $supplierNumber, null, ['product' => $productName]);
    }

    public function logProductUnmapped(string $supplierId, string $supplierNumber, string $productName, Context $context): void
    {
        $this->log('supplier_product', $supplierId, 'product_unmapped', $context, "Product \"{$productName}\" unmapped from Supplier {$supplierNumber}", $supplierNumber, ['product' => $productName], null);
    }

    /**
     * Find the last known reference number for an entity from previous log entries.
     * Useful for delete events where the entity is already gone.
     *
     * @return array{referenceNumber: string|null}
     */
    public function findLastReference(string $entityType, string $entityId, Context $context): array
    {
        try {
            $normalizedId = str_replace('-', '', $entityId);
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('entityType', $entityType));
            $criteria->addFilter(new EqualsFilter('entityId', $normalizedId));
            $criteria->addSorting(new FieldSorting('loggedAt', FieldSorting::DESCENDING));
            $criteria->setLimit(1);

            $result = $this->activityLogRepository->search($criteria, $context);
            $lastLog = $result->first();

            if ($lastLog && $lastLog->getReferenceNumber()) {
                return ['referenceNumber' => $lastLog->getReferenceNumber()];
            }
        } catch (\Exception $e) {
            // fallback
        }

        return ['referenceNumber' => null];
    }

    // Goods Receipt Methods
    public function logGoodsReceiptCreated(string $id, string $receiptNumber, string $poNumber, Context $context): void
    {
        $this->log('goods_receipt', $id, 'created', $context, "Goods Receipt {$receiptNumber} created for PO {$poNumber}", $receiptNumber, null, ['poNumber' => $poNumber]);
    }

    public function logGoodsReceiptBooked(string $id, string $receiptNumber, string $poNumber, array $stockChanges, Context $context): void
    {
        $totalItems = 0;
        foreach ($stockChanges as $change) {
            $totalItems += $change['quantityAdded'] ?? 0;
        }
        $this->log('goods_receipt', $id, 'booked', $context, "Goods Receipt {$receiptNumber} booked: {$totalItems} items received against PO {$poNumber}", $receiptNumber, null, ['poNumber' => $poNumber, 'totalItemsReceived' => $totalItems, 'stockChanges' => count($stockChanges)]);
    }

    public function logGoodsReceiptCancelled(string $id, string $receiptNumber, Context $context): void
    {
        $this->log('goods_receipt', $id, 'cancelled', $context, "Goods Receipt {$receiptNumber} cancelled", $receiptNumber, ['status' => 'draft'], ['status' => 'cancelled']);
    }

    public function logStockChange(string $productId, string $productName, int $oldStock, int $newStock, string $receiptNumber, Context $context): void
    {
        $change = $newStock - $oldStock;
        $sign = $change >= 0 ? '+' : '';
        $this->log('stock_change', $productId, 'stock_updated', $context, "Stock updated for {$productName}: {$sign}{$change} units ({$receiptNumber})", $receiptNumber, ['stock' => $oldStock], ['stock' => $newStock]);
    }

    public function logManualStockBooking(
        string $productId,
        string $productName,
        int $oldStock,
        int $newStock,
        string $reason,
        Context $context
    ): void {
        $change = $newStock - $oldStock;
        $direction = $change >= 0 ? '+' : '';
        $this->log(
            'product_stock',
            $productId,
            'manual_stock_booking',
            $context,
            "Manual stock change for {$productName}: {$oldStock} -> {$newStock} ({$direction}{$change}). Reason: {$reason}"
        );
    }

    // Manual Order Methods
    public function logManualOrderCreated(
        string $orderId,
        string $orderNumber,
        string $customerName,
        float $total,
        Context $context
    ): void {
        $this->log(
            'manual_order',
            $orderId,
            'created',
            $context,
            "Manual order {$orderNumber} created for {$customerName} (Total: {$total})",
            $orderNumber
        );
    }

    public function logManualCustomerCreated(
        string $customerId,
        string $customerName,
        string $email,
        Context $context
    ): void {
        $this->log(
            'manual_customer',
            $customerId,
            'created',
            $context,
            "New customer created via Manual Order: {$customerName} ({$email})"
        );
    }

    public function logManualOrderExported(
        string $orderId,
        string $orderNumber,
        string $format,
        Context $context
    ): void {
        $this->log(
            'manual_order',
            $orderId,
            'exported',
            $context,
            "Manual order {$orderNumber} exported as {$format}",
            $orderNumber
        );
    }

    public function logManualOrderBulkExported(int $orderCount, string $format, Context $context): void
    {
        $bulkId = Uuid::randomHex();
        $this->log(
            'manual_order',
            $bulkId,
            'bulk_exported',
            $context,
            "Manual orders bulk exported as {$format} (Count: {$orderCount})"
        );
    }
}
