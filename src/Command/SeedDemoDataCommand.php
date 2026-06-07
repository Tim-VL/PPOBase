<?php declare(strict_types=1);

namespace PPOBase\Command;

use PPOBase\Service\ManualOrderService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityRepository $supplierRepository,
        private readonly EntityRepository $purchaseOrderRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $salutationRepository,
        private readonly EntityRepository $countryRepository,
        private readonly ManualOrderService $manualOrderService
    ) {
        parent::__construct('ppobase:seed-demo-data');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Seed demo data for PPOBase (Suppliers, Purchase Orders, Manual Orders).')
            ->addOption('suppliers', null, InputOption::VALUE_REQUIRED, 'Number of suppliers to create', '30')
            ->addOption('purchase-orders', null, InputOption::VALUE_REQUIRED, 'Number of purchase orders to create', '30')
            ->addOption('manual-orders', null, InputOption::VALUE_REQUIRED, 'Number of manual orders to create', '10')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Name/number prefix for generated data', 'DEMO')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write to database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $supplierCount = max(0, (int) $input->getOption('suppliers'));
        $poCount = max(0, (int) $input->getOption('purchase-orders'));
        $manualOrderCount = max(0, (int) $input->getOption('manual-orders'));
        $prefix = trim((string) $input->getOption('prefix')) ?: 'DEMO';
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('PPOBase demo data seeding');
        $io->text(sprintf('Suppliers: %d, Purchase Orders: %d, Manual Orders: %d%s', $supplierCount, $poCount, $manualOrderCount, $dryRun ? ' (dry-run)' : ''));

        $salesChannelId = $this->resolveSalesChannelId($context);
        $shippingMethodId = $this->resolveShippingMethodId($salesChannelId, $context);
        $paymentMethodId = $this->resolvePaymentMethodId($salesChannelId, $context);
        $salutationId = $this->resolveSalutationId($context);
        $countryId = $this->resolveCountryId($context);
        $products = $this->loadProducts($context);

        $createdSupplierIds = [];

        if ($supplierCount > 0) {
            $io->section('Creating suppliers');
            $createdSupplierIds = $this->createSuppliers($supplierCount, $prefix, $dryRun, $context);
            $io->success(sprintf('Suppliers created: %d', count($createdSupplierIds)));
        }

        if ($poCount > 0) {
            $io->section('Creating purchase orders');
            $poCreated = $this->createPurchaseOrders($poCount, $prefix, $createdSupplierIds, $products, $dryRun, $context);
            $io->success(sprintf('Purchase orders created: %d', $poCreated));
        }

        if ($manualOrderCount > 0) {
            $io->section('Creating manual orders');
            $created = $this->createManualOrders(
                $manualOrderCount,
                $prefix,
                $salesChannelId,
                $shippingMethodId,
                $paymentMethodId,
                $salutationId,
                $countryId,
                $products,
                $dryRun,
                $context
            );
            $io->success(sprintf('Manual orders created: %d', $created));
        }

        $io->success('Done.');
        return Command::SUCCESS;
    }

    private function resolveSalesChannelId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(1);
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        if (!$salesChannel) {
            throw new \RuntimeException('No sales channel found.');
        }

        return $salesChannel->getId();
    }

    private function resolveShippingMethodId(string $salesChannelId, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->setLimit(1);
        $shippingMethod = $this->shippingMethodRepository->search($criteria, $context)->first();

        if (!$shippingMethod) {
            throw new \RuntimeException('No active shipping method found for the selected sales channel.');
        }

        return $shippingMethod->getId();
    }

    private function resolvePaymentMethodId(string $salesChannelId, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->setLimit(1);
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->first();

        if (!$paymentMethod) {
            throw new \RuntimeException('No active payment method found for the selected sales channel.');
        }

        return $paymentMethod->getId();
    }

    private function resolveSalutationId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(1);
        $salutation = $this->salutationRepository->search($criteria, $context)->first();

        if (!$salutation) {
            throw new \RuntimeException('No salutation found.');
        }

        return $salutation->getId();
    }

    private function resolveCountryId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        $criteria->setLimit(1);
        $country = $this->countryRepository->search($criteria, $context)->first();

        if (!$country) {
            throw new \RuntimeException('No active country found.');
        }

        return $country->getId();
    }

    /**
     * @return array<int, array{id: string, productNumber: string, name: string, price: float}>
     */
    private function loadProducts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('tax');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(100);

        $result = $this->productRepository->search($criteria, $context);

        $products = [];
        foreach ($result->getEntities() as $product) {
            $name = $product->getTranslation('name') ?? $product->getName() ?? '';
            $productNumber = (string) ($product->getProductNumber() ?? '');

            $price = 0.0;
            $priceStruct = $product->getCurrencyPrice(Defaults::CURRENCY);
            if ($priceStruct) {
                $price = (float) ($priceStruct->getGross() ?? 0);
            }
            if ($price <= 0) {
                $price = 10.0;
            }

            $products[] = [
                'id' => $product->getId(),
                'productNumber' => $productNumber,
                'name' => $name,
                'price' => $price,
            ];
        }

        if (count($products) === 0) {
            throw new \RuntimeException('No products found. Please create some products first.');
        }

        return $products;
    }

    /**
     * @return array<string>
     */
    private function createSuppliers(int $count, string $prefix, bool $dryRun, Context $context): array
    {
        $now = (new \DateTimeImmutable())->format('YmdHis');
        $payload = [];
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $id = Uuid::randomHex();
            $supplierNumber = sprintf('SUP-%s-%s-%02d', strtoupper($prefix), $now, $i);
            $tradeName = sprintf('%s Supplier %02d', $prefix, $i);

            $payload[] = [
                'id' => $id,
                'active' => true,
                'supplierNumber' => $supplierNumber,
                'companyTradeName' => $tradeName,
                'companyEmail' => sprintf('supplier%02d-%s@example.com', $i, strtolower($prefix)),
                'contactFirstName' => 'Demo',
                'contactLastName' => 'Supplier ' . $i,
                'contactEmail' => sprintf('contact%02d-%s@example.com', $i, strtolower($prefix)),
                'contactPhone' => '+44 20 0000 ' . str_pad((string) $i, 4, '0', \STR_PAD_LEFT),
                'billingAddressLine1' => $i . ' Demo Street',
                'billingCity' => 'London',
                'billingPostalCode' => '110093',
                'billingCountry' => 'United Kingdom',
                'shippingAddressLine1' => $i . ' Demo Street',
                'shippingCity' => 'London',
                'shippingPostalCode' => '110093',
                'shippingCountry' => 'United Kingdom',
                'currency' => 'GBP',
                'paymentTerms' => 'Net 30',
                'minimumOrderValue' => 0,
                'incoterms' => 'EXW',
                'vatPercent' => 0,
                'createdBy' => 'seed',
            ];

            $ids[] = $id;
        }

        if (!$dryRun) {
            $this->supplierRepository->create($payload, $context);
        }

        return $ids;
    }

    /**
     * @param array<string> $supplierIds
     * @param array<int, array{id: string, productNumber: string, name: string, price: float}> $products
     */
    private function createPurchaseOrders(int $count, string $prefix, array $supplierIds, array $products, bool $dryRun, Context $context): int
    {
        if (count($supplierIds) === 0) {
            // If we didn't create suppliers in this run, use existing ones.
            $supplierIds = $this->loadExistingSupplierIds($context);
        }

        if (count($supplierIds) === 0) {
            throw new \RuntimeException('No suppliers found. Create suppliers first.');
        }

        $now = (new \DateTimeImmutable())->format('YmdHis');
        $payload = [];

        for ($i = 1; $i <= $count; $i++) {
            $id = Uuid::randomHex();
            $supplierId = $supplierIds[($i - 1) % count($supplierIds)];
            $poNumber = sprintf('PO-%s-%s-%04d', strtoupper($prefix), $now, $i);

            $itemLines = random_int(1, 3);
            $items = [];

            $subtotal = 0.0;
            for ($line = 1; $line <= $itemLines; $line++) {
                $product = $products[random_int(0, count($products) - 1)];
                $qty = random_int(1, 10);
                $unitPrice = (float) $product['price'];
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $items[] = [
                    'id' => Uuid::randomHex(),
                    'purchaseOrderId' => $id,
                    'productId' => $product['id'],
                    'lineNumber' => $line,
                    'productNumber' => $product['productNumber'],
                    'productName' => $product['name'] ?: ('Product ' . $product['productNumber']),
                    'quantityOrdered' => $qty,
                    'quantityReceived' => 0,
                    'unit' => 'pcs',
                    'unitPrice' => round($unitPrice, 2),
                    'taxRate' => 0,
                    'taxAmount' => 0,
                    'lineTotal' => $lineTotal,
                ];
            }

            $payload[] = [
                'id' => $id,
                'poNumber' => $poNumber,
                'supplierId' => $supplierId,
                'status' => 'draft',
                'orderDate' => (new \DateTimeImmutable())->format('c'),
                'expectedDeliveryDate' => (new \DateTimeImmutable('+14 days'))->format('c'),
                'currency' => 'GBP',
                'subtotal' => round($subtotal, 2),
                'taxAmount' => 0,
                'total' => round($subtotal, 2),
                'itemCount' => count($items),
                'internalReference' => sprintf('%s-PO-%04d', strtoupper($prefix), $i),
                'notes' => 'Seeded purchase order for bulk testing',
                'shippingAddress' => "Demo Warehouse\n110093 London\nUnited Kingdom",
                'emailSent' => false,
                'createdBy' => 'seed',
                'items' => $items,
            ];
        }

        if (!$dryRun) {
            $this->purchaseOrderRepository->create($payload, $context);
        }

        return $count;
    }

    /**
     * @return array<string>
     */
    private function loadExistingSupplierIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(50);

        $result = $this->supplierRepository->searchIds($criteria, $context);
        return $result->getIds();
    }

    /**
     * @param array<int, array{id: string, productNumber: string, name: string, price: float}> $products
     */
    private function createManualOrders(
        int $count,
        string $prefix,
        string $salesChannelId,
        string $shippingMethodId,
        string $paymentMethodId,
        string $salutationId,
        string $countryId,
        array $products,
        bool $dryRun,
        Context $context
    ): int {
        $now = (new \DateTimeImmutable())->format('YmdHis');

        for ($i = 1; $i <= $count; $i++) {
            $customerEmail = sprintf('manual-%s-%s-%02d@example.com', strtolower($prefix), $now, $i);

            $customerId = Uuid::randomHex();
            if (!$dryRun) {
                $customerId = $this->manualOrderService->createCustomer([
                    'salutationId' => $salutationId,
                    'firstName' => 'Demo',
                    'lastName' => 'User ' . $i,
                    'email' => $customerEmail,
                    'phone' => '+44 20 1234 ' . str_pad((string) $i, 4, '0', \STR_PAD_LEFT),
                    'company' => 'Demo Company',
                    'street' => $i . ' Demo Street',
                    'zipcode' => '110093',
                    'city' => 'London',
                    'countryId' => $countryId,
                ], $salesChannelId, $context);
            }

            $itemLines = random_int(1, 2);
            $items = [];
            for ($line = 1; $line <= $itemLines; $line++) {
                $product = $products[random_int(0, count($products) - 1)];
                $qty = random_int(1, 5);
                $items[] = [
                    'productId' => $product['id'],
                    'quantity' => $qty,
                    'unitPrice' => round((float) $product['price'], 2),
                ];
            }

            if (!$dryRun) {
                $this->manualOrderService->createOrder([
                    'salesChannelId' => $salesChannelId,
                    'customerId' => $customerId,
                    'items' => $items,
                    'shippingMethodId' => $shippingMethodId,
                    'paymentMethodId' => $paymentMethodId,
                    'orderReference' => sprintf('%s-MO-%s-%02d', strtoupper($prefix), $now, $i),
                    'notes' => 'Seeded manual order for bulk testing',
                    'shippingDate' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d'),
                    'sendConfirmation' => false,
                    'openAfterCreate' => false,
                ], $context);
            }
        }

        return $count;
    }
}
