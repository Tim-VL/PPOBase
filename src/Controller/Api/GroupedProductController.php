<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use Doctrine\DBAL\Connection;
use PPOBase\Service\ActivityLogService;
use PPOBase\Service\GroupedProductService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * REST API for managing grouped-product relationships.
 * All routes live under /api/_action/ppobase/grouped-products.
 *
 * @package PPOBase
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class GroupedProductController
{
    public function __construct(
        private readonly EntityRepository    $groupedProductRepository,
        private readonly GroupedProductService $groupedProductService,
        private readonly ActivityLogService  $activityLogService,
        private readonly Connection          $connection
    ) {
    }

    /**
     * GET /api/_action/ppobase/grouped-products/parents
     *
     * Returns all distinct parent product IDs that have at least one child.
     * Used by the Grouped Products navigation list page.
     */
    #[Route(
        path: '/api/_action/ppobase/grouped-products/parents',
        name: 'api.ppobase.grouped_products.parents',
        methods: ['GET']
    )]
    public function parents(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addGroupField(new FieldGrouping('parentProductId'));

        $results = $this->groupedProductRepository->search($criteria, $context);
        $data    = array_values(array_map(
            fn ($entity) => [
                'id'             => $entity->getParentProductId(),
                'isGroupedActive' => $this->groupedProductService->isGroupedActive($entity->getParentProductId()),
            ],
            $results->getElements()
        ));

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/_action/ppobase/grouped-products/list/{parentProductId}
     *
     * Returns all children for the given parent, ordered by position,
     * with product name, number and cover URL pre-loaded.
     */
    #[Route(
        path: '/api/_action/ppobase/grouped-products/list/{parentProductId}',
        name: 'api.ppobase.grouped_products.list',
        methods: ['GET']
    )]
    public function list(string $parentProductId, Context $context): JsonResponse
    {
        $children = $this->groupedProductService->getAllChildrenForProduct($parentProductId, $context);
        $active   = $this->groupedProductService->isGroupedActive($parentProductId);

        return new JsonResponse(['success' => true, 'data' => $children, 'active' => $active]);
    }

    #[Route(
        path: '/api/_action/ppobase/grouped-products/toggle-active/{parentProductId}',
        name: 'api.ppobase.grouped_products.toggle_active',
        defaults: ['_acl' => ['product.editor']],
        methods: ['POST']
    )]
    public function toggleActive(string $parentProductId, Request $request, Context $context): JsonResponse
    {
        $active = (bool) ($request->toArray()['active'] ?? true);
        $hex    = str_replace('-', '', $parentProductId);

        $this->connection->executeStatement(
            'INSERT INTO ppobase_product_extension (id, product_id, is_grouped_active, created_at)
             VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), UNHEX(:pid), :active, NOW(3))
             ON DUPLICATE KEY UPDATE is_grouped_active = VALUES(is_grouped_active), updated_at = NOW(3)',
            ['pid' => $hex, 'active' => $active ? 1 : 0]
        );

        return new JsonResponse(['success' => true, 'active' => $active]);
    }

    /**
     * POST /api/_action/ppobase/grouped-products/assign
     *
     * Body: { parentProductId, items: [{ childProductId, quantity, position }] }
     *
     * Upserts each item. Requires product.editor privilege.
     */
    #[Route(
        path: '/api/_action/ppobase/grouped-products/assign',
        name: 'api.ppobase.grouped_products.assign',
        defaults: ['_acl' => ['product.editor']],
        methods: ['POST']
    )]
    public function assign(Request $request, Context $context): JsonResponse
    {
        $payload         = json_decode($request->getContent(), true) ?: [];
        $parentProductId = $payload['parentProductId'] ?? null;
        $items           = $payload['items'] ?? [];

        if (!$parentProductId || !is_array($items)) {
            return new JsonResponse(['success' => false, 'error' => 'parentProductId and items[] are required'], 400);
        }

        $assigned = 0;
        foreach ($items as $item) {
            $childProductId = $item['childProductId'] ?? null;
            if (!$childProductId) {
                continue;
            }

            // Check if this parent→child pair already exists to decide create vs update
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('parentProductId', $parentProductId));
            $criteria->addFilter(new EqualsFilter('childProductId', $childProductId));

            $existing = $this->groupedProductRepository->search($criteria, $context)->first();

            if ($existing) {
                $this->groupedProductRepository->update([[
                    'id'       => $existing->getId(),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'position' => (int) ($item['position'] ?? 0),
                ]], $context);
            } else {
                $this->groupedProductRepository->create([[
                    'id'              => Uuid::randomHex(),
                    'parentProductId' => $parentProductId,
                    'childProductId'  => $childProductId,
                    'quantity'        => (int) ($item['quantity'] ?? 1),
                    'position'        => (int) ($item['position'] ?? 0),
                ]], $context);
                ++$assigned;
            }
        }

        if ($assigned > 0) {
            $this->activityLogService->log(
                'grouped_product',
                $parentProductId,
                'items_assigned',
                $context,
                sprintf('%d child product(s) assigned to grouped product', $assigned)
            );
        }

        // Return the refreshed full list so the admin UI can update in one round-trip
        $children = $this->groupedProductService->getAllChildrenForProduct($parentProductId, $context);

        return new JsonResponse(['success' => true, 'data' => $children]);
    }

    /**
     * PATCH /api/_action/ppobase/grouped-products/update/{id}
     *
     * Updates quantity and/or position of a single row.
     */
    #[Route(
        path: '/api/_action/ppobase/grouped-products/update/{id}',
        name: 'api.ppobase.grouped_products.update',
        defaults: ['_acl' => ['product.editor']],
        methods: ['PATCH']
    )]
    public function update(string $id, Request $request, Context $context): JsonResponse
    {
        $payload    = json_decode($request->getContent(), true) ?: [];
        $updateData = ['id' => $id];

        if (isset($payload['quantity'])) {
            $updateData['quantity'] = (int) $payload['quantity'];
        }
        if (isset($payload['position'])) {
            $updateData['position'] = (int) $payload['position'];
        }

        $this->groupedProductRepository->update([$updateData], $context);

        $this->activityLogService->log(
            'grouped_product',
            $id,
            'updated',
            $context,
            'Grouped product child quantity/position updated',
            null,
            null,
            $updateData
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * DELETE /api/_action/ppobase/grouped-products/remove/{id}
     *
     * Removes a single grouped-product row by its own ID.
     */
    #[Route(
        path: '/api/_action/ppobase/grouped-products/remove/{id}',
        name: 'api.ppobase.grouped_products.remove',
        defaults: ['_acl' => ['product.editor']],
        methods: ['DELETE']
    )]
    public function remove(string $id, Context $context): JsonResponse
    {
        $this->groupedProductRepository->delete([['id' => $id]], $context);

        $this->activityLogService->log(
            'grouped_product',
            $id,
            'deleted',
            $context,
            'Grouped product child removed'
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * DELETE /api/_action/ppobase/grouped-products/remove-all/{parentProductId}
     *
     * Removes ALL children for the given parent when the toggle is switched off.
     */
    #[Route(
        path: '/api/_action/ppobase/grouped-products/remove-all/{parentProductId}',
        name: 'api.ppobase.grouped_products.remove_all',
        defaults: ['_acl' => ['product.editor']],
        methods: ['DELETE']
    )]
    public function removeAll(string $parentProductId, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentProductId', $parentProductId));

        $ids = $this->groupedProductRepository
            ->searchIds($criteria, $context)
            ->getIds();

        if (!empty($ids)) {
            $deletePayload = array_map(fn (string $id) => ['id' => $id], $ids);
            $this->groupedProductRepository->delete($deletePayload, $context);

            $this->activityLogService->log(
                'grouped_product',
                $parentProductId,
                'deleted',
                $context,
                sprintf('All %d grouped product children removed', count($ids))
            );
        }

        return new JsonResponse(['success' => true]);
    }
}
