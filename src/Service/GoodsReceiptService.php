<?php declare(strict_types=1);

namespace PPOBase\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class GoodsReceiptService
{
    public function __construct(
        private readonly EntityRepository $goodsReceiptRepository,
        private readonly EntityRepository $goodsReceiptItemRepository,
        private readonly EntityRepository $purchaseOrderRepository,
        private readonly EntityRepository $purchaseOrderItemRepository,
        private readonly EntityRepository $productRepository,
        private readonly ActivityLogService $activityLogService
    ) {
    }

    public function generateReceiptNumber(Context $context): string
    {
        $year = (new \DateTime())->format('Y');
        $prefix = "GR{$year}-";

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('receiptNumber', null));
        $criteria->setLimit(1);

        // Find the highest receipt number for this year
        $allCriteria = new Criteria();
        $allCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $allCriteria->setLimit(100);

        $results = $this->goodsReceiptRepository->search($allCriteria, $context);
        $maxNum = 0;

        foreach ($results->getElements() as $receipt) {
            $rn = $receipt->getReceiptNumber();
            if (str_starts_with($rn, $prefix)) {
                $num = (int) substr($rn, strlen($prefix));
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        return $prefix . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT);
    }

    public function createFromPurchaseOrder(string $purchaseOrderId, Context $context): array
    {
        $criteria = new Criteria([$purchaseOrderId]);
        $criteria->addAssociation('items');
        $criteria->addAssociation('supplier');

        $purchaseOrder = $this->purchaseOrderRepository->search($criteria, $context)->first();
        if (!$purchaseOrder) {
            throw new \RuntimeException('Purchase order not found');
        }

        $status = $purchaseOrder->getStatus();
        if (!in_array($status, ['sent', 'partial'], true)) {
            throw new \RuntimeException('Purchase order must have status "sent" or "partial" to receive goods');
        }

        $receiptId = Uuid::randomHex();
        $receiptNumber = $this->generateReceiptNumber($context);

        $receiptItems = [];
        $items = $purchaseOrder->getItems();
        if ($items) {
            foreach ($items as $poItem) {
                $quantityExpected = ($poItem->getQuantityOrdered() ?? 0) - ($poItem->getQuantityReceived() ?? 0);
                if ($quantityExpected <= 0) {
                    continue;
                }

                $receiptItems[] = [
                    'id' => Uuid::randomHex(),
                    'goodsReceiptId' => $receiptId,
                    'purchaseOrderItemId' => $poItem->getId(),
                    'productId' => $poItem->getProductId(),
                    'productNumber' => $poItem->getProductNumber(),
                    'productName' => $poItem->getProductName(),
                    'ean' => $poItem->getEan(),
                    'mpn' => $poItem->getMpn(),
                    'quantityExpected' => $quantityExpected,
                    'quantityReceived' => $quantityExpected,
                    'quantityRejected' => 0,
                    'unitPrice' => $poItem->getUnitPrice() ?? 0,
                    'bookedPrice' => $poItem->getUnitPrice() ?? 0,
                    'lineTotal' => $quantityExpected * ($poItem->getUnitPrice() ?? 0),
                ];
            }
        }

        if (empty($receiptItems)) {
            throw new \RuntimeException('No items with remaining quantity to receive');
        }

        $this->goodsReceiptRepository->create([[
            'id' => $receiptId,
            'receiptNumber' => $receiptNumber,
            'purchaseOrderId' => $purchaseOrderId,
            'supplierId' => $purchaseOrder->getSupplierId(),
            'status' => 'draft',
            'receiptDate' => (new \DateTime())->format(\DateTime::ATOM),
            'items' => $receiptItems,
        ]], $context);

        $this->activityLogService->logGoodsReceiptCreated(
            $receiptId,
            $receiptNumber,
            $purchaseOrder->getPoNumber(),
            $context
        );

        return [
            'id' => $receiptId,
            'receiptNumber' => $receiptNumber,
        ];
    }

    public function getGoodsReceipt(string $id, Context $context): ?object
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('items');
        $criteria->addAssociation('purchaseOrder');
        $criteria->addAssociation('supplier');
        $criteria->getAssociation('items')->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return $this->goodsReceiptRepository->search($criteria, $context)->first();
    }

    public function bookReceipt(string $goodsReceiptId, Context $context): array
    {
        $receipt = $this->getGoodsReceipt($goodsReceiptId, $context);
        if (!$receipt) {
            throw new \RuntimeException('Goods receipt not found');
        }

        if ($receipt->getStatus() !== 'draft') {
            throw new \RuntimeException('Only draft receipts can be booked');
        }

        $items = $receipt->getItems();
        if (!$items || $items->count() === 0) {
            throw new \RuntimeException('Receipt has no items');
        }

        $stockChanges = [];
        $totalItemsReceived = 0;

        foreach ($items as $item) {
            $qtyReceived = $item->getQuantityReceived();
            if ($qtyReceived <= 0) {
                continue;
            }

            $totalItemsReceived += $qtyReceived;

            // Update PO item quantityReceived
            $poItemId = $item->getPurchaseOrderItemId();
            if ($poItemId) {
                $poItem = $this->purchaseOrderItemRepository->search(new Criteria([$poItemId]), $context)->first();
                if ($poItem) {
                    $newQtyReceived = ($poItem->getQuantityReceived() ?? 0) + $qtyReceived;
                    $this->purchaseOrderItemRepository->update([[
                        'id' => $poItemId,
                        'quantityReceived' => $newQtyReceived,
                    ]], $context);
                }
            }

            // Update Shopware product stock
            $productId = $item->getProductId();
            if ($productId) {
                $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
                if ($product) {
                    $oldStock = $product->getStock();
                    $newStock = $oldStock + $qtyReceived;

                    $this->productRepository->update([[
                        'id' => $productId,
                        'stock' => $newStock,
                    ]], $context);

                    $stockChanges[] = [
                        'productId' => $productId,
                        'productName' => $item->getProductName(),
                        'productNumber' => $item->getProductNumber(),
                        'oldStock' => $oldStock,
                        'newStock' => $newStock,
                        'quantityAdded' => $qtyReceived,
                    ];

                    $this->activityLogService->logStockChange(
                        $productId,
                        $item->getProductName(),
                        $oldStock,
                        $newStock,
                        $receipt->getReceiptNumber(),
                        $context
                    );
                }
            }

            // Update line total based on booked price
            $bookedPrice = $item->getBookedPrice() ?? $item->getUnitPrice() ?? 0;
            $this->goodsReceiptItemRepository->update([[
                'id' => $item->getId(),
                'lineTotal' => $qtyReceived * $bookedPrice,
            ]], $context);
        }

        // Mark receipt as booked
        $this->goodsReceiptRepository->update([[
            'id' => $goodsReceiptId,
            'status' => 'booked',
        ]], $context);

        // Update PO status based on total received quantities
        $this->updatePurchaseOrderStatus($receipt->getPurchaseOrderId(), $context);

        // Fetch PO number for logging
        $po = $this->purchaseOrderRepository->search(new Criteria([$receipt->getPurchaseOrderId()]), $context)->first();
        $poNumber = $po ? $po->getPoNumber() : 'Unknown';

        $this->activityLogService->logGoodsReceiptBooked(
            $goodsReceiptId,
            $receipt->getReceiptNumber(),
            $poNumber,
            $stockChanges,
            $context
        );

        return [
            'receiptNumber' => $receipt->getReceiptNumber(),
            'totalItemsReceived' => $totalItemsReceived,
            'stockChanges' => $stockChanges,
        ];
    }

    public function cancelReceipt(string $goodsReceiptId, Context $context): void
    {
        $receipt = $this->getGoodsReceipt($goodsReceiptId, $context);
        if (!$receipt) {
            throw new \RuntimeException('Goods receipt not found');
        }

        if ($receipt->getStatus() !== 'draft') {
            throw new \RuntimeException('Only draft receipts can be cancelled');
        }

        $this->goodsReceiptRepository->update([[
            'id' => $goodsReceiptId,
            'status' => 'cancelled',
        ]], $context);

        $this->activityLogService->logGoodsReceiptCancelled(
            $goodsReceiptId,
            $receipt->getReceiptNumber(),
            $context
        );
    }

    public function getReceiptsByPurchaseOrder(string $purchaseOrderId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('purchaseOrderId', $purchaseOrderId));
        $criteria->addAssociation('items');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $results = $this->goodsReceiptRepository->search($criteria, $context);

        return array_values($results->getElements());
    }

    private function updatePurchaseOrderStatus(string $purchaseOrderId, Context $context): void
    {
        $criteria = new Criteria([$purchaseOrderId]);
        $criteria->addAssociation('items');

        $po = $this->purchaseOrderRepository->search($criteria, $context)->first();
        if (!$po || !$po->getItems()) {
            return;
        }

        $allFullyReceived = true;
        $anyReceived = false;

        foreach ($po->getItems() as $item) {
            $ordered = $item->getQuantityOrdered() ?? 0;
            $received = $item->getQuantityReceived() ?? 0;

            if ($received > 0) {
                $anyReceived = true;
            }
            if ($received < $ordered) {
                $allFullyReceived = false;
            }
        }

        if ($allFullyReceived) {
            $newStatus = 'received';
        } elseif ($anyReceived) {
            $newStatus = 'partial';
        } else {
            return;
        }

        $this->purchaseOrderRepository->update([[
            'id' => $purchaseOrderId,
            'status' => $newStatus,
        ]], $context);
    }
}
