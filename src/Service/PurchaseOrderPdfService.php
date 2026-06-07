<?php declare(strict_types=1);

namespace PPOBase\Service;

use PPOBase\Entity\PurchaseOrder\PurchaseOrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Environment;

class PurchaseOrderPdfService
{
    public function __construct(
        private readonly EntityRepository $purchaseOrderRepository,
        private readonly Environment $twig,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function getPurchaseOrder(string $id, Context $context): ?PurchaseOrderEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('supplier');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('items');
        $criteria->addAssociation('items.product');
        $criteria->getAssociation('items')->addSorting(new FieldSorting('lineNumber', 'ASC'));

        return $this->purchaseOrderRepository->search($criteria, $context)->first();
    }

    public function generateHtml(PurchaseOrderEntity $purchaseOrder): string
    {
        $config = $this->getPluginConfig();

        return $this->twig->render('@PPOBase/documents/purchase-order.html.twig', [
            'purchaseOrder' => $purchaseOrder,
            'supplier' => $purchaseOrder->getSupplier(),
            'items' => $purchaseOrder->getItems(),
            'config' => $config,
        ]);
    }

    public function generatePdf(PurchaseOrderEntity $purchaseOrder): string
    {
        $html = $this->generateHtml($purchaseOrder);
        $config = $this->getPluginConfig();

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', $config['poFontFamily'] ?? 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Generate filename in format: PO{YYYY}-{ID}_{SupplierName}_{YYYY-MM-DD}.{ext}
     * Example: PO2026-0001_Acme_Corp_2026-02-17.pdf
     */
    public function getFilename(PurchaseOrderEntity $purchaseOrder, string $extension = 'pdf'): string
    {
        $poNumber = $purchaseOrder->getPoNumber();

        // Get supplier name and sanitize for filename
        $supplierName = '';
        if ($purchaseOrder->getSupplier()) {
            $supplierName = $purchaseOrder->getSupplier()->getCompanyTradeName() ?? '';
        }
        // Replace non-alphanumeric chars (except space) with nothing, then spaces with underscore
        $supplierName = preg_replace('/[^a-zA-Z0-9\s]/', '', $supplierName);
        $supplierName = preg_replace('/\s+/', '_', trim($supplierName));

        $currentDate = date('Y-m-d');

        return "{$poNumber}_{$supplierName}_{$currentDate}.{$extension}";
    }

    /**
     * Retrieve all plugin configuration values
     */
    private function getPluginConfig(): array
    {
        $prefix = 'PPOBase.config.';

        return [
            'poPrimaryColor' => $this->systemConfigService->get($prefix . 'poPrimaryColor') ?? '#1E88E5',
            'poFontFamily' => $this->systemConfigService->get($prefix . 'poFontFamily') ?? 'DejaVu Sans',
            'showSupplierAddress' => $this->systemConfigService->get($prefix . 'showSupplierAddress') ?? true,
            'showSupplierVat' => $this->systemConfigService->get($prefix . 'showSupplierVat') ?? true,
            'showEan' => $this->systemConfigService->get($prefix . 'showEan') ?? false,
            'showMpn' => $this->systemConfigService->get($prefix . 'showMpn') ?? false,
            'showTaxColumn' => $this->systemConfigService->get($prefix . 'showTaxColumn') ?? true,
            'showNotes' => $this->systemConfigService->get($prefix . 'showNotes') ?? true,
            'showExpectedDelivery' => $this->systemConfigService->get($prefix . 'showExpectedDelivery') ?? true,
            'showInternalReference' => $this->systemConfigService->get($prefix . 'showInternalReference') ?? false,
        ];
    }
}
