<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use PPOBase\Service\ActivityLogService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SupplierSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly EntityRepository $supplierRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'ppobase_supplier.written' => 'onSupplierWritten',
            'ppobase_supplier.deleted' => 'onSupplierDeleted',
        ];
    }

    public function onSupplierWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            $id = $payload['id'] ?? null;

            if (!$id) {
                continue;
            }

            if ($result->getOperation() === 'insert') {
                // On insert, payload has all fields
                $supplierNumber = $payload['supplierNumber'] ?? 'New';
                $companyName = $payload['companyTradeName'] ?? 'Unknown';

                $this->activityLogService->logSupplierCreated(
                    $id,
                    $supplierNumber,
                    $companyName,
                    $event->getContext()
                );
            } else {
                // On update, payload only has changed fields.
                // Fetch the full entity to get supplierNumber and companyTradeName.
                $supplier = $this->resolveSupplier($id, $event->getContext());
                $supplierNumber = $supplier['number'];
                $companyName = $supplier['name'];

                $changedFields = array_filter(
                    $payload,
                    fn($key) => !in_array($key, ['id', 'createdAt', 'updatedAt'], true),
                    ARRAY_FILTER_USE_KEY
                );

                if (!empty($changedFields)) {
                    $this->activityLogService->logSupplierUpdated(
                        $id,
                        $supplierNumber,
                        $changedFields,
                        $event->getContext()
                    );
                }
            }
        }
    }

    public function onSupplierDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $id) {
            // Entity is already deleted, try to find reference from previous log entries
            $lastKnown = $this->activityLogService->findLastReference('supplier', $id, $event->getContext());
            $supplierNumber = $lastKnown['referenceNumber'] ?? substr($id, 0, 8);

            $this->activityLogService->logSupplierDeleted(
                $id,
                $supplierNumber,
                'Deleted Supplier',
                $event->getContext()
            );
        }
    }

    /**
     * Fetch supplier from repository to get current supplierNumber and companyTradeName
     */
    private function resolveSupplier(string $id, $context): array
    {
        try {
            $supplier = $this->supplierRepository->search(new Criteria([$id]), $context)->first();
            if ($supplier) {
                return [
                    'number' => $supplier->getSupplierNumber() ?: $supplier->getCompanyTradeName() ?: substr($id, 0, 8),
                    'name' => $supplier->getCompanyTradeName() ?: 'Unknown',
                ];
            }
        } catch (\Exception $e) {
            // fallback
        }
        return ['number' => substr($id, 0, 8), 'name' => 'Unknown'];
    }
}
