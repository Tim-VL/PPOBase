<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use PPOBase\Service\ActivityLogService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PurchaseOrderSubscriber implements EventSubscriberInterface
{
    private array $statusCache = [];

    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly EntityRepository $purchaseOrderRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'ppobase_purchase_order.written' => 'onPurchaseOrderWritten',
            'ppobase_purchase_order.deleted' => 'onPurchaseOrderDeleted',
            'ppobase_purchase_order_item.written' => 'onPurchaseOrderItemWritten',
            'ppobase_purchase_order_item.deleted' => 'onPurchaseOrderItemDeleted',
        ];
    }

    public function onPurchaseOrderWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            $id = $payload['id'] ?? null;

            if (!$id) {
                continue;
            }

            if ($result->getOperation() === 'insert') {
                // On insert, payload has all fields
                $poNumber = $payload['poNumber'] ?? 'New';

                $this->activityLogService->logPurchaseOrderCreated(
                    $id,
                    $poNumber,
                    null,
                    $event->getContext()
                );
            } else {
                // On update, payload only has changed fields.
                // Fetch the full entity to get poNumber.
                $poNumber = $this->resolvePoNumber($id, $event->getContext());

                // Check if status changed
                if (isset($payload['status'])) {
                    $oldStatus = $this->statusCache[$id] ?? 'unknown';
                    $newStatus = $payload['status'];

                    if ($oldStatus !== $newStatus && $oldStatus !== 'unknown') {
                        $this->activityLogService->logPurchaseOrderStatusChanged(
                            $id,
                            $poNumber,
                            $oldStatus,
                            $newStatus,
                            $event->getContext()
                        );
                        return; // Status change is logged separately, skip generic update
                    }
                }

                $changedFields = array_filter(
                    $payload,
                    fn($key) => !in_array($key, ['id', 'createdAt', 'updatedAt'], true),
                    ARRAY_FILTER_USE_KEY
                );

                if (!empty($changedFields)) {
                    $this->activityLogService->logPurchaseOrderUpdated(
                        $id,
                        $poNumber,
                        $changedFields,
                        $event->getContext()
                    );
                }
            }
        }
    }

    public function onPurchaseOrderDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $id) {
            // Entity is already deleted, try to find reference from previous log entries
            $lastKnown = $this->activityLogService->findLastReference('purchase_order', $id, $event->getContext());
            $poNumber = $lastKnown['referenceNumber'] ?? substr($id, 0, 8);

            $this->activityLogService->logPurchaseOrderDeleted(
                $id,
                $poNumber,
                $event->getContext()
            );
        }
    }

    public function onPurchaseOrderItemWritten(EntityWrittenEvent $event): void
    {
        $poItems = [];
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            $poId = $payload['purchaseOrderId'] ?? null;

            if (!$poId) {
                continue;
            }

            if (!isset($poItems[$poId])) {
                $poItems[$poId] = ['added' => 0, 'updated' => 0];
            }

            if ($result->getOperation() === 'insert') {
                $poItems[$poId]['added']++;
            } else {
                $poItems[$poId]['updated']++;
            }
        }

        foreach ($poItems as $poId => $counts) {
            // Resolve PO number from repository
            $poNumber = $this->resolvePoNumber($poId, $event->getContext());

            if ($counts['added'] > 0) {
                $this->activityLogService->logPurchaseOrderItemsChanged(
                    $poId,
                    $poNumber,
                    'items_added',
                    $counts['added'],
                    $event->getContext()
                );
            }
        }
    }

    public function onPurchaseOrderItemDeleted(EntityDeletedEvent $event): void
    {
        // Items deleted — we don't have the PO ID from the delete event payload,
        // so log generically. The item is already gone.
        $count = count($event->getIds());
        if ($count > 0) {
            $this->activityLogService->log(
                'purchase_order_item',
                $event->getIds()[0],
                'items_removed',
                $event->getContext(),
                "{$count} item(s) removed from Purchase Order"
            );
        }
    }

    public function cacheStatus(string $poId, string $status): void
    {
        $this->statusCache[$poId] = $status;
    }

    /**
     * Fetch PO from repository to get current poNumber
     */
    private function resolvePoNumber(string $poId, $context): string
    {
        try {
            $po = $this->purchaseOrderRepository->search(new Criteria([$poId]), $context)->first();
            if ($po) {
                return $po->getPoNumber() ?: substr($poId, 0, 8);
            }
        } catch (\Exception $e) {
            // fallback
        }
        return substr($poId, 0, 8);
    }
}
