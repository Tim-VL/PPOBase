<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use PPOBase\Service\ActivityLogService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SupplierProductSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly EntityRepository $supplierProductRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $supplierRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'ppobase_supplier_product.written' => 'onSupplierProductWritten',
            'ppobase_supplier_product.deleted' => 'onSupplierProductDeleted',
        ];
    }

    public function onSupplierProductWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            $supplierId = $payload['supplierId'] ?? null;
            $productId = $payload['productId'] ?? null;

            if (!$supplierId || !$productId) {
                continue;
            }

            if ($result->getOperation() === 'insert') {
                // Resolve product name and supplier number for readable logs
                $productName = $this->resolveProductName($productId, $event->getContext());
                $supplierNumber = $this->resolveSupplierNumber($supplierId, $event->getContext());

                $this->activityLogService->logProductMapped(
                    $supplierId,
                    $supplierNumber,
                    $productName,
                    $event->getContext()
                );
            }
        }
    }

    public function onSupplierProductDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $id) {
            $this->activityLogService->log(
                'supplier_product',
                $id,
                'product_unmapped',
                $event->getContext(),
                'Product unmapped from supplier'
            );
        }
    }

    private function resolveProductName(string $productId, $context): string
    {
        try {
            $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
            if ($product) {
                $name = $product->getName();
                $number = $product->getProductNumber();
                return $name ? "{$name} ({$number})" : $number;
            }
        } catch (\Exception $e) {
            // fallback
        }
        return $productId;
    }

    private function resolveSupplierNumber(string $supplierId, $context): string
    {
        try {
            $supplier = $this->supplierRepository->search(new Criteria([$supplierId]), $context)->first();
            if ($supplier) {
                return $supplier->getSupplierNumber() ?: $supplier->getCompanyTradeName() ?: 'N/A';
            }
        } catch (\Exception $e) {
            // fallback
        }
        return 'N/A';
    }
}
