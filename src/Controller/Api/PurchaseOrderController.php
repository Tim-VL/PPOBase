<?php declare(strict_types=1);

namespace PPOBase\Controller\Api;

use PPOBase\Service\ActivityLogService;
use PPOBase\Service\PurchaseOrderPdfService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Twig\Environment;

#[Route(defaults: ['_routeScope' => ['api']])]
class PurchaseOrderController
{
    public function __construct(
        private readonly EntityRepository $purchaseOrderRepository,
        private readonly EntityRepository $activityLogRepository,
        private readonly PurchaseOrderPdfService $pdfService,
        private readonly ActivityLogService $activityLogService,
        private readonly MailerInterface $mailer,
        private readonly SystemConfigService $systemConfigService,
        private readonly Environment $twig
    ) {
    }

    #[Route(path: '/api/_action/ppobase/purchase-order/{id}', name: 'api.action.ppobase.purchase-order.get', methods: ['GET'])]
    public function get(string $id, Context $context): JsonResponse
    {
        $purchaseOrder = $this->pdfService->getPurchaseOrder($id, $context);
        if (!$purchaseOrder) {
            return new JsonResponse(['success' => false, 'error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['success' => true, 'data' => $purchaseOrder]);
    }

    #[Route(path: '/api/_action/ppobase/purchase-order/{id}/export/pdf', name: 'api.action.ppobase.purchase-order.export.pdf', methods: ['GET'])]
    public function exportPdf(string $id, Context $context): Response
    {
        $purchaseOrder = $this->pdfService->getPurchaseOrder($id, $context);
        if (!$purchaseOrder) {
            return new JsonResponse(['success' => false, 'error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        $pdf = $this->pdfService->generatePdf($purchaseOrder);
        $filename = $this->pdfService->getFilename($purchaseOrder, 'pdf');

        $this->activityLogService->logPurchaseOrderExported($id, $purchaseOrder->getPoNumber(), 'PDF', $context);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route(path: '/api/_action/ppobase/purchase-order/{id}/export/html', name: 'api.action.ppobase.purchase-order.export.html', methods: ['GET'])]
    public function exportHtml(string $id, Context $context): Response
    {
        $purchaseOrder = $this->pdfService->getPurchaseOrder($id, $context);
        if (!$purchaseOrder) {
            return new JsonResponse(['success' => false, 'error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        $html = $this->pdfService->generateHtml($purchaseOrder);
        $filename = $this->pdfService->getFilename($purchaseOrder, 'html');

        $this->activityLogService->logPurchaseOrderExported($id, $purchaseOrder->getPoNumber(), 'HTML', $context);

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Returns pre-filled email data for the compose modal.
     * Resolves: TO (supplier), BCC (reply-to from config), subject, and rendered HTML body.
     */
    #[Route(path: '/api/_action/ppobase/purchase-order/{id}/email-preview', name: 'api.action.ppobase.purchase-order.email-preview', methods: ['GET'])]
    public function emailPreview(string $id, Context $context): JsonResponse
    {
        $purchaseOrder = $this->pdfService->getPurchaseOrder($id, $context);
        if (!$purchaseOrder) {
            return new JsonResponse(['success' => false, 'error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        $supplier = $purchaseOrder->getSupplier();
        if (!$supplier) {
            return new JsonResponse(['success' => false, 'error' => 'No supplier associated with this purchase order'], Response::HTTP_BAD_REQUEST);
        }

        // Resolve TO: purchaseEmail → companyEmail
        $emailTo = $supplier->getPurchaseEmail() ?: $supplier->getCompanyEmail();

        // Resolve sender / reply-to from Shopware config
        $senderEmail = $this->systemConfigService->get('core.basicInformation.email') ?? '';
        $shopName = $this->systemConfigService->get('core.basicInformation.shopName') ?? 'Shop';
        $senderName = $purchaseOrder->getSalesChannel()?->getName() ?? $shopName;

        $poNumber = $purchaseOrder->getPoNumber();
        $supplierName = $supplier->getCompanyTradeName();
        $poDate = (new \DateTime())->format('d F Y');

        // Default subject
        $subject = "Purchase Order {$poNumber} — {$senderName}";

        // CC/BCC: supplier defaults + shop email in BCC
        $cc = $supplier->getDefaultCc() ?? '';
        $bcc = $supplier->getDefaultBcc() ?? '';
        if ($bcc === '') {
            $bcc = $senderEmail;
        } elseif ($senderEmail) {
            $bcc = $bcc . ', ' . $senderEmail;
        }

        // Render email body HTML
        $htmlBody = $this->twig->render('@PPOBase/email/purchase-order.html.twig', [
            'purchaseOrder' => $purchaseOrder,
            'supplier'      => $supplier,
            'poNumber'      => $poNumber,
            'supplierName'  => $supplierName,
            'senderName'    => $senderName,
            'poDate'        => $poDate,
            'config'        => $this->getPluginConfig(),
        ]);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'to'          => $emailTo ?? '',
                'cc'          => $cc,
                'bcc'         => $bcc,
                'subject'     => $subject,
                'body'        => $htmlBody,
                'senderEmail' => $senderEmail,
                'senderName'  => $senderName,
                'supplierName' => $supplierName,
                'attachmentFilename' => $this->pdfService->getFilename($purchaseOrder, 'pdf'),
            ],
        ]);
    }

    /**
     * Enhanced send-email endpoint.
     * Accepts optional POST body: { to, cc, bcc, subject }
     * - Sends the PO PDF as attachment
     * - Marks PO as sent (status → 'sent', emailSent, emailSentAt, emailSentTo)
     * - BCC's the reply-to (shop email) by default
     */
    #[Route(path: '/api/_action/ppobase/purchase-order/{id}/send-email', name: 'api.action.ppobase.purchase-order.send-email', methods: ['POST'])]
    public function sendEmail(string $id, Request $request, Context $context): JsonResponse
    {
        $purchaseOrder = $this->pdfService->getPurchaseOrder($id, $context);
        if (!$purchaseOrder) {
            return new JsonResponse(['success' => false, 'error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        $supplier = $purchaseOrder->getSupplier();
        if (!$supplier) {
            return new JsonResponse(['success' => false, 'error' => 'No supplier associated with this purchase order'], Response::HTTP_BAD_REQUEST);
        }

        // Read overrides from POST body (from the compose modal)
        $payload = json_decode($request->getContent(), true) ?: [];

        // Resolve TO: from payload or supplier data
        $supplierEmail = $supplier->getPurchaseEmail() ?: $supplier->getCompanyEmail();
        $emailTo = !empty($payload['to']) ? trim($payload['to']) : $supplierEmail;

        if (!$emailTo) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No email address configured for this supplier. Please set Purchase Email or Company Email.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Resolve sender from Shopware Basic Information settings
        $senderEmail = $this->systemConfigService->get('core.basicInformation.email');
        if (!$senderEmail) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No sender email configured. Please set a shop email address in Basic Information settings.',
            ], Response::HTTP_BAD_REQUEST);
        }
        $shopName = $this->systemConfigService->get('core.basicInformation.shopName') ?? 'Shop';
        $senderName = $purchaseOrder->getSalesChannel()?->getName() ?? $shopName;

        // Respect Shopware's "Disable email delivery" setting
        if ($this->systemConfigService->getBool('core.mailerSettings.disableDelivery')) {

            return new JsonResponse([
                'success' => false,
                'error'   => 'Email delivery is disabled in Shopware Mailer settings (Settings > Mailer). Enable it to send emails.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // CC/BCC from payload; default BCC = senderEmail (reply-to / shop email)
        $cc  = !empty($payload['cc']) ? trim($payload['cc']) : '';
        $bcc = array_key_exists('bcc', $payload) ? trim($payload['bcc']) : $senderEmail;

        // Subject from payload or default
        $poNumber = $purchaseOrder->getPoNumber();
        $supplierName = $supplier->getCompanyTradeName();
        $subject = !empty($payload['subject']) ? trim($payload['subject']) : "Purchase Order {$poNumber} — {$senderName}";

        try {
            // Generate PDF attachment
            $pdfContent = $this->pdfService->generatePdf($purchaseOrder);
            $pdfFilename = $this->pdfService->getFilename($purchaseOrder, 'pdf');

            $poDate = (new \DateTime())->format('d F Y');
            $bodyOverride = array_key_exists('body', $payload) ? (string) $payload['body'] : null;

            // Prefer edited body from compose modal; fallback to rendered template.
            if ($bodyOverride !== null && trim($bodyOverride) !== '') {
                $htmlBody = $bodyOverride;
            } else {
                $htmlBody = $this->twig->render('@PPOBase/email/purchase-order.html.twig', [
                    'purchaseOrder' => $purchaseOrder,
                    'supplier'      => $supplier,
                    'poNumber'      => $poNumber,
                    'supplierName'  => $supplierName,
                    'senderName'    => $senderName,
                    'poDate'        => $poDate,
                    'config'        => $this->getPluginConfig(),
                ]);
            }

            // Build the email
            $email = (new Email())
                ->from(new Address($senderEmail, $senderName))
                ->to($emailTo)
                ->replyTo(new Address($senderEmail, $senderName))
                ->subject($subject)
                ->html($htmlBody)
                ->attach($pdfContent, $pdfFilename, 'application/pdf');

            // Add CC (supports multiple comma-separated)
            if ($cc !== '') {
                foreach ($this->parseEmailAddresses($cc) as $ccAddr) {
                    $email->addCc(new Address($ccAddr));
                }
            }

            // Add BCC (supports multiple comma-separated)
            if ($bcc !== '') {
                foreach ($this->parseEmailAddresses($bcc) as $bccAddr) {
                    $email->addBcc(new Address($bccAddr));
                }
            }

            $this->mailer->send($email);

            // Update PO: mark as sent + record the recipient + change status to 'sent'
            $updateData = [
                'id'          => $id,
                'emailSent'   => true,
                'emailSentAt' => (new \DateTime())->format(\DateTime::ATOM),
                'emailSentTo' => $emailTo,
            ];

            // Auto-set status to 'sent' if currently 'draft'
            if ($purchaseOrder->getStatus() === 'draft') {
                $updateData['status'] = 'sent';
            }

            $this->purchaseOrderRepository->update([$updateData], $context);

            // Log the action with details
            $logDetails = "PO {$poNumber} emailed to {$emailTo}";
            if ($cc !== '') {
                $logDetails .= ", CC: {$cc}";
            }
            if ($bcc !== '') {
                $logDetails .= ", BCC: {$bcc}";
            }
            $this->activityLogService->log(
                'purchase_order',
                $id,
                'emailed',
                $context,
                $logDetails,
                $poNumber,
                null,
                ['email' => $emailTo, 'cc' => $cc, 'bcc' => $bcc]
            );

            return new JsonResponse([
                'success' => true,
                'message' => "Email sent to {$emailTo}",
                'data'    => [
                    'emailTo' => $emailTo,
                    'cc'      => $cc,
                    'bcc'     => $bcc,
                    'statusChanged' => $purchaseOrder->getStatus() === 'draft',
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/ppobase/purchase-order/{id}/history', name: 'api.action.ppobase.purchase-order.history', methods: ['GET'])]
    public function history(string $id, Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entityType', 'purchase_order'));
        $criteria->addFilter(new EqualsFilter('entityId', str_replace('-', '', $id)));
        $criteria->addSorting(new FieldSorting('loggedAt', FieldSorting::DESCENDING));
        $criteria->setLimit((int) $request->query->get('limit', 50));

        $result = $this->activityLogRepository->search($criteria, $context);

        return new JsonResponse(['success' => true, 'data' => array_values($result->getElements())]);
    }

    /**
     * Parse comma/semicolon-separated email addresses into an array.
     * Trims whitespace and filters out empty strings.
     */
    private function parseEmailAddresses(string $addresses): array
    {
        $parsed = preg_split('/[,;]+/', $addresses);
        return array_filter(array_map('trim', $parsed), static fn(string $addr) => $addr !== '');
    }

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
