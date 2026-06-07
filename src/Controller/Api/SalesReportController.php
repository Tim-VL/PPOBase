<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SalesReportController
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    #[Route(path: '/api/_action/ppobase/sales-report/customers-by-orders', name: 'api.action.ppobase.sales-report.customers-by-orders', methods: ['GET'])]
    public function customersByOrders(Request $request): JsonResponse
    {
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $sql = <<<'SQL'
SELECT
    LOWER(HEX(oc.customer_id)) AS customer_id,
    oc.first_name,
    oc.last_name,
    oc.email,
    oc.company,
    COUNT(DISTINCT o.id) AS order_count,
    SUM(o.amount_total) AS total_revenue,
    MAX(o.order_date_time) AS last_order_date
FROM `order` o
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oc.customer_id IS NOT NULL
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;

        if (!$includeCancelled) {
            $sql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }

        $sql .= <<<'SQL'
GROUP BY oc.customer_id, oc.first_name, oc.last_name, oc.email, oc.company
ORDER BY order_count DESC
LIMIT 100
SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $summary = [
            'totalCustomers' => count($rows),
            'totalOrders' => 0,
            'totalRevenue' => 0.0,
            'avgOrdersPerCustomer' => 0.0,
        ];

        foreach ($rows as $row) {
            $summary['totalOrders'] += (int) ($row['order_count'] ?? 0);
            $summary['totalRevenue'] += (float) ($row['total_revenue'] ?? 0);
        }
        $summary['avgOrdersPerCustomer'] = $summary['totalCustomers'] > 0
            ? round($summary['totalOrders'] / $summary['totalCustomers'], 2)
            : 0.0;

        $top = array_slice($rows, 0, 20);
        $labels = [];
        $orderCounts = [];
        $revenues = [];
        foreach ($top as $row) {
            $labels[] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['email'] ?? '-');
            $orderCounts[] = (int) ($row['order_count'] ?? 0);
            $revenues[] = (float) ($row['total_revenue'] ?? 0);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'summary' => $summary,
                'chartData' => [
                    'labels' => $labels,
                    'orderCounts' => $orderCounts,
                    'revenues' => $revenues,
                ],
            ],
        ]);
    }

    #[Route(path: '/api/_action/ppobase/sales-report/items-by-sales', name: 'api.action.ppobase.sales-report.items-by-sales', methods: ['GET'])]
    public function itemsBySales(Request $request): JsonResponse
    {
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $productNumberExpr = "JSON_UNQUOTE(JSON_EXTRACT(oli.payload, '$.productNumber'))";

        $sql = <<<SQL
SELECT
    LOWER(HEX(oli.product_id)) AS product_id,
    oli.label AS product_name,
    {$productNumberExpr} AS product_number,
    SUM(oli.quantity) AS total_quantity,
    COUNT(DISTINCT oli.order_id) AS order_count,
    SUM(oli.total_price) AS total_revenue,
    AVG(oli.unit_price) AS avg_unit_price
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oli.type = 'product'
    AND oli.product_id IS NOT NULL
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;

        if (!$includeCancelled) {
            $sql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }

        $sql .= <<<SQL
GROUP BY oli.product_id, oli.label, {$productNumberExpr}
ORDER BY total_quantity DESC
LIMIT 100
SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $summary = [
            'totalProducts' => count($rows),
            'totalQuantity' => 0.0,
            'totalRevenue' => 0.0,
        ];
        foreach ($rows as $row) {
            $summary['totalQuantity'] += (float) ($row['total_quantity'] ?? 0);
            $summary['totalRevenue'] += (float) ($row['total_revenue'] ?? 0);
        }

        $top = array_slice($rows, 0, 20);
        $labels = [];
        $quantities = [];
        $revenues = [];
        foreach ($top as $row) {
            $labels[] = (string) ($row['product_name'] ?? '-');
            $quantities[] = (float) ($row['total_quantity'] ?? 0);
            $revenues[] = (float) ($row['total_revenue'] ?? 0);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'summary' => $summary,
                'chartData' => [
                    'labels' => $labels,
                    'quantities' => $quantities,
                    'revenues' => $revenues,
                ],
            ],
        ]);
    }

    #[Route(path: '/api/_action/ppobase/sales-report/sales-by-customer/{customerId}', name: 'api.action.ppobase.sales-report.sales-by-customer', methods: ['GET'])]
    public function salesByCustomer(string $customerId, Request $request): JsonResponse
    {
        $customerId = $this->normalizeHexId($customerId);
        if (!Uuid::isValid($customerId)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid customerId'], 400);
        }

        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $productNumberExpr = "JSON_UNQUOTE(JSON_EXTRACT(oli.payload, '$.productNumber'))";

        $itemsSql = <<<SQL
SELECT
    LOWER(HEX(oli.product_id)) AS product_id,
    oli.label AS product_name,
    {$productNumberExpr} AS product_number,
    SUM(oli.quantity) AS total_quantity,
    SUM(oli.total_price) AS total_spent,
    AVG(oli.unit_price) AS avg_price,
    COUNT(DISTINCT oli.order_id) AS order_count
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oc.customer_id = :customerId
    AND oli.type = 'product'
    AND oli.product_id IS NOT NULL
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;

        if (!$includeCancelled) {
            $itemsSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }

        $itemsSql .= <<<SQL
GROUP BY oli.product_id, oli.label, {$productNumberExpr}
ORDER BY total_spent DESC
SQL;

        $items = $this->connection->fetchAllAssociative($itemsSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customerId' => Uuid::fromHexToBytes($customerId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $customerSql = <<<'SQL'
SELECT
    oc.first_name,
    oc.last_name,
    oc.email,
    oc.company
FROM order_customer oc
JOIN `order` o ON oc.order_id = o.id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oc.customer_id = :customerId
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $customerSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $customerSql .= "\nORDER BY o.order_date_time DESC\nLIMIT 1";

        $customer = $this->connection->fetchAssociative($customerSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customerId' => Uuid::fromHexToBytes($customerId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]) ?: null;

        $monthlySql = <<<'SQL'
SELECT
    DATE_FORMAT(o.order_date_time, '%Y-%m') AS month,
    SUM(o.amount_total) AS monthly_total,
    COUNT(DISTINCT o.id) AS monthly_orders
FROM `order` o
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oc.customer_id = :customerId
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $monthlySql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $monthlySql .= "\nGROUP BY month\nORDER BY month ASC";

        $monthly = $this->connection->fetchAllAssociative($monthlySql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customerId' => Uuid::fromHexToBytes($customerId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $totalsSql = <<<'SQL'
SELECT
    COUNT(DISTINCT o.id) AS total_orders,
    SUM(o.amount_total) AS total_spent
FROM `order` o
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oc.customer_id = :customerId
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $totalsSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }

        $totals = $this->connection->fetchAssociative($totalsSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customerId' => Uuid::fromHexToBytes($customerId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]) ?: ['total_orders' => 0, 'total_spent' => 0];

        $pieLabels = [];
        $pieValues = [];
        $topForPie = array_slice($items, 0, 10);
        $otherValue = 0.0;
        foreach ($items as $idx => $row) {
            $v = (float) ($row['total_spent'] ?? 0);
            if ($idx < 10) {
                $pieLabels[] = (string) ($row['product_name'] ?? '-');
                $pieValues[] = $v;
            } else {
                $otherValue += $v;
            }
        }
        if ($otherValue > 0) {
            $pieLabels[] = 'Other';
            $pieValues[] = $otherValue;
        }

        $lineLabels = [];
        $lineRevenues = [];
        $lineOrderCounts = [];
        foreach ($monthly as $row) {
            $lineLabels[] = (string) ($row['month'] ?? '');
            $lineRevenues[] = (float) ($row['monthly_total'] ?? 0);
            $lineOrderCounts[] = (int) ($row['monthly_orders'] ?? 0);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'summary' => [
                    'totalOrders' => (int) ($totals['total_orders'] ?? 0),
                    'totalSpent' => (float) ($totals['total_spent'] ?? 0),
                ],
                'items' => $items,
                'chartData' => [
                    'pie' => [
                        'labels' => $pieLabels,
                        'values' => $pieValues,
                    ],
                    'line' => [
                        'labels' => $lineLabels,
                        'revenues' => $lineRevenues,
                        'orderCounts' => $lineOrderCounts,
                    ],
                ],
            ],
        ]);
    }

    #[Route(path: '/api/_action/ppobase/sales-report/sales-by-item/{productId}', name: 'api.action.ppobase.sales-report.sales-by-item', methods: ['GET'])]
    public function salesByItem(string $productId, Request $request): JsonResponse
    {
        $productId = $this->normalizeHexId($productId);
        if (!Uuid::isValid($productId)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid productId'], 400);
        }

        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $ordersSql = <<<'SQL'
SELECT
    LOWER(HEX(o.id)) AS order_id,
    o.order_number,
    o.order_date_time,
    oc.first_name,
    oc.last_name,
    oc.email,
    oc.company,
    oli.quantity,
    oli.unit_price,
    oli.total_price
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oli.product_id = :productId
    AND oli.type = 'product'
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $ordersSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $ordersSql .= "\nORDER BY o.order_date_time DESC\nLIMIT 500";

        $orders = $this->connection->fetchAllAssociative($ordersSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'productId' => Uuid::fromHexToBytes($productId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $productNumberExpr = "JSON_UNQUOTE(JSON_EXTRACT(oli.payload, '$.productNumber'))";
        $productSql = <<<SQL
SELECT
    oli.label AS product_name,
    {$productNumberExpr} AS product_number
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oli.product_id = :productId
    AND oli.type = 'product'
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $productSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $productSql .= "\nORDER BY o.order_date_time DESC\nLIMIT 1";

        $product = $this->connection->fetchAssociative($productSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'productId' => Uuid::fromHexToBytes($productId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]) ?: null;

        $monthlySql = <<<'SQL'
SELECT
    DATE_FORMAT(o.order_date_time, '%Y-%m') AS month,
    SUM(oli.quantity) AS monthly_quantity,
    SUM(oli.total_price) AS monthly_revenue
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oli.product_id = :productId
    AND oli.type = 'product'
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $monthlySql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $monthlySql .= "\nGROUP BY month\nORDER BY month ASC";

        $monthly = $this->connection->fetchAllAssociative($monthlySql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'productId' => Uuid::fromHexToBytes($productId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $buyersSql = <<<'SQL'
SELECT
    CONCAT(oc.first_name, ' ', oc.last_name) AS customer_name,
    SUM(oli.quantity) AS total_quantity
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oli.product_id = :productId
    AND oli.type = 'product'
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $buyersSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $buyersSql .= "\nGROUP BY customer_name\nORDER BY total_quantity DESC\nLIMIT 10";

        $buyers = $this->connection->fetchAllAssociative($buyersSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'productId' => Uuid::fromHexToBytes($productId),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $lineLabels = [];
        $lineQuantities = [];
        $lineRevenues = [];
        foreach ($monthly as $row) {
            $lineLabels[] = (string) ($row['month'] ?? '');
            $lineQuantities[] = (float) ($row['monthly_quantity'] ?? 0);
            $lineRevenues[] = (float) ($row['monthly_revenue'] ?? 0);
        }

        $barLabels = [];
        $barQuantities = [];
        foreach ($buyers as $row) {
            $barLabels[] = (string) ($row['customer_name'] ?? '-');
            $barQuantities[] = (float) ($row['total_quantity'] ?? 0);
        }

        $totalQty = 0.0;
        $totalRevenue = 0.0;
        foreach ($orders as $row) {
            $totalQty += (float) ($row['quantity'] ?? 0);
            $totalRevenue += (float) ($row['total_price'] ?? 0);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'product' => $product,
                'summary' => [
                    'totalQuantity' => $totalQty,
                    'totalRevenue' => $totalRevenue,
                ],
                'orders' => $orders,
                'chartData' => [
                    'line' => [
                        'labels' => $lineLabels,
                        'quantities' => $lineQuantities,
                        'revenues' => $lineRevenues,
                    ],
                    'bar' => [
                        'labels' => $barLabels,
                        'quantities' => $barQuantities,
                    ],
                ],
            ],
        ]);
    }

    #[Route(path: '/api/_action/ppobase/sales-report/orders-overview', name: 'api.action.ppobase.sales-report.orders-overview', methods: ['GET'])]
    public function ordersOverview(Request $request): JsonResponse
    {
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $ordersSql = <<<'SQL'
SELECT
    LOWER(HEX(o.id)) AS order_id,
    o.order_number,
    o.order_date_time,
    o.amount_total,
    o.amount_net,
    o.currency_factor,
    oc.first_name,
    oc.last_name,
    oc.email,
    oc.company,
    cf.iso_code AS currency,
    sms.technical_name AS order_status
FROM `order` o
JOIN order_customer oc ON o.id = oc.order_id AND oc.order_version_id = o.version_id
LEFT JOIN currency cf ON o.currency_id = cf.id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $ordersSql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $ordersSql .= "\nORDER BY o.order_date_time DESC\nLIMIT 500";

        $orders = $this->connection->fetchAllAssociative($ordersSql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $monthlySql = <<<'SQL'
SELECT
    DATE_FORMAT(o.order_date_time, '%Y-%m') AS month,
    COUNT(DISTINCT o.id) AS order_count,
    SUM(o.amount_total) AS total_revenue
FROM `order` o
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
SQL;
        if (!$includeCancelled) {
            $monthlySql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $monthlySql .= "\nGROUP BY month\nORDER BY month ASC";

        $monthly = $this->connection->fetchAllAssociative($monthlySql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $totalOrders = 0;
        $totalRevenue = 0.0;
        foreach ($orders as $order) {
            $totalOrders++;
            $totalRevenue += (float) ($order['amount_total'] ?? 0);
        }
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0;

        $lineLabels = [];
        $lineRevenues = [];
        $barLabels = [];
        $barOrderCounts = [];
        foreach ($monthly as $row) {
            $month = (string) ($row['month'] ?? '');
            $lineLabels[] = $month;
            $barLabels[] = $month;
            $lineRevenues[] = (float) ($row['total_revenue'] ?? 0);
            $barOrderCounts[] = (int) ($row['order_count'] ?? 0);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'summary' => [
                    'totalOrders' => $totalOrders,
                    'totalRevenue' => $totalRevenue,
                    'avgOrderValue' => $avgOrderValue,
                ],
                'chartData' => [
                    'line' => [
                        'labels' => $lineLabels,
                        'revenues' => $lineRevenues,
                    ],
                    'bar' => [
                        'labels' => $barLabels,
                        'orderCounts' => $barOrderCounts,
                    ],
                ],
            ],
        ]);
    }

    #[Route(path: '/api/_action/ppobase/sales-report/customer-search', name: 'api.action.ppobase.sales-report.customer-search', methods: ['GET'])]
    public function customerSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->query->get('term', ''));
        if ($term === '') {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $sql = <<<'SQL'
SELECT DISTINCT
    LOWER(HEX(oc.customer_id)) AS customer_id,
    oc.first_name,
    oc.last_name,
    oc.email,
    oc.company
FROM order_customer oc
JOIN `order` o ON oc.order_id = o.id AND oc.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oc.customer_id IS NOT NULL
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
    AND (
        oc.first_name LIKE :term
        OR oc.last_name LIKE :term
        OR oc.email LIKE :term
        OR oc.company LIKE :term
    )
SQL;
        if (!$includeCancelled) {
            $sql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $sql .= "\nORDER BY oc.last_name ASC\nLIMIT 20";

        $rows = $this->connection->fetchAllAssociative($sql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'term' => '%' . $term . '%',
        ]);

        return new JsonResponse(['success' => true, 'data' => $rows]);
    }

    #[Route(path: '/api/_action/ppobase/sales-report/product-search', name: 'api.action.ppobase.sales-report.product-search', methods: ['GET'])]
    public function productSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->query->get('term', ''));
        if ($term === '') {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $includeCancelled = $this->isTruthy($request->query->get('includeCancelled'));

        $productNumberExpr = "JSON_UNQUOTE(JSON_EXTRACT(oli.payload, '$.productNumber'))";

        $sql = <<<SQL
SELECT DISTINCT
    LOWER(HEX(oli.product_id)) AS product_id,
    oli.label AS product_name,
    {$productNumberExpr} AS product_number
FROM order_line_item oli
JOIN `order` o ON oli.order_id = o.id AND oli.order_version_id = o.version_id
LEFT JOIN state_machine_state sms ON o.state_id = sms.id
WHERE o.version_id = :liveVersionId
    AND oli.type = 'product'
    AND oli.product_id IS NOT NULL
    AND o.order_date_time BETWEEN :dateFrom AND :dateTo
    AND (
        oli.label LIKE :term
        OR {$productNumberExpr} LIKE :term
    )
SQL;
        if (!$includeCancelled) {
            $sql .= "\n    AND COALESCE(sms.technical_name, '') <> 'cancelled'";
        }
        $sql .= "\nORDER BY oli.label ASC\nLIMIT 20";

        $rows = $this->connection->fetchAllAssociative($sql, [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'term' => '%' . $term . '%',
        ]);

        return new JsonResponse(['success' => true, 'data' => $rows]);
    }

    private function getDateRange(Request $request): array
    {
        $today = new \DateTimeImmutable('today');
        $defaultFrom = $today->modify('-12 months');

        $dateFromRaw = (string) $request->query->get('dateFrom', $defaultFrom->format('Y-m-d'));
        $dateToRaw = (string) $request->query->get('dateTo', $today->format('Y-m-d'));

        $dateFrom = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFromRaw) ?: $defaultFrom;
        $dateTo = \DateTimeImmutable::createFromFormat('Y-m-d', $dateToRaw) ?: $today;

        $from = $dateFrom->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $to = $dateTo->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        return [$from, $to];
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === true) {
            return true;
        }
        $v = strtolower((string) $value);
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeHexId(string $id): string
    {
        return str_replace('-', '', strtolower(trim($id)));
    }
}
