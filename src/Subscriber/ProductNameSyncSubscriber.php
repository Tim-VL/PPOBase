<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Syncs product name changes from Shopware products to PO items.
 * When a product name is updated in Shopware, all purchase order items
 * referencing that product will have their productName updated as well.
 */
class ProductNameSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $purchaseOrderItemRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();

            // Only process updates that include a name change
            if ($result->getOperation() !== 'update') {
                continue;
            }

            $productId = $payload['id'] ?? null;
            $productName = $payload['name'] ?? null;

            if (!$productId || !$productName) {
                continue;
            }

            // Find all PO items linked to this product and update the productName
            try {
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('productId', $productId));
                $criteria->setLimit(500);

                $items = $this->purchaseOrderItemRepository->search($criteria, $event->getContext());

                if ($items->getTotal() === 0) {
                    continue;
                }

                $updates = [];
                foreach ($items as $item) {
                    // Only update if name actually differs
                    if ($item->getProductName() !== $productName) {
                        $updates[] = [
                            'id' => $item->getId(),
                            'productName' => $productName,
                        ];
                    }
                }

                if (!empty($updates)) {
                    $this->purchaseOrderItemRepository->update($updates, $event->getContext());
                }
            } catch (\Exception $e) {
                // Silently fail - don't block product save
            }
        }
    }
}
