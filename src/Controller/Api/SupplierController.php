<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SupplierController extends AbstractController
{
    private EntityRepository $supplierRepository;
    private RequestCriteriaBuilder $criteriaBuilder;

    public function __construct(
        EntityRepository $supplierRepository,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->supplierRepository = $supplierRepository;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    #[Route(path: '/api/ppobase/supplier', name: 'api.ppobase.supplier.list', methods: ['GET'])]
    public function list(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);

        $searchTerm = $request->query->get('search');
        if ($searchTerm) {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('companyTradeName', $searchTerm),
                new ContainsFilter('companyOfficialName', $searchTerm),
                new ContainsFilter('contactEmail', $searchTerm),
                new ContainsFilter('generalEmail', $searchTerm),
                new ContainsFilter('supplierNumber', $searchTerm),
            ]));
        }

        $activeFilter = $request->query->get('active');
        if ($activeFilter !== null) {
            $criteria->addFilter(new EqualsFilter('active', $activeFilter === 'true'));
        }

        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = strtoupper($request->query->get('sortOrder', 'DESC'));
        $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';
        $criteria->addSorting(new FieldSorting($sortBy, $sortOrder));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->supplierRepository->search($criteria, $context);

        return new JsonResponse([
            'success' => true,
            'data' => $result->getEntities()->getElements(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result->getTotal(),
                'totalPages' => (int) ceil($result->getTotal() / $limit),
            ],
        ]);
    }

    #[Route(path: '/api/ppobase/supplier/{id}', name: 'api.ppobase.supplier.get', methods: ['GET'])]
    public function get(string $id, Context $context): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid supplier ID format'], Response::HTTP_BAD_REQUEST);
        }

        $supplier = $this->supplierRepository->search(new Criteria([$id]), $context)->first();
        if (!$supplier) {
            return new JsonResponse(['success' => false, 'error' => 'Supplier not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['success' => true, 'data' => $supplier]);
    }

    #[Route(path: '/api/ppobase/supplier', name: 'api.ppobase.supplier.create', methods: ['POST'])]
    public function create(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validateSupplierCreate($data);
        if (!empty($errors)) {
            return new JsonResponse(['success' => false, 'error' => 'Validation failed', 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $id = $data['id'] ?? Uuid::randomHex();
        $data['id'] = $id;

        if (empty($data['supplierNumber'])) {
            $data['supplierNumber'] = $this->generateSupplierNumber($context);
        }
        if (!isset($data['active'])) {
            $data['active'] = true;
        }
        if (!isset($data['vatPercent'])) {
            $data['vatPercent'] = 0;
        }
        $data['createdBy'] = $data['createdBy'] ?? 'system';

        try {
            $this->supplierRepository->create([$data], $context);
            $supplier = $this->supplierRepository->search(new Criteria([$id]), $context)->first();
            return new JsonResponse(['success' => true, 'message' => 'Supplier created successfully', 'data' => $supplier], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to create supplier: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/ppobase/supplier/{id}', name: 'api.ppobase.supplier.update', methods: ['PATCH'])]
    public function update(string $id, Request $request, Context $context): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid supplier ID format'], Response::HTTP_BAD_REQUEST);
        }

        $criteria = new Criteria([$id]);
        $existingSupplier = $this->supplierRepository->search($criteria, $context)->first();
        if (!$existingSupplier) {
            return new JsonResponse(['success' => false, 'error' => 'Supplier not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validateSupplierUpdate($data);
        if (!empty($errors)) {
            return new JsonResponse(['success' => false, 'error' => 'Validation failed', 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $data['id'] = $id;
        $data['updatedBy'] = $data['updatedBy'] ?? 'system';

        try {
            $this->supplierRepository->update([$data], $context);
            $supplier = $this->supplierRepository->search($criteria, $context)->first();
            return new JsonResponse(['success' => true, 'message' => 'Supplier updated successfully', 'data' => $supplier]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to update supplier: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/ppobase/supplier/{id}', name: 'api.ppobase.supplier.delete', methods: ['DELETE'])]
    public function delete(string $id, Context $context): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid supplier ID format'], Response::HTTP_BAD_REQUEST);
        }

        $supplier = $this->supplierRepository->search(new Criteria([$id]), $context)->first();
        if (!$supplier) {
            return new JsonResponse(['success' => false, 'error' => 'Supplier not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->supplierRepository->delete([['id' => $id]], $context);
            return new JsonResponse(['success' => true, 'message' => 'Supplier deleted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to delete supplier: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateSupplierCreate(array $data): array
    {
        $errors = [];

        if (empty($data['companyTradeName']) || !is_string($data['companyTradeName']) || trim($data['companyTradeName']) === '') {
            $errors[] = 'Company trade name is required.';
        }

        if (empty($data['companyEmail']) && empty($data['generalEmail'])) {
            $errors[] = 'At least one email address (Company Email or General Email) is required.';
        }

        if (!empty($data['companyEmail']) && !filter_var($data['companyEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Company email must be a valid email address.';
        }
        if (!empty($data['generalEmail']) && !filter_var($data['generalEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'General email must be a valid email address.';
        }
        if (!empty($data['contactEmail']) && !filter_var($data['contactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email must be a valid email address.';
        }

        if (empty($data['currency'])) {
            $errors[] = 'Currency is required for purchase order creation.';
        }

        if (isset($data['vatPercent'])) {
            $vp = $data['vatPercent'];
            if (!is_numeric($vp) || $vp < 0 || $vp > 100) {
                $errors[] = 'VAT percent must be a number between 0 and 100.';
            }
        }

        return $errors;
    }

    private function validateSupplierUpdate(array $data): array
    {
        $errors = [];

        if (array_key_exists('companyTradeName', $data) && (empty($data['companyTradeName']) || trim($data['companyTradeName']) === '')) {
            $errors[] = 'Company trade name cannot be empty.';
        }
        if (array_key_exists('currency', $data) && empty($data['currency'])) {
            $errors[] = 'Currency cannot be empty.';
        }

        if (isset($data['vatPercent'])) {
            $vp = $data['vatPercent'];
            if (!is_numeric($vp) || $vp < 0 || $vp > 100) {
                $errors[] = 'VAT percent must be a number between 0 and 100.';
            }
        }

        if (!empty($data['companyEmail']) && !filter_var($data['companyEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Company email must be a valid email address.';
        }
        if (!empty($data['generalEmail']) && !filter_var($data['generalEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'General email must be a valid email address.';
        }
        if (!empty($data['contactEmail']) && !filter_var($data['contactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email must be a valid email address.';
        }

        return $errors;
    }

    private function generateSupplierNumber(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('supplierNumber', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $lastSupplier = $this->supplierRepository->search($criteria, $context)->first();
        if (!$lastSupplier || !$lastSupplier->getSupplierNumber()) {
            return '1000';
        }

        $nextNumber = (int) $lastSupplier->getSupplierNumber() + 1;
        return (string) max($nextNumber, 1000);
    }
}
