<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use PPOBase\Service\ActivityLogService;
use PPOBase\Service\StockBookingService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ProductExtensionController
{
    public function __construct(
        private readonly EntityRepository $productExtensionRepository,
        private readonly EntityRepository $stockBookingRepository,
        private readonly EntityRepository $supplierProductRepository,
        private readonly EntityRepository $purchaseOrderItemRepository,
        private readonly EntityRepository $goodsReceiptItemRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $purchaseOrderRepository,
        private readonly EntityRepository $supplierRepository,
        private readonly StockBookingService $stockBookingService,
        private readonly ActivityLogService $activityLogService,
        private readonly EntityRepository $orderLineItemRepository
    ) {
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}', name: 'api.action.ppobase.product-extension.get', methods: ['GET'])]
    public function getProductExtension(string $productId, Context $context): JsonResponse
    {
        $extension = $this->findOrCreateProductExtension($productId, $context);

        return new JsonResponse([
            'success' => true,
            'data' => $extension,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}', name: 'api.action.ppobase.product-extension.save', methods: ['POST'])]
    public function saveProductExtension(string $productId, Request $request, Context $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: [];
            $existing = $this->findProductExtensionEntity($productId, $context);

            if (!$existing) {
                $existingId = Uuid::randomHex();
                $this->productExtensionRepository->create([[
                    'id' => $existingId,
                    'productId' => $productId,
                    'itemsPerCarton' => 0,
                    'cartonsPerLayer' => 0,
                    'cartonLength' => 0.0,
                    'cartonHeight' => 0.0,
                    'cartonWidth' => 0.0,
                    'cartonWeightNet' => 0.0,
                    'cartonWeightGross' => 0.0,
                    'ruleNotes' => null,
                ]], $context);
                $existing = $this->productExtensionRepository->search(new Criteria([$existingId]), $context)->first();
            }

            $updateData = [
                'id' => $existing->get('id'),
                'itemsPerCarton' => (int) ($payload['itemsPerCarton'] ?? 0),
                'cartonsPerLayer' => (int) ($payload['cartonsPerLayer'] ?? 0),
                'cartonLength' => (float) ($payload['cartonLength'] ?? 0),
                'cartonHeight' => (float) ($payload['cartonHeight'] ?? 0),
                'cartonWidth' => (float) ($payload['cartonWidth'] ?? 0),
                'cartonWeightNet' => (float) ($payload['cartonWeightNet'] ?? 0),
                'cartonWeightGross' => (float) ($payload['cartonWeightGross'] ?? 0),
                'ruleNotes' => $payload['ruleNotes'] ?? null,
            ];

            $this->productExtensionRepository->update([$updateData], $context);

            $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
            $productName = (string) ($product?->get('name') ?? 'Product');

            $this->activityLogService->log(
                'product_extension',
                $productId,
                'updated',
                $context,
                "Packaging data updated for {$productName}",
                null,
                [
                    'itemsPerCarton' => $existing->get('itemsPerCarton'),
                    'cartonsPerLayer' => $existing->get('cartonsPerLayer'),
                    'cartonLength' => $existing->get('cartonLength'),
                    'cartonHeight' => $existing->get('cartonHeight'),
                    'cartonWidth' => $existing->get('cartonWidth'),
                    'cartonWeightNet' => $existing->get('cartonWeightNet'),
                    'cartonWeightGross' => $existing->get('cartonWeightGross'),
                    'ruleNotes' => $existing->get('ruleNotes'),
                ],
                [
                    'itemsPerCarton' => $updateData['itemsPerCarton'],
                    'cartonsPerLayer' => $updateData['cartonsPerLayer'],
                    'cartonLength' => $updateData['cartonLength'],
                    'cartonHeight' => $updateData['cartonHeight'],
                    'cartonWidth' => $updateData['cartonWidth'],
                    'cartonWeightNet' => $updateData['cartonWeightNet'],
                    'cartonWeightGross' => $updateData['cartonWeightGross'],
                    'ruleNotes' => $updateData['ruleNotes'],
                ]
            );

            return new JsonResponse([
                'success' => true,
                'data' => $this->findOrCreateProductExtension($productId, $context),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/stock-booking', name: 'api.action.ppobase.product-extension.stock-booking', methods: ['POST'])]
    public function createStockBooking(string $productId, Request $request, Context $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: [];
            $newStock = (int) ($payload['newStock'] ?? 0);
            $reason = trim((string) ($payload['reason'] ?? 'Manual adjustment'));
            $notes = $payload['notes'] ?? null;

            $result = $this->stockBookingService->bookStockChange($productId, $newStock, $reason, $notes, $context);

            return new JsonResponse([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/stock-bookings', name: 'api.action.ppobase.product-extension.stock-bookings', methods: ['GET'])]
    public function getStockBookings(string $productId, Context $context): JsonResponse
    {
        $entries = $this->stockBookingService->getStockHistory($productId, $context);

        return new JsonResponse([
            'success' => true,
            'data' => $entries,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/purchase-orders', name: 'api.action.ppobase.product-extension.purchase-orders', methods: ['GET'])]
    public function getPurchaseOrders(string $productId, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addAssociation('purchaseOrder');
        $criteria->addAssociation('purchaseOrder.supplier');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->purchaseOrderItemRepository->search($criteria, $context);
        $rows = [];

        foreach ($result->getElements() as $item) {
            $po = $item->get('purchaseOrder');
            $supplier = $po ? $po->get('supplier') : null;
            $rows[] = [
                'id' => $item->get('id'),
                'purchaseOrderId' => $po?->get('id') ?? $item->get('purchaseOrderId'),
                'poNumber' => $po?->get('poNumber'),
                'poStatus' => $po?->get('status'),
                'orderDate' => self::normalizeDateValue($po?->get('orderDate')),
                'expectedDeliveryDate' => self::normalizeDateValue($po?->get('expectedDeliveryDate')),
                'supplierName' => $supplier?->get('companyTradeName') ?? $supplier?->get('companyOfficialName') ?? '-',
                'quantityOrdered' => $item->get('quantityOrdered'),
                'quantityReceived' => $item->get('quantityReceived'),
                'unitPrice' => $item->get('unitPrice'),
                'unit' => $item->get('unit'),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $rows,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/goods-receipts', name: 'api.action.ppobase.product-extension.goods-receipts', methods: ['GET'])]
    public function getGoodsReceipts(string $productId, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addAssociation('goodsReceipt');
        $criteria->addAssociation('goodsReceipt.purchaseOrder');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->goodsReceiptItemRepository->search($criteria, $context);
        $rows = [];

        foreach ($result->getElements() as $item) {
            $gr = $item->get('goodsReceipt');
            $po = $gr ? $gr->get('purchaseOrder') : null;

            $rows[] = [
                'id' => $item->get('id'),
                'goodsReceiptId' => $item->get('goodsReceiptId'),
                'receiptNumber' => $gr?->get('receiptNumber'),
                'status' => $gr?->get('status'),
                'receiptDate' => self::normalizeDateValue($gr?->get('receiptDate')),
                'purchaseOrderId' => $gr?->get('purchaseOrderId'),
                'poNumber' => $po?->get('poNumber'),
                'quantityReceived' => $item->get('quantityReceived'),
                'bookedPrice' => $item->get('bookedPrice'),
                'lineTotal' => $item->get('lineTotal'),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $rows,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/suppliers', name: 'api.action.ppobase.product-extension.suppliers', methods: ['GET'])]
    public function getSuppliers(string $productId, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addAssociation('supplier');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->supplierProductRepository->search($criteria, $context);
        $rows = [];

        foreach ($result->getElements() as $entry) {
            $supplier = $entry->get('supplier');
            $rows[] = [
                'id'             => $entry->get('id'),
                'supplierId'     => $entry->get('supplierId'),
                'supplierName'   => $supplier?->get('companyTradeName') ?? $supplier?->get('companyOfficialName') ?? '-',
                'idRange'        => $supplier?->get('itemsIdRange') ?? '',
                'contactEmail'   => $supplier?->get('contactEmail') ?? '',
                'phone'          => $supplier?->get('generalPhone') ?? '',
                'currency'       => $supplier?->get('currency') ?? $entry->get('supplierCurrency') ?? '',
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $rows,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/suppliers', name: 'api.action.ppobase.product-extension.suppliers.add', methods: ['POST'])]
    public function addSupplier(string $productId, Request $request, Context $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: [];

            $supplierId = (string) ($payload['supplierId'] ?? '');
            if (!$supplierId) {
                throw new \RuntimeException('supplierId is required.');
            }

            $this->supplierProductRepository->create([[
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'supplierId' => $supplierId,
                'supplierSku' => $payload['supplierSku'] ?? null,
                'supplierPrice' => (float) ($payload['supplierPrice'] ?? 0),
                'supplierCurrency' => $payload['supplierCurrency'] ?? 'EUR',
            ]], $context);

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/suppliers/{supplierProductId}', name: 'api.action.ppobase.product-extension.suppliers.delete', methods: ['DELETE'])]
    public function removeSupplier(string $productId, string $supplierProductId, Context $context): JsonResponse
    {
        try {
            $criteria = new Criteria([$supplierProductId]);
            $entry = $this->supplierProductRepository->search($criteria, $context)->first();
            if (!$entry) {
                throw new \RuntimeException('Supplier mapping not found.');
            }
            if ((string) $entry->get('productId') !== $productId) {
                throw new \RuntimeException('Supplier mapping does not belong to this product.');
            }

            $this->supplierProductRepository->delete([['id' => $supplierProductId]], $context);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/stock-history', name: 'api.action.ppobase.product-extension.stock-history', methods: ['GET'])]
    public function getStockHistory(string $productId, Request $request, Context $context): JsonResponse
    {
        $includeAll = filter_var((string) $request->query->get('includeAll', 'false'), FILTER_VALIDATE_BOOL);
        $rows = [];

        $bookingCriteria = new Criteria();
        $bookingCriteria->addFilter(new EqualsFilter('productId', $productId));
        $bookingCriteria->addSorting(new FieldSorting('bookedAt', FieldSorting::DESCENDING));
        $bookings = $this->stockBookingRepository->search($bookingCriteria, $context);

        foreach ($bookings->getElements() as $booking) {
            $rows[] = [
                'date' => self::normalizeDateValue($booking->get('bookedAt') ?: $booking->get('createdAt')),
                'type' => 'manual_booking',
                'reference' => $booking->get('reason') ?: 'Manual booking',
                'referenceId' => $booking->get('id'),
                'quantityChange' => (int) $booking->get('stockChange'),
                'stockAfter' => (int) $booking->get('newStock'),
                'unitPrice' => null,
                'user' => $booking->get('userName') ?: 'System',
            ];
        }

        $grCriteria = new Criteria();
        $grCriteria->addFilter(new EqualsFilter('productId', $productId));
        $grCriteria->addAssociation('goodsReceipt');
        $grCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $grItems = $this->goodsReceiptItemRepository->search($grCriteria, $context);

        foreach ($grItems->getElements() as $grItem) {
            $gr = $grItem->get('goodsReceipt');
            $bookedPrice = $grItem->get('bookedPrice');
            $rows[] = [
                'date' => self::normalizeDateValue($gr?->get('receiptDate') ?: $grItem->get('createdAt')),
                'type' => 'goods_receipt',
                'reference' => $gr?->get('receiptNumber') ?: 'Goods receipt',
                'referenceId' => $gr?->get('id'),
                'quantityChange' => (int) ($grItem->get('quantityReceived') ?? 0),
                'stockAfter' => null,
                'unitPrice' => $bookedPrice !== null ? (float) $bookedPrice : null,
                'user' => $gr?->get('updatedBy') ?: $gr?->get('createdBy') ?: 'System',
            ];
        }

        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('productId', $productId));
        $orderCriteria->addFilter(new EqualsFilter('type', 'product'));
        $orderCriteria->addAssociation('order');
        $orderCriteria->addAssociation('order.stateMachineState');

        if (!$includeAll) {
            $orderCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('order.stateMachineState.technicalName', ['cancelled', 'refunded']),
            ]));
        }

        $orderCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $orderItems = $this->orderLineItemRepository->search($orderCriteria, $context);

        foreach ($orderItems->getElements() as $orderItem) {
            $order = $orderItem->get('order');
            $unitPrice = $orderItem->get('unitPrice');
            $rows[] = [
                'date' => self::normalizeDateValue($order?->get('orderDateTime') ?: $orderItem->get('createdAt')),
                'type' => 'sales_order',
                'reference' => $order?->get('orderNumber') ?: 'Order',
                'referenceId' => $order?->get('id'),
                'quantityChange' => -1 * (int) ($orderItem->get('quantity') ?? 0),
                'stockAfter' => null,
                'unitPrice' => $unitPrice !== null ? (float) $unitPrice : null,
                'user' => 'Customer',
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $aTime = isset($a['date']) ? (strtotime((string) $a['date']) ?: 0) : 0;
            $bTime = isset($b['date']) ? (strtotime((string) $b['date']) ?: 0) : 0;

            return $bTime <=> $aTime;
        });

        return new JsonResponse([
            'success' => true,
            'data' => $rows,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/product-extension/{productId}/add-to-po', name: 'api.action.ppobase.product-extension.add-to-po', methods: ['POST'])]
    public function addProductToPo(string $productId, Request $request, Context $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?: [];
            $supplierId = (string) ($payload['supplierId'] ?? '');
            $quantity = (int) ($payload['quantity'] ?? 0);
            $unit = trim((string) ($payload['unit'] ?? 'pcs'));
            $purchaseOrderId = $payload['purchaseOrderId'] ?? null;

            if (!$supplierId) {
                throw new \RuntimeException('supplierId is required.');
            }
            if ($quantity <= 0) {
                throw new \RuntimeException('quantity must be greater than 0.');
            }

            $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
            if (!$product) {
                throw new \RuntimeException('Product not found.');
            }

            $supplier = $this->supplierRepository->search(new Criteria([$supplierId]), $context)->first();
            if (!$supplier) {
                throw new \RuntimeException('Supplier not found.');
            }

            $mappingCriteria = new Criteria();
            $mappingCriteria->addFilter(new EqualsFilter('productId', $productId));
            $mappingCriteria->addFilter(new EqualsFilter('supplierId', $supplierId));
            $supplierMapping = $this->supplierProductRepository->search($mappingCriteria, $context)->first();

            $purchaseOrder = null;
            if ($purchaseOrderId) {
                $purchaseOrder = $this->purchaseOrderRepository->search(new Criteria([(string) $purchaseOrderId]), $context)->first();
                if (!$purchaseOrder) {
                    throw new \RuntimeException('Purchase order not found.');
                }
            }

            if (!$purchaseOrder) {
                $newPoId = Uuid::randomHex();
                $poNumber = $this->generatePoNumber($context);
                $currency = (string) ($supplier->get('currency') ?: 'EUR');

                $this->purchaseOrderRepository->create([[
                    'id' => $newPoId,
                    'poNumber' => $poNumber,
                    'supplierId' => $supplierId,
                    'status' => 'draft',
                    'orderDate' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
                    'currency' => $currency,
                    'subtotal' => 0,
                    'taxAmount' => 0,
                    'total' => 0,
                    'itemCount' => 0,
                    'emailSent' => false,
                    'createdBy' => 'admin',
                ]], $context);

                $purchaseOrderId = $newPoId;
                $purchaseOrder = $this->purchaseOrderRepository->search(new Criteria([$purchaseOrderId]), $context)->first();
            }

            $unitPrice = (float) ($supplierMapping?->get('supplierPrice') ?? 0);
            $lineTotal = $unitPrice * $quantity;

            $this->purchaseOrderItemRepository->create([[
                'id' => Uuid::randomHex(),
                'purchaseOrderId' => $purchaseOrderId,
                'productId' => $productId,
                'productNumber' => $product->get('productNumber') ?: null,
                'productName' => $product->get('name') ?: 'Product',
                'ean' => $product->get('ean') ?: null,
                'mpn' => $product->get('manufacturerNumber') ?: null,
                'supplierSku' => $supplierMapping?->get('supplierSku'),
                'quantityOrdered' => $quantity,
                'quantityReceived' => 0,
                'unit' => $unit,
                'unitPrice' => $unitPrice,
                'taxRate' => (float) ($supplier->get('vatPercent') ?? 0),
                'taxAmount' => round($lineTotal * ((float) ($supplier->get('vatPercent') ?? 0) / 100), 2),
                'lineTotal' => $lineTotal,
            ]], $context);

            $this->recalculatePurchaseOrderTotals((string) $purchaseOrderId, $context);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'purchaseOrderId' => $purchaseOrderId,
                    'poNumber' => $purchaseOrder?->get('poNumber'),
                ],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function findProductExtensionEntity(string $productId, Context $context): mixed
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));

        return $this->productExtensionRepository->search($criteria, $context)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrCreateProductExtension(string $productId, Context $context): array
    {
        $entity = $this->findProductExtensionEntity($productId, $context);

        if (!$entity) {
            $newId = Uuid::randomHex();
            $this->productExtensionRepository->create([[
                'id' => $newId,
                'productId' => $productId,
                'itemsPerCarton' => 0,
                'cartonsPerLayer' => 0,
                'cartonLength' => 0.0,
                'cartonHeight' => 0.0,
                'cartonWidth' => 0.0,
                'cartonWeightNet' => 0.0,
                'cartonWeightGross' => 0.0,
                'ruleNotes' => null,
            ]], $context);

            $entity = $this->productExtensionRepository->search(new Criteria([$newId]), $context)->first();
        }

        return [
            'id' => $entity->get('id'),
            'productId' => $entity->get('productId'),
            'itemsPerCarton' => (int) ($entity->get('itemsPerCarton') ?? 0),
            'cartonsPerLayer' => (int) ($entity->get('cartonsPerLayer') ?? 0),
            'cartonLength' => (float) ($entity->get('cartonLength') ?? 0),
            'cartonHeight' => (float) ($entity->get('cartonHeight') ?? 0),
            'cartonWidth' => (float) ($entity->get('cartonWidth') ?? 0),
            'cartonWeightNet' => (float) ($entity->get('cartonWeightNet') ?? 0),
            'cartonWeightGross' => (float) ($entity->get('cartonWeightGross') ?? 0),
            'ruleNotes' => $entity->get('ruleNotes'),
        ];
    }

    private function generatePoNumber(Context $context): string
    {
        $year = (new \DateTimeImmutable())->format('Y');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('poNumber', 'PO' . $year . '-'));
        $criteria->addSorting(new FieldSorting('poNumber', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $result = $this->purchaseOrderRepository->search($criteria, $context);
        if ($result->getTotal() <= 0) {
            return 'PO' . $year . '-0001';
        }

        $lastPo = $result->first();
        $lastNumber = 0;

        if ($lastPo && $lastPo->get('poNumber')) {
            $parts = explode('-', (string) $lastPo->get('poNumber'));
            $lastNumber = (int) ($parts[1] ?? 0);
        }

        return 'PO' . $year . '-' . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    private function recalculatePurchaseOrderTotals(string $purchaseOrderId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('purchaseOrderId', $purchaseOrderId));
        $items = $this->purchaseOrderItemRepository->search($criteria, $context);

        $subtotal = 0.0;
        $taxAmount = 0.0;
        $count = 0;

        foreach ($items->getElements() as $item) {
            $subtotal += (float) ($item->get('lineTotal') ?? 0);
            $taxAmount += (float) ($item->get('taxAmount') ?? 0);
            $count++;
        }

        $this->purchaseOrderRepository->update([[
            'id' => $purchaseOrderId,
            'subtotal' => $subtotal,
            'taxAmount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'itemCount' => $count,
        ]], $context);
    }

    private static function normalizeDateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
