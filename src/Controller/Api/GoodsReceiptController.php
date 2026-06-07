<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use PPOBase\Service\GoodsReceiptService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class GoodsReceiptController
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService
    ) {
    }

    #[Route(path: '/api/_action/ppobase/goods-receipt/{purchaseOrderId}/create', name: 'api.action.ppobase.goods-receipt.create', methods: ['GET'])]
    public function create(string $purchaseOrderId, Context $context): JsonResponse
    {
        try {
            $result = $this->goodsReceiptService->createFromPurchaseOrder($purchaseOrderId, $context);

            return new JsonResponse([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/goods-receipt/{id}', name: 'api.action.ppobase.goods-receipt.get', methods: ['GET'])]
    public function get(string $id, Context $context): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getGoodsReceipt($id, $context);
        if (!$receipt) {
            return new JsonResponse(['success' => false, 'error' => 'Goods receipt not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $receipt,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/goods-receipt/{id}/book', name: 'api.action.ppobase.goods-receipt.book', methods: ['POST'])]
    public function book(string $id, Context $context): JsonResponse
    {
        try {
            $result = $this->goodsReceiptService->bookReceipt($id, $context);

            return new JsonResponse([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/goods-receipt/{id}/cancel', name: 'api.action.ppobase.goods-receipt.cancel', methods: ['POST'])]
    public function cancel(string $id, Context $context): JsonResponse
    {
        try {
            $this->goodsReceiptService->cancelReceipt($id, $context);

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route(path: '/api/_action/ppobase/goods-receipt/by-po/{purchaseOrderId}', name: 'api.action.ppobase.goods-receipt.by-po', methods: ['GET'])]
    public function byPurchaseOrder(string $purchaseOrderId, Context $context): JsonResponse
    {
        $receipts = $this->goodsReceiptService->getReceiptsByPurchaseOrder($purchaseOrderId, $context);

        return new JsonResponse([
            'success' => true,
            'data' => $receipts,
        ]);
    }
}
