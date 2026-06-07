<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ActivityLogController
{
    public function __construct(
        private readonly EntityRepository $activityLogRepository
    ) {
    }

    #[Route(path: '/api/_action/ppobase/activity-log', name: 'api.action.ppobase.activity-log.list', methods: ['GET'])]
    public function list(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);

        if ($entityType = $request->query->get('entityType')) {
            $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        }
        if ($entityId = $request->query->get('entityId')) {
            $criteria->addFilter(new EqualsFilter('entityId', str_replace('-', '', $entityId)));
        }
        if ($action = $request->query->get('action')) {
            $criteria->addFilter(new EqualsFilter('action', $action));
        }

        $criteria->addSorting(new FieldSorting('loggedAt', FieldSorting::DESCENDING));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->activityLogRepository->search($criteria, $context);

        return new JsonResponse([
            'success' => true,
            'data' => array_values($result->getElements()),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $result->getTotal()],
        ]);
    }

    #[Route(path: '/api/_action/ppobase/activity-log/entity/{entityType}/{entityId}', name: 'api.action.ppobase.activity-log.by-entity', methods: ['GET'])]
    public function byEntity(string $entityType, string $entityId, Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        $criteria->addFilter(new EqualsFilter('entityId', str_replace('-', '', $entityId)));
        $criteria->addSorting(new FieldSorting('loggedAt', FieldSorting::DESCENDING));
        $criteria->setLimit(min(100, (int) $request->query->get('limit', 50)));

        $result = $this->activityLogRepository->search($criteria, $context);

        return new JsonResponse(['success' => true, 'data' => array_values($result->getElements())]);
    }
}
