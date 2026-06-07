<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use PPOBase\Service\ActivityLogService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GoodsReceiptSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly EntityRepository $goodsReceiptRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'ppobase_goods_receipt.written' => 'onGoodsReceiptWritten',
            'ppobase_goods_receipt.deleted' => 'onGoodsReceiptDeleted',
        ];
    }

    public function onGoodsReceiptWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            $id = $payload['id'] ?? null;

            if (!$id) {
                continue;
            }

            // Skip inserts (logged by GoodsReceiptService) and status changes (logged by service)
            if ($result->getOperation() === 'insert') {
                continue;
            }

            // On update, log changed fields (except status changes handled by service)
            if (isset($payload['status'])) {
                continue;
            }

            $receiptNumber = $this->resolveReceiptNumber($id, $event->getContext());

            $changedFields = array_filter(
                $payload,
                fn($key) => !in_array($key, ['id', 'createdAt', 'updatedAt'], true),
                ARRAY_FILTER_USE_KEY
            );

            if (!empty($changedFields)) {
                $readable = [];
                foreach ($changedFields as $key => $value) {
                    if (!in_array($key, ['id', 'createdAt', 'updatedAt', 'createdBy', 'updatedBy'], true)) {
                        $readable[] = ucfirst(preg_replace('/([A-Z])/', ' $1', $key));
                    }
                }
                $fieldList = implode(', ', $readable);

                $this->activityLogService->log(
                    'goods_receipt',
                    $id,
                    'updated',
                    $event->getContext(),
                    "Goods Receipt {$receiptNumber} updated: {$fieldList}",
                    $receiptNumber,
                    null,
                    $changedFields
                );
            }
        }
    }

    public function onGoodsReceiptDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $id) {
            $lastKnown = $this->activityLogService->findLastReference('goods_receipt', $id, $event->getContext());
            $receiptNumber = $lastKnown['referenceNumber'] ?? substr($id, 0, 8);

            $this->activityLogService->log(
                'goods_receipt',
                $id,
                'deleted',
                $event->getContext(),
                "Goods Receipt {$receiptNumber} deleted",
                $receiptNumber,
                null,
                null
            );
        }
    }

    private function resolveReceiptNumber(string $id, $context): string
    {
        try {
            $receipt = $this->goodsReceiptRepository->search(new Criteria([$id]), $context)->first();
            if ($receipt) {
                return $receipt->getReceiptNumber() ?: substr($id, 0, 8);
            }
        } catch (\Exception $e) {
            // fallback
        }
        return substr($id, 0, 8);
    }
}
