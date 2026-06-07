<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use PPOBase\Service\ActivityLogService;
use PPOBase\Service\ManualOrderService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class ManualOrderController
{
    public function __construct(
        private readonly ManualOrderService $manualOrderService,
        private readonly ActivityLogService $activityLogService
    ) {
    }

    #[Route(path: '/api/_action/ppobase/manual-order/create', name: 'api.action.ppobase.manual-order.create', methods: ['POST'])]
    public function createOrder(Request $request, Context $context): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            // Validate required fields
            if (empty($payload['salesChannelId'])) {
                return new JsonResponse(['success' => false, 'error' => 'salesChannelId is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($payload['customerId'])) {
                return new JsonResponse(['success' => false, 'error' => 'customerId is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($payload['items']) || !is_array($payload['items'])) {
                return new JsonResponse(['success' => false, 'error' => 'At least one item is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($payload['shippingMethodId'])) {
                return new JsonResponse(['success' => false, 'error' => 'shippingMethodId is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($payload['paymentMethodId'])) {
                return new JsonResponse(['success' => false, 'error' => 'paymentMethodId is required'], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->manualOrderService->createOrder($payload, $context);

            return new JsonResponse($result);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/create-customer', name: 'api.action.ppobase.manual-order.create-customer', methods: ['POST'])]
    public function createCustomer(Request $request, Context $context): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $salesChannelId = $data['salesChannelId'] ?? null;
            if (!$salesChannelId) {
                return new JsonResponse(['success' => false, 'error' => 'salesChannelId is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['salutationId'])) {
                return new JsonResponse(['success' => false, 'error' => 'salutationId is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['firstName']) || empty($data['lastName']) || empty($data['email'])) {
                return new JsonResponse(['success' => false, 'error' => 'firstName, lastName, and email are required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['street']) || empty($data['zipcode']) || empty($data['city'])) {
                return new JsonResponse(['success' => false, 'error' => 'street, zipcode, and city are required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['countryId'])) {
                return new JsonResponse(['success' => false, 'error' => 'countryId is required'], Response::HTTP_BAD_REQUEST);
            }

            $customerId = $this->manualOrderService->createCustomer($data, $salesChannelId, $context);

            return new JsonResponse([
                'success' => true,
                'customerId' => $customerId,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/create-address/{customerId}', name: 'api.action.ppobase.manual-order.create-address', methods: ['POST'])]
    public function createAddress(string $customerId, Request $request, Context $context): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            if (empty($data['salutationId'])) {
                return new JsonResponse(['success' => false, 'error' => 'salutationId is required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['firstName']) || empty($data['lastName'])) {
                return new JsonResponse(['success' => false, 'error' => 'firstName and lastName are required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['street']) || empty($data['zipcode']) || empty($data['city'])) {
                return new JsonResponse(['success' => false, 'error' => 'street, zipcode, and city are required'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['countryId'])) {
                return new JsonResponse(['success' => false, 'error' => 'countryId is required'], Response::HTTP_BAD_REQUEST);
            }

            $addressId = $this->manualOrderService->createCustomerAddress($customerId, $data, $context);

            // Reload addresses so frontend gets the full list
            $addresses = $this->manualOrderService->getCustomerAddresses($customerId, $context);
            $newAddress = null;
            foreach ($addresses as $addr) {
                if ($addr['id'] === $addressId) {
                    $newAddress = $addr;
                    break;
                }
            }

            return new JsonResponse([
                'success' => true,
                'addressId' => $addressId,
                'address' => $newAddress,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/search-customers', name: 'api.action.ppobase.manual-order.search-customers', methods: ['GET'])]
    public function searchCustomers(Request $request, Context $context): JsonResponse
    {
        $term = $request->query->get('term', '');
        if (strlen($term) < 1) {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        $data = $this->manualOrderService->searchCustomers($term, $context);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/search-products', name: 'api.action.ppobase.manual-order.search-products', methods: ['GET'])]
    public function searchProducts(Request $request, Context $context): JsonResponse
    {
        $term = $request->query->get('term', '');
        $salesChannelId = $request->query->get('salesChannelId', '');

        if (strlen($term) < 1 || !$salesChannelId) {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        $data = $this->manualOrderService->searchProducts($term, $salesChannelId, $context);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/sales-channels', name: 'api.action.ppobase.manual-order.sales-channels', methods: ['GET'])]
    public function getSalesChannels(Context $context): JsonResponse
    {
        $data = $this->manualOrderService->getSalesChannels($context);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/shipping-methods', name: 'api.action.ppobase.manual-order.shipping-methods', methods: ['GET'])]
    public function getShippingMethods(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId', '');
        if (!$salesChannelId) {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        $data = $this->manualOrderService->getShippingMethods($salesChannelId, $context);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/payment-methods', name: 'api.action.ppobase.manual-order.payment-methods', methods: ['GET'])]
    public function getPaymentMethods(Request $request, Context $context): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId', '');
        if (!$salesChannelId) {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        $data = $this->manualOrderService->getPaymentMethods($salesChannelId, $context);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/customer-addresses/{customerId}', name: 'api.action.ppobase.manual-order.customer-addresses', methods: ['GET'])]
    public function getCustomerAddresses(string $customerId, Context $context): JsonResponse
    {
        $data = $this->manualOrderService->getCustomerAddresses($customerId, $context);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/recent', name: 'api.action.ppobase.manual-order.recent', methods: ['GET'])]
    public function getRecentOrders(Request $request, Context $context): JsonResponse
    {
        $term = (string) $request->query->get('term', '');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        if ($page < 1) {
            $page = 1;
        }
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 250) {
            $limit = 250;
        }

        $result = $this->manualOrderService->getRecentManualOrders($context, $term, $limit, $page);

        return new JsonResponse([
            'success' => true,
            'data' => $result['data'] ?? [],
            'total' => $result['total'] ?? 0,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/{orderId}/export-excel', name: 'api.action.ppobase.manual-order.export-excel', methods: ['GET'])]
    public function exportExcel(string $orderId, Context $context): Response
    {
        try {
            $orderData = $this->manualOrderService->getOrderForPrint($orderId, $context);

            if (empty($orderData)) {
                return new JsonResponse(['success' => false, 'error' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }

            $csv = $this->manualOrderService->generateOrderCsv($orderId, $context);

            $this->activityLogService->logManualOrderExported($orderId, $orderData['orderNumber'], 'CSV', $context);

            return new Response($csv, Response::HTTP_OK, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="Order_' . $orderData['orderNumber'] . '.csv"',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/bulk-export-csv', name: 'api.action.ppobase.manual-order.bulk-export-csv', methods: ['POST'])]
    public function bulkExportCsv(Request $request, Context $context): Response
    {
        try {
            $payload = [];
            $content = trim((string) $request->getContent());
            if ($content !== '') {
                $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            }

            $ids = $payload['ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $ids = array_values(array_filter($ids, static fn ($id) => is_string($id) && $id !== ''));

            $term = (string) ($payload['term'] ?? '');
            $limit = (int) ($payload['limit'] ?? 1000);

            $csv = $this->manualOrderService->generateManualOrdersCsvBulk($context, $ids, $term, $limit);

            $this->activityLogService->logManualOrderBulkExported(count($ids) ?: $limit, 'CSV', $context);

            $timestamp = (new \DateTimeImmutable())->format('Y-m-d_H-i-s');

            return new Response($csv, Response::HTTP_OK, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="ManualOrders_' . $timestamp . '.csv"',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/{orderId}/update', name: 'api.action.ppobase.manual-order.update', methods: ['PATCH'])]
    public function updateOrder(string $orderId, Request $request, Context $context): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $this->manualOrderService->updateManualOrder($orderId, $data, $context);
            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/{orderId}/print-data', name: 'api.action.ppobase.manual-order.print-data', methods: ['GET'])]
    public function getPrintData(string $orderId, Context $context): JsonResponse
    {
        $data = $this->manualOrderService->getOrderForPrint($orderId, $context);

        if (empty($data)) {
            return new JsonResponse(['success' => false, 'error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route(path: '/api/_action/ppobase/manual-order/{orderId}/invoice-html', name: 'api.action.ppobase.manual-order.invoice-html', methods: ['GET'])]
    public function getInvoiceHtml(string $orderId, Context $context): Response
    {
        try {
            $orderData = $this->manualOrderService->getOrderForPrint($orderId, $context);

            if (empty($orderData)) {
                return new JsonResponse(['success' => false, 'error' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }

            $html = $this->renderInvoiceHtml($orderData);

            return new Response($html, Response::HTTP_OK, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/manual-order/{orderId}/delivery-note-html', name: 'api.action.ppobase.manual-order.delivery-note-html', methods: ['GET'])]
    public function getDeliveryNoteHtml(string $orderId, Context $context): Response
    {
        try {
            $orderData = $this->manualOrderService->getOrderForPrint($orderId, $context);

            if (empty($orderData)) {
                return new JsonResponse(['success' => false, 'error' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }

            $html = $this->renderDeliveryNoteHtml($orderData);

            return new Response($html, Response::HTTP_OK, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function renderInvoiceHtml(array $order): string
    {
        $billingAddr = $order['billingAddress'] ?? [];
        $billingHtml = '';
        if ($billingAddr) {
            if (!empty($billingAddr['company'])) {
                $billingHtml .= htmlspecialchars($billingAddr['company']) . '<br>';
            }
            $billingHtml .= htmlspecialchars(($billingAddr['firstName'] ?? '') . ' ' . ($billingAddr['lastName'] ?? '')) . '<br>';
            $billingHtml .= htmlspecialchars($billingAddr['street'] ?? '') . '<br>';
            if (!empty($billingAddr['additionalAddressLine1'])) {
                $billingHtml .= htmlspecialchars($billingAddr['additionalAddressLine1']) . '<br>';
            }
            $billingHtml .= htmlspecialchars(($billingAddr['zipcode'] ?? '') . ' ' . ($billingAddr['city'] ?? '')) . '<br>';
            $billingHtml .= htmlspecialchars($billingAddr['country'] ?? '');
        }

        $shippingAddr = $order['shippingAddress'] ?? $order['billingAddress'] ?? [];
        $shippingHtml = '';
        if ($shippingAddr) {
            if (!empty($shippingAddr['company'])) {
                $shippingHtml .= htmlspecialchars($shippingAddr['company']) . '<br>';
            }
            $shippingHtml .= htmlspecialchars(($shippingAddr['firstName'] ?? '') . ' ' . ($shippingAddr['lastName'] ?? '')) . '<br>';
            $shippingHtml .= htmlspecialchars($shippingAddr['street'] ?? '') . '<br>';
            if (!empty($shippingAddr['additionalAddressLine1'])) {
                $shippingHtml .= htmlspecialchars($shippingAddr['additionalAddressLine1']) . '<br>';
            }
            $shippingHtml .= htmlspecialchars(($shippingAddr['zipcode'] ?? '') . ' ' . ($shippingAddr['city'] ?? '')) . '<br>';
            $shippingHtml .= htmlspecialchars($shippingAddr['country'] ?? '');
        }

        $itemsHtml = '';
        foreach ($order['items'] as $i => $item) {
            $rowBg = ($i % 2 === 0) ? '#f9f9f9' : '#fff';
            $itemsHtml .= '<tr style="background:' . $rowBg . ';">';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['productName']) . '</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['productNumber']) . '</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;text-align:center;">' . $item['quantity'] . '</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;text-align:right;">' . number_format($item['unitPrice'], 2) . '</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;text-align:right;">' . $item['taxRate'] . '%</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;text-align:right;">' . number_format($item['totalPrice'], 2) . '</td>';
            $itemsHtml .= '</tr>';
        }

        $currency = htmlspecialchars($order['currency']);

        $notesHtml = '';
        if (!empty($order['notes'])) {
            $notesHtml = <<<NOTES
        <div style="margin-bottom: 25px; padding: 12px 15px; background: #f5f5f5; border-left: 4px solid #1E88E5;">
            <h3 style="font-size: 10pt; color: #1E88E5; margin-bottom: 6px; text-transform: uppercase;">Order Notes</h3>
            <p style="font-size: 9.5pt; line-height: 1.5; white-space: pre-wrap;">{$this->esc($order['notes'])}</p>
        </div>
NOTES;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {$this->esc($order['orderNumber'])}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 10pt; line-height: 1.4; color: #333; }
        .container { padding: 30px 40px; max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #1E88E5; padding-bottom: 15px; margin-bottom: 30px; }
        .title { font-size: 24pt; font-weight: bold; color: #1E88E5; }
        .order-num { font-size: 12pt; color: #666; margin-top: 4px; }
        .info-section { display: flex; gap: 40px; margin-bottom: 30px; }
        .info-box { flex: 1; }
        .info-box h3 { font-size: 11pt; color: #1E88E5; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase; }
        .info-row { margin-bottom: 4px; font-size: 9.5pt; }
        .info-label { color: #888; font-size: 8.5pt; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #1E88E5; color: #fff; padding: 9px 7px; text-align: left; font-size: 8pt; text-transform: uppercase; }
        th.right { text-align: right; }
        th.center { text-align: center; }
        .totals { width: 300px; margin-left: auto; margin-bottom: 25px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 9pt; }
        .totals-row.grand { background: #1E88E5; color: #fff; font-weight: bold; font-size: 11pt; border: none; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 8pt; color: #999; text-align: center; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="title">INVOICE</div>
                <div class="order-num">{$this->esc($order['orderNumber'])}</div>
            </div>
            <div style="text-align:right;">
                <div class="info-label">Date</div>
                <div class="info-row">{$this->esc(date('d/m/Y', strtotime($order['orderDate'])))}</div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-box">
                <h3>Bill To</h3>
                <div class="info-row"><strong>{$this->esc($order['customerName'])}</strong></div>
                <div class="info-row">{$this->esc($order['customerEmail'])}</div>
                <div class="info-row">{$billingHtml}</div>
                <h3 style="margin-top: 14px;">Ship To</h3>
                <div class="info-row">{$shippingHtml}</div>
            </div>
            <div class="info-box">
                <h3>Order Details</h3>
                <div class="info-row"><span class="info-label">Currency:</span> {$currency}</div>
                <div class="info-row"><span class="info-label">Payment:</span> {$this->esc($order['paymentMethod'] ?? 'N/A')}</div>
                <div class="info-row"><span class="info-label">Shipping:</span> {$this->esc($order['shippingMethod'] ?? 'N/A')}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="center">Qty</th>
                    <th class="right">Unit Price</th>
                    <th class="right">Tax</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHtml}
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row"><span>Subtotal</span><span>{$currency} {$this->fmt($order['subtotal'])}</span></div>
            <div class="totals-row"><span>Tax</span><span>{$currency} {$this->fmt($order['taxAmount'])}</span></div>
            <div class="totals-row grand"><span>Grand Total</span><span>{$currency} {$this->fmt($order['grandTotal'])}</span></div>
        </div>

        {$notesHtml}

        <div class="footer">
            Generated by PPOBase &middot; {$this->esc(date('d/m/Y H:i'))}
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderDeliveryNoteHtml(array $order): string
    {
        $shippingAddr = $order['shippingAddress'] ?? $order['billingAddress'] ?? [];
        $addrHtml = '';
        if ($shippingAddr) {
            if (!empty($shippingAddr['company'])) {
                $addrHtml .= htmlspecialchars($shippingAddr['company']) . '<br>';
            }
            $addrHtml .= htmlspecialchars(($shippingAddr['firstName'] ?? '') . ' ' . ($shippingAddr['lastName'] ?? '')) . '<br>';
            $addrHtml .= htmlspecialchars($shippingAddr['street'] ?? '') . '<br>';
            if (!empty($shippingAddr['additionalAddressLine1'])) {
                $addrHtml .= htmlspecialchars($shippingAddr['additionalAddressLine1']) . '<br>';
            }
            $addrHtml .= htmlspecialchars(($shippingAddr['zipcode'] ?? '') . ' ' . ($shippingAddr['city'] ?? '')) . '<br>';
            $addrHtml .= htmlspecialchars($shippingAddr['country'] ?? '');
        }

        $itemsHtml = '';
        foreach ($order['items'] as $i => $item) {
            $rowBg = ($i % 2 === 0) ? '#f9f9f9' : '#fff';
            $itemsHtml .= '<tr style="background:' . $rowBg . ';">';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['productName']) . '</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['productNumber']) . '</td>';
            $itemsHtml .= '<td style="padding:9px 7px;border-bottom:1px solid #eee;text-align:center;">' . $item['quantity'] . '</td>';
            $itemsHtml .= '</tr>';
        }

        $shippingDate = !empty($order['shippingDate']) ? date('d/m/Y', strtotime($order['shippingDate'])) : 'N/A';

        $notesValue = $order['notes'] ?? null;
        $notesText = (is_string($notesValue) && trim($notesValue) !== '') ? $notesValue : 'N/A';
        $notesRowHtml = '<div class="info-row"><span class="info-label">Notes:</span> <span style="white-space: pre-wrap;">' . $this->esc($notesText) . '</span></div>';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Note - {$this->esc($order['orderNumber'])}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 10pt; line-height: 1.4; color: #333; }
        .container { padding: 30px 40px; max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #388E3C; padding-bottom: 15px; margin-bottom: 30px; }
        .title { font-size: 24pt; font-weight: bold; color: #388E3C; }
        .order-num { font-size: 12pt; color: #666; margin-top: 4px; }
        .info-section { display: flex; gap: 40px; margin-bottom: 30px; }
        .info-box { flex: 1; }
        .info-box h3 { font-size: 11pt; color: #388E3C; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase; }
        .info-row { margin-bottom: 4px; font-size: 9.5pt; }
        .info-label { color: #888; font-size: 8.5pt; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th { background: #388E3C; color: #fff; padding: 9px 7px; text-align: left; font-size: 8pt; text-transform: uppercase; }
        th.center { text-align: center; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 8pt; color: #999; text-align: center; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="title">DELIVERY NOTE</div>
                <div class="order-num">{$this->esc($order['orderNumber'])}</div>
            </div>
            <div style="text-align:right;">
                <div class="info-label">Order Date</div>
                <div class="info-row">{$this->esc(date('d/m/Y', strtotime($order['orderDate'])))}</div>
                <div class="info-label" style="margin-top:8px;">Shipping Date</div>
                <div class="info-row">{$this->esc($shippingDate)}</div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-box">
                <h3>Ship To</h3>
                <div class="info-row">{$addrHtml}</div>
            </div>
            <div class="info-box">
                <h3>Shipping Details</h3>
                <div class="info-row"><span class="info-label">Method:</span> {$this->esc($order['shippingMethod'] ?? 'N/A')}</div>
                <div class="info-row"><span class="info-label">Customer:</span> {$this->esc($order['customerName'])}</div>
                <div class="info-row"><span class="info-label">Ref:</span> {$this->esc($order['orderReference'] ?? 'N/A')}</div>
                {$notesRowHtml}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="center">Qty</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHtml}
            </tbody>
        </table>

        <div class="footer">
            Generated by PPOBase &middot; {$this->esc(date('d/m/Y H:i'))}
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }

    private function fmt(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }
}
