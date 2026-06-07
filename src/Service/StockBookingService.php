<?php declare(strict_types=1);

namespace PPOBase\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;

class StockBookingService
{
    public function __construct(
        private readonly EntityRepository $stockBookingRepository,
        private readonly EntityRepository $productRepository,
        private readonly ActivityLogService $activityLogService,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @return array{oldStock: int, newStock: int, change: int}
     */
    public function bookStockChange(
        string $productId,
        int $newStock,
        string $reason,
        ?string $notes,
        Context $context
    ): array {
        $criteria = new Criteria([$productId]);
        $product = $this->productRepository->search($criteria, $context)->first();

        if (!$product) {
            throw new \RuntimeException('Product not found.');
        }

        $oldStock = (int) ($product->get('stock') ?? 0);
        $change = $newStock - $oldStock;

        $this->productRepository->update([
            [
                'id' => $productId,
                'stock' => $newStock,
            ],
        ], $context);

        $userId = $this->extractUserId($context);
        $userName = $this->extractUserName($context);

        $this->stockBookingRepository->create([
            [
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'oldStock' => $oldStock,
                'newStock' => $newStock,
                'stockChange' => $change,
                'reason' => $reason,
                'notes' => $notes,
                'referenceType' => 'manual',
                'userId' => $userId,
                'userName' => $userName,
                'bookedAt' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
            ],
        ], $context);

        $productName = (string) ($product->get('name') ?? 'Product');
        $this->activityLogService->logManualStockBooking(
            $productId,
            $productName,
            $oldStock,
            $newStock,
            $reason,
            $context
        );

        return [
            'oldStock' => $oldStock,
            'newStock' => $newStock,
            'change' => $change,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function getStockHistory(string $productId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addSorting(new FieldSorting('bookedAt', FieldSorting::DESCENDING));

        $result = $this->stockBookingRepository->search($criteria, $context);

        return array_map(static function ($entry): array {
            $bookedAt = $entry->get('bookedAt');
            $createdAt = $entry->get('createdAt');

            return [
                'id' => $entry->get('id'),
                'productId' => $entry->get('productId'),
                'oldStock' => (int) $entry->get('oldStock'),
                'newStock' => (int) $entry->get('newStock'),
                'stockChange' => (int) $entry->get('stockChange'),
                'reason' => $entry->get('reason'),
                'notes' => $entry->get('notes'),
                'referenceType' => $entry->get('referenceType'),
                'referenceId' => $entry->get('referenceId'),
                'userId' => $entry->get('userId'),
                'userName' => $entry->get('userName'),
                'bookedAt' => $bookedAt instanceof \DateTimeInterface ? $bookedAt->format('c') : $bookedAt,
                'createdAt' => $createdAt instanceof \DateTimeInterface ? $createdAt->format('c') : $createdAt,
            ];
        }, array_values($result->getElements()));
    }

    private function extractUserId(Context $context): ?string
    {
        $source = $context->getSource();
        if (method_exists($source, 'getUserId') && $source->getUserId()) {
            return str_replace('-', '', (string) $source->getUserId());
        }

        return null;
    }

    private function extractUserName(Context $context): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $headerName = $request?->headers->get('sw-user-name');
        if ($headerName) {
            return $headerName;
        }

        $source = $context->getSource();
        if (method_exists($source, 'getUserId') && $source->getUserId()) {
            return 'Admin';
        }

        return 'System';
    }
}
