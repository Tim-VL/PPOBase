<?php declare(strict_types=1);

namespace PPOBase\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ManualOrderService
{
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly Processor $cartProcessor,
        private readonly OrderConverter $orderConverter,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerAddressRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly ActivityLogService $activityLogService,
        private readonly GroupedProductService $groupedProductService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Create a real Shopware order using the cart-to-order pipeline.
     *
     * @return array{success: bool, orderId: string, orderNumber: string}
     */
    public function createOrder(array $payload, Context $context): array
    {
        $salesChannelId = $payload['salesChannelId'];
        $customerId = $payload['customerId'];

        // 1. Build SalesChannelContext with customer, shipping & payment methods
        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::SHIPPING_METHOD_ID => $payload['shippingMethodId'],
            SalesChannelContextService::PAYMENT_METHOD_ID => $payload['paymentMethodId'],
        ];

        if (!empty($payload['billingAddressId'])) {
            $options[SalesChannelContextService::BILLING_ADDRESS_ID] = $payload['billingAddressId'];
        }
        if (!empty($payload['shippingAddressId'])) {
            $options[SalesChannelContextService::SHIPPING_ADDRESS_ID] = $payload['shippingAddressId'];
        }

        $token = Uuid::randomHex();
        $salesChannelContext = $this->salesChannelContextFactory->create($token, $salesChannelId, $options);

        // 2. Create cart with line items
        $cart = new Cart($token);
        $lineItems = [];
        $items = $this->expandGroupedProductItems($payload['items'], $context);

        foreach ($items as $item) {
            $lineItemId = Uuid::randomHex();
            $lineItem = new LineItem($lineItemId, LineItem::PRODUCT_LINE_ITEM_TYPE, $item['productId'], (int) $item['quantity']);
            $lineItem->setStackable(true);
            $lineItem->setRemovable(true);

            if (!empty($item['ppobaseGroupedChild'])) {
                $lineItem->setPayloadValue('ppobase_grouped_child', true);
                $lineItem->setPayloadValue('ppobase_manual_order_grouped_child', true);
                if (!empty($item['ppobaseGroupedParentProductId'])) {
                    $lineItem->setPayloadValue('ppobase_grouped_parent_product_id', $item['ppobaseGroupedParentProductId']);
                }
                if (!empty($item['ppobaseChildBaseQty'])) {
                    $lineItem->setPayloadValue('ppobase_child_base_qty', (int) $item['ppobaseChildBaseQty']);
                }
            } else {
                $lineItem->setPayloadValue('ppobase_skip_grouped_children', true);
                if (!empty($item['ppobaseRemovedGroupedChildProductIds']) && \is_array($item['ppobaseRemovedGroupedChildProductIds'])) {
                    $lineItem->setPayloadValue('ppobase_removed_child_product_ids', $item['ppobaseRemovedGroupedChildProductIds']);
                }
            }

            // Price override: only override unit price, let the product's own tax rate be resolved by the cart processor
            if (isset($item['unitPrice']) && $item['unitPrice'] !== null) {
                // Lookup the product's actual tax rate from the database (with inheritance for variants)
                $productCriteria = new Criteria([$item['productId']]);
                $productCriteria->addAssociation('tax');
                $inheritanceContext = clone $context;
                $inheritanceContext->setConsiderInheritance(true);
                $productEntity = $this->productRepository->search($productCriteria, $inheritanceContext)->first();
                $taxRate = $productEntity && $productEntity->getTax() ? $productEntity->getTax()->getTaxRate() : 0.0;

                $priceDefinition = new QuantityPriceDefinition(
                    (float) $item['unitPrice'],
                    new TaxRuleCollection([new TaxRule($taxRate)]),
                    (int) $item['quantity']
                );
                $lineItem->setPriceDefinition($priceDefinition);

                // Mark as custom price so the ProductCartProcessor does NOT overwrite it
                $lineItem->addExtension(ProductCartProcessor::CUSTOM_PRICE, new ArrayEntity());
            }

            $lineItems[] = $lineItem;
        }

        $cart->addLineItems(new LineItemCollection($lineItems));

        // 3. Process/calculate the cart (with permission to keep custom prices)
        $cartBehavior = new CartBehavior([
            CheckoutPermissions::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
        ]);
        $cart = $this->cartProcessor->process($cart, $salesChannelContext, $cartBehavior);

        // 3b. Override shipping costs if a custom shipping price was provided.
        // We must do this via a second cart calculation, otherwise the order totals might not include the custom shipping.
        if (isset($payload['shippingCosts']) && $payload['shippingCosts'] !== null && $payload['shippingCosts'] !== '') {
            $shippingCostValue = (float) $payload['shippingCosts'];
            // Use the first line item's tax rate for shipping tax, or default to 0
            $shippingTaxRate = 0.0;
            if ($cart->getLineItems()->count() > 0) {
                $firstItem = $cart->getLineItems()->first();
                if ($firstItem && $firstItem->getPrice() && $firstItem->getPrice()->getCalculatedTaxes()->count() > 0) {
                    $shippingTaxRate = $firstItem->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
                }
            }

            $shippingTaxAmount = $shippingCostValue - ($shippingCostValue / (1 + $shippingTaxRate / 100));
            $calculatedTax = new CalculatedTax($shippingTaxAmount, $shippingTaxRate, $shippingCostValue);
            $shippingPrice = new CalculatedPrice(
                $shippingCostValue,
                $shippingCostValue,
                new CalculatedTaxCollection([$calculatedTax]),
                new TaxRuleCollection([new TaxRule($shippingTaxRate)])
            );

            $cart->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $shippingPrice);

            $recalcBehavior = new CartBehavior([
                CheckoutPermissions::ALLOW_PRODUCT_PRICE_OVERWRITES => true,
                CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true,
            ]);
            $cart = $this->cartProcessor->process($cart, $salesChannelContext, $recalcBehavior);
        }

        // 4. Convert cart to order data array
        $conversionContext = new OrderConversionContext();
        $orderData = $this->orderConverter->convertToOrder($cart, $salesChannelContext, $conversionContext);

        // Store selected shipping method so print/export can fall back even if deliveries are missing.
        $customFields = $orderData['customFields'] ?? [];
        $customFields['ppobase_shipping_method_id'] = $payload['shippingMethodId'];
        $shippingMethod = $this->shippingMethodRepository->search(new Criteria([$payload['shippingMethodId']]), $context)->first();
        if ($shippingMethod) {
            $customFields['ppobase_shipping_method_name'] = $shippingMethod->getTranslation('name') ?? $shippingMethod->getName();
        }
        $orderData['customFields'] = $customFields;

        // Store order reference in custom fields if provided
        if (!empty($payload['orderReference'])) {
            $customFields = $orderData['customFields'] ?? [];
            $customFields['ppobase_order_reference'] = $payload['orderReference'];
            $orderData['customFields'] = $customFields;
        }

        // Store shipping date in custom fields if provided
        if (!empty($payload['shippingDate'])) {
            $customFields = $orderData['customFields'] ?? [];
            $customFields['ppobase_shipping_date'] = $payload['shippingDate'];
            $orderData['customFields'] = $customFields;
        }

        // Store notes in custom fields if provided
        if (!empty($payload['notes'])) {
            $customFields = $orderData['customFields'] ?? [];
            $customFields['ppobase_order_notes'] = $payload['notes'];
            $orderData['customFields'] = $customFields;

            // Also map to Shopware's built-in internal comment field so it shows up consistently
            // across the admin and can be printed from the order later.
            $orderData['internalComment'] = $payload['notes'];
        }

        // Mark as manual order (use string 'yes' so DAL EqualsFilter works without registered custom field)
        $customFields = $orderData['customFields'] ?? [];
        $customFields['ppobase_manual_order'] = 'yes';
        $orderData['customFields'] = $customFields;

        // 5. Persist the order
        $this->orderRepository->create([$orderData], $context);

        $orderId = $orderData['id'];

        // 6. Retrieve order number
        $criteria = new Criteria([$orderId]);
        $order = $this->orderRepository->search($criteria, $context)->first();
        $orderNumber = $order ? $order->getOrderNumber() : 'N/A';

        // 7. Dispatch order placed event if confirmation is requested
        if (!empty($payload['sendConfirmation']) && $order instanceof OrderEntity) {
            // Reload order with all associations needed for the event
            $fullCriteria = new Criteria([$orderId]);
            $fullCriteria->addAssociation('orderCustomer');
            $fullCriteria->addAssociation('lineItems');
            $fullCriteria->addAssociation('deliveries');
            $fullCriteria->addAssociation('transactions');
            $fullOrder = $this->orderRepository->search($fullCriteria, $context)->first();

            if ($fullOrder instanceof OrderEntity) {
                $event = new CheckoutOrderPlacedEvent($salesChannelContext, $fullOrder);
                $this->eventDispatcher->dispatch($event);
            }
        }

        // 8. Get customer name for logging
        $customerName = 'Unknown';
        $customerCriteria = new Criteria([$customerId]);
        $customer = $this->customerRepository->search($customerCriteria, $context)->first();
        if ($customer) {
            $customerName = $customer->getFirstName() . ' ' . $customer->getLastName();
        }

        $total = $order ? $order->getAmountTotal() : 0.0;

        // 9. Log activity
        $this->activityLogService->logManualOrderCreated($orderId, $orderNumber, $customerName, $total, $context);

        return [
            'success' => true,
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function expandGroupedProductItems(array $items, Context $context): array
    {
        $expanded = [];
        $submittedGroupedChildren = [];

        foreach ($items as $item) {
            if (!empty($item['ppobaseGroupedChild']) && !empty($item['productId'])) {
                $parentProductId = (string) ($item['ppobaseGroupedParentProductId'] ?? '');
                $submittedGroupedChildren[$parentProductId . '-' . $item['productId']] = true;
            }
        }

        foreach ($items as $item) {
            $expanded[] = $item;

            if (empty($item['productId']) || !empty($item['ppobaseGroupedChild'])) {
                continue;
            }

            $parentProductId = (string) $item['productId'];
            $parentQuantity = max(1, (int) ($item['quantity'] ?? 1));
            $removedChildIds = $item['ppobaseRemovedGroupedChildProductIds'] ?? [];
            if (!\is_array($removedChildIds)) {
                $removedChildIds = [];
            }

            foreach ($this->groupedProductService->getChildrenForProduct($parentProductId, $context) as $child) {
                $childProductId = (string) ($child['childProductId'] ?? '');
                if ($childProductId === '' || \in_array($childProductId, $removedChildIds, true)) {
                    continue;
                }
                if (isset($submittedGroupedChildren[$parentProductId . '-' . $childProductId])) {
                    continue;
                }

                $baseQuantity = max(1, (int) ($child['quantity'] ?? 1));
                $expanded[] = [
                    'productId' => $childProductId,
                    'quantity' => $parentQuantity * $baseQuantity,
                    'unitPrice' => 0.0,
                    'ppobaseGroupedChild' => true,
                    'ppobaseGroupedParentProductId' => $parentProductId,
                    'ppobaseChildBaseQty' => $baseQuantity,
                ];
            }
        }

        return $expanded;
    }

    /**
     * Create a new customer inline. Returns the new customer ID.
     */
    public function createCustomer(array $data, string $salesChannelId, Context $context): string
    {
        // Get default customer group from the sales channel
        $scCriteria = new Criteria([$salesChannelId]);
        $salesChannel = $this->salesChannelRepository->search($scCriteria, $context)->first();
        $customerGroupId = $salesChannel ? $salesChannel->getCustomerGroupId() : null;

        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $data['salutationId'] ?? null;

        $customerData = [
            'id' => $customerId,
            'salesChannelId' => $salesChannelId,
            'groupId' => $customerGroupId,
            'defaultPaymentMethodId' => $salesChannel ? $salesChannel->getPaymentMethodId() : null,
            'languageId' => $salesChannel ? $salesChannel->getLanguageId() : Defaults::LANGUAGE_SYSTEM,
            'customerNumber' => $this->generateCustomerNumber($context),
            'firstName' => $data['firstName'],
            'lastName' => $data['lastName'],
            'email' => $data['email'],
            'password' => bin2hex(random_bytes(16)),
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'firstName' => $data['firstName'],
                    'lastName' => $data['lastName'],
                    'street' => $data['street'] ?? '',
                    'zipcode' => $data['zipcode'] ?? '',
                    'city' => $data['city'] ?? '',
                    'countryId' => $data['countryId'],
                    'phoneNumber' => $data['phone'] ?? null,
                    'company' => $data['company'] ?? null,
                    'salutationId' => $salutationId,
                ],
            ],
        ];

        if ($salutationId) {
            $customerData['salutationId'] = $salutationId;
        }

        $this->customerRepository->create([$customerData], $context);

        $customerName = $data['firstName'] . ' ' . $data['lastName'];
        $this->activityLogService->logManualCustomerCreated($customerId, $customerName, $data['email'], $context);

        return $customerId;
    }

    /**
     * Create a new shipping address for an existing customer. Returns the new address ID.
     */
    public function createCustomerAddress(string $customerId, array $data, Context $context): string
    {
        $addressId = Uuid::randomHex();

        $addressData = [
            'id' => $addressId,
            'customerId' => $customerId,
            'firstName' => $data['firstName'],
            'lastName' => $data['lastName'],
            'street' => $data['street'] ?? '',
            'zipcode' => $data['zipcode'] ?? '',
            'city' => $data['city'] ?? '',
            'countryId' => $data['countryId'],
            'phoneNumber' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'additionalAddressLine1' => $data['additionalAddressLine1'] ?? null,
        ];

        if (!empty($data['salutationId'])) {
            $addressData['salutationId'] = $data['salutationId'];
        }

        $this->customerAddressRepository->create([$addressData], $context);

        return $addressId;
    }

    /**
     * Search existing customers by name, email, or customer number.
     */
    public function searchCustomers(string $term, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('firstName', $term),
            new ContainsFilter('lastName', $term),
            new ContainsFilter('email', $term),
            new ContainsFilter('customerNumber', $term),
            new ContainsFilter('company', $term),
        ]));
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addSorting(new FieldSorting('lastName', FieldSorting::ASCENDING));
        $criteria->setLimit(20);

        $result = $this->customerRepository->search($criteria, $context);

        $customers = [];
        foreach ($result->getEntities() as $customer) {
            $address = $customer->getDefaultBillingAddress();
            $customers[] = [
                'id' => $customer->getId(),
                'customerNumber' => $customer->getCustomerNumber(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'company' => $customer->getCompany(),
                'city' => $address ? $address->getCity() : null,
            ];
        }

        return $customers;
    }

    /**
     * Search products available in the given sales channel.
     * Includes both parent products and variant products (children).
     * Variant products inherit visibility, name, price, tax, and active flag from their parent.
     */
    public function searchProducts(string $term, string $salesChannelId, Context $context): array
    {
        // Enable inheritance so child/variant products inherit parent's visibility, name, price, tax, etc.
        $context->setConsiderInheritance(true);

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('name', $term),
            new ContainsFilter('productNumber', $term),
            new ContainsFilter('ean', $term),
        ]));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('tax');
        $criteria->addAssociation('options.group');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->setLimit(25);

        // Filter by sales channel visibility (inherited from parent for variants)
        $criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $salesChannelId));

        $result = $this->productRepository->search($criteria, $context);

        $products = [];
        foreach ($result->getEntities() as $product) {
            $price = $product->getCurrencyPrice(Defaults::CURRENCY);
            $grossPrice = $price ? $price->getGross() : 0;
            $netPrice = $price ? $price->getNet() : 0;

            // Build display name: include variant options if this is a child product
            $baseName = $product->getTranslation('name') ?? $product->getName() ?? '';
            $displayName = $baseName;

            $options = $product->getOptions();
            if ($options && $options->count() > 0) {
                $optionLabels = [];
                foreach ($options as $option) {
                    $groupName = $option->getGroup() ? ($option->getGroup()->getTranslation('name') ?? $option->getGroup()->getName()) : '';
                    $optionName = $option->getTranslation('name') ?? $option->getName();
                    if ($groupName) {
                        $optionLabels[] = $groupName . ': ' . $optionName;
                    } else {
                        $optionLabels[] = $optionName;
                    }
                }
                $displayName = $baseName . ' (' . implode(', ', $optionLabels) . ')';
            }

            $products[] = [
                'id' => $product->getId(),
                'name' => $displayName,
                'productNumber' => $product->getProductNumber(),
                'price' => $grossPrice,
                'netPrice' => $netPrice,
                'stock' => $product->getStock(),
                'taxRate' => $product->getTax() ? $product->getTax()->getTaxRate() : 0,
                'ean' => $product->getEan(),
                'isVariant' => $product->getParentId() !== null,
            ];
        }

        // Restore context
        $context->setConsiderInheritance(false);

        return $products;
    }

    /**
     * Return all active sales channels.
     */
    public function getSalesChannels(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->salesChannelRepository->search($criteria, $context);

        $channels = [];
        foreach ($result->getEntities() as $channel) {
            $channels[] = [
                'id' => $channel->getId(),
                'name' => $channel->getTranslation('name') ?? $channel->getName(),
                'type' => $channel->getTypeId(),
            ];
        }

        return $channels;
    }

    /**
     * Return shipping methods available for the given sales channel.
     */
    public function getShippingMethods(string $salesChannelId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->shippingMethodRepository->search($criteria, $context);

        $methods = [];
        foreach ($result->getEntities() as $method) {
            $methods[] = [
                'id' => $method->getId(),
                'name' => $method->getTranslation('name') ?? $method->getName(),
            ];
        }

        return $methods;
    }

    /**
     * Return payment methods available for the given sales channel.
     */
    public function getPaymentMethods(string $salesChannelId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->paymentMethodRepository->search($criteria, $context);

        $methods = [];
        foreach ($result->getEntities() as $method) {
            $methods[] = [
                'id' => $method->getId(),
                'name' => $method->getTranslation('name') ?? $method->getName(),
            ];
        }

        return $methods;
    }

    /**
     * Return all addresses for a customer.
     */
    public function getCustomerAddresses(string $customerId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addAssociation('country');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = $this->customerAddressRepository->search($criteria, $context);

        $addresses = [];
        foreach ($result->getEntities() as $address) {
            $addresses[] = [
                'id' => $address->getId(),
                'firstName' => $address->getFirstName(),
                'lastName' => $address->getLastName(),
                'street' => $address->getStreet(),
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'countryId' => $address->getCountryId(),
                'countryName' => $address->getCountry() ? ($address->getCountry()->getTranslation('name') ?? $address->getCountry()->getName()) : null,
                'company' => $address->getCompany(),
                'phoneNumber' => $address->getPhoneNumber(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            ];
        }

        return $addresses;
    }

    /**
     * Load full order details for printing/export.
     */
    public function updateManualOrder(string $orderId, array $data, Context $context): void
    {
        $customFields = [];

        if (array_key_exists('notes', $data)) {
            $notes = $data['notes'] ?? '';
            $customFields['ppobase_order_notes'] = $notes;
        }

        if (array_key_exists('orderReference', $data)) {
            $customFields['ppobase_order_reference'] = $data['orderReference'] ?? '';
        }

        if (array_key_exists('shippingDate', $data)) {
            $customFields['ppobase_shipping_date'] = $data['shippingDate'] ?: null;
        }

        $updateData = ['id' => $orderId];

        if (!empty($customFields)) {
            $updateData['customFields'] = $customFields;
        }

        if (array_key_exists('notes', $data)) {
            $updateData['customerComment'] = $data['notes'] ?? '';
        }

        $this->orderRepository->update([$updateData], $context);
    }

    public function getOrderForPrint(string $orderId, Context $context): array
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('addresses.country');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            return [];
        }

        return $this->buildOrderForPrintFromEntity($order, $context);
    }

    /**
     * Generate a CSV export for multiple manual orders.
     *
     * @param array<string> $orderIds If provided, only these order IDs will be exported
     */
    public function generateManualOrdersCsvBulk(Context $context, array $orderIds = [], string $term = '', int $limit = 1000): string
    {
        $term = trim($term);

        if ($limit <= 0) {
            $limit = 1000;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        $criteria = new Criteria($orderIds ?: null);
        $criteria->addFilter(new EqualsFilter('customFields.ppobase_manual_order', 'yes'));
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('addresses.country');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('currency');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        if (empty($orderIds)) {
            $criteria->setLimit($limit);
        }

        if ($term !== '' && empty($orderIds)) {
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('orderNumber', $term),
                new ContainsFilter('orderCustomer.firstName', $term),
                new ContainsFilter('orderCustomer.lastName', $term),
                new ContainsFilter('orderCustomer.email', $term),
                new ContainsFilter('customFields.ppobase_order_reference', $term),
                new ContainsFilter('customFields.ppobase_order_notes', $term),
            ]));
        }

        $result = $this->orderRepository->search($criteria, $context);

        $ordersData = [];
        foreach ($result->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }
            $ordersData[] = $this->buildOrderForPrintFromEntity($order, $context);
        }

        return $this->generateCsvFromOrderDataList($ordersData);
    }

    private function buildOrderForPrintFromEntity(OrderEntity $order, Context $context): array
    {
        $customer = $order->getOrderCustomer();
        $currency = $order->getCurrency() ? $order->getCurrency()->getIsoCode() : 'EUR';

        // Billing address
        $billingAddress = null;
        $billingAddressId = $order->getBillingAddressId();
        if ($order->getAddresses()) {
            foreach ($order->getAddresses() as $addr) {
                if ($addr->getId() === $billingAddressId) {
                    $billingAddress = $this->formatOrderAddress($addr);
                    break;
                }
            }
        }

        // Shipping address
        $shippingAddress = null;
        $deliveries = $order->getDeliveries();
        $shippingMethodName = null;
        if ($deliveries && $deliveries->count() > 0) {
            $delivery = $deliveries->first();
            $shippingMethodName = $delivery->getShippingMethod() ? ($delivery->getShippingMethod()->getTranslation('name') ?? $delivery->getShippingMethod()->getName()) : null;
            $shippingAddr = $delivery->getShippingOrderAddress();
            if ($shippingAddr) {
                $shippingAddress = $this->formatOrderAddress($shippingAddr);
            }
        }
        if (!$shippingMethodName) {
            $customFields = $order->getCustomFields() ?? [];
            $shippingMethodName = $customFields['ppobase_shipping_method_name'] ?? null;
            if (!$shippingMethodName && !empty($customFields['ppobase_shipping_method_id'])) {
                $shippingMethod = $this->shippingMethodRepository->search(new Criteria([$customFields['ppobase_shipping_method_id']]), $context)->first();
                if ($shippingMethod) {
                    $shippingMethodName = $shippingMethod->getTranslation('name') ?? $shippingMethod->getName();
                }
            }
        }

        // Payment method
        $paymentMethodName = null;
        $transactions = $order->getTransactions();
        if ($transactions && $transactions->count() > 0) {
            $transaction = $transactions->last();
            $paymentMethodName = $transaction->getPaymentMethod() ? ($transaction->getPaymentMethod()->getTranslation('name') ?? $transaction->getPaymentMethod()->getName()) : null;
        }

        // Line items
        $items = [];
        $subtotal = 0;
        $taxAmount = 0;
        if ($order->getLineItems()) {
            foreach ($order->getLineItems() as $lineItem) {
                $unitPrice = $lineItem->getUnitPrice();
                $totalPrice = $lineItem->getTotalPrice();
                $qty = $lineItem->getQuantity();

                $taxRate = 0;
                $lineTax = 0;
                if ($lineItem->getPrice() && $lineItem->getPrice()->getCalculatedTaxes()) {
                    foreach ($lineItem->getPrice()->getCalculatedTaxes() as $tax) {
                        $taxRate = $tax->getTaxRate();
                        $lineTax += $tax->getTax();
                    }
                }

                $subtotal += $totalPrice;
                $taxAmount += $lineTax;

                $items[] = [
                    'productName' => $lineItem->getLabel(),
                    'productNumber' => $lineItem->getPayload()['productNumber'] ?? '',
                    'quantity' => $qty,
                    'unitPrice' => round($unitPrice, 2),
                    'taxRate' => $taxRate,
                    'taxAmount' => round($lineTax, 2),
                    'totalPrice' => round($totalPrice, 2),
                ];
            }
        }

        return [
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'orderDate' => $order->getOrderDateTime()->format('c'),
            'customerName' => $customer ? ($customer->getFirstName() . ' ' . $customer->getLastName()) : 'N/A',
            'customerEmail' => $customer ? $customer->getEmail() : '',
            'customerNumber' => $customer ? $customer->getCustomerNumber() : '',
            'customerId' => $customer ? $customer->getCustomerId() : null,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
            'shippingMethod' => $shippingMethodName,
            'paymentMethod' => $paymentMethodName,
            'currency' => $currency,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'taxAmount' => round($taxAmount, 2),
            'grandTotal' => round($order->getAmountTotal(), 2),
            'orderReference' => ($order->getCustomFields() ?? [])['ppobase_order_reference'] ?? null,
            'notes' => ($order->getCustomFields() ?? [])['ppobase_order_notes']
                ?? ($order->getInternalComment() ?: null),
            'shippingDate' => ($order->getCustomFields() ?? [])['ppobase_shipping_date'] ?? null,
        ];
    }

    /**
     * Generate a CSV export for an order.
     */
    public function generateOrderCsv(string $orderId, Context $context): string
    {
        $orderData = $this->getOrderForPrint($orderId, $context);
        if (empty($orderData)) {
            return '';
        }

        return $this->generateCsvFromOrderDataList([$orderData]);
    }

    private function csvRow(array $fields, string $delimiter = ',', string $enclosure = '"'): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $fields, $delimiter, $enclosure);
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return rtrim($csv, "\r\n");
    }

    private function getOrderCsvHeaderFields(): array
    {
        return [
            'Order Number',
            'Order Date',
            'Customer',
            'Email',
            'Currency',
            'Reference',
            'Notes',
            'Shipping Method',
            'Shipping Date',
            'Product',
            'SKU',
            'Quantity',
            'Unit Price',
            'Tax Rate',
            'Line Total',
            'Subtotal',
            'Tax Amount',
            'Grand Total',
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function orderDataToCsvRows(array $orderData): array
    {
        $base = [
            (string) ($orderData['orderNumber'] ?? ''),
            (string) ($orderData['orderDate'] ?? ''),
            (string) ($orderData['customerName'] ?? ''),
            (string) ($orderData['customerEmail'] ?? ''),
            (string) ($orderData['currency'] ?? ''),
            (string) ($orderData['orderReference'] ?? ''),
            (string) ($orderData['notes'] ?? ''),
            (string) ($orderData['shippingMethod'] ?? ''),
            (string) ($orderData['shippingDate'] ?? ''),
        ];

        $totals = [
            (string) ($orderData['subtotal'] ?? ''),
            (string) ($orderData['taxAmount'] ?? ''),
            (string) ($orderData['grandTotal'] ?? ''),
        ];

        $items = $orderData['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            return [
                array_merge($base, ['', '', '', '', '', ''], $totals),
            ];
        }

        $rows = [];
        foreach ($items as $item) {
            $rows[] = array_merge($base, [
                (string) ($item['productName'] ?? ''),
                (string) ($item['productNumber'] ?? ''),
                (string) ($item['quantity'] ?? ''),
                (string) ($item['unitPrice'] ?? ''),
                (string) ($item['taxRate'] ?? ''),
                (string) ($item['totalPrice'] ?? ''),
            ], $totals);
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $ordersData
     */
    private function generateCsvFromOrderDataList(array $ordersData): string
    {
        // Excel-friendly CSV:
        // - UTF-8 BOM (for Excel on Windows)
        // - "sep=," hint so Excel uses comma even in locales where list-separator is ';'
        // - CRLF line endings
        $eol = "\r\n";
        $csv = "\xEF\xBB\xBF" . 'sep=,' . $eol;

        $csv .= $this->csvRow($this->getOrderCsvHeaderFields()) . $eol;

        foreach ($ordersData as $orderData) {
            foreach ($this->orderDataToCsvRows($orderData) as $row) {
                $csv .= $this->csvRow($row) . $eol;
            }
        }

        return $csv;
    }

    /**
     * Get recent manual orders from activity log.
     */
    public function getRecentManualOrders(Context $context, string $term = '', int $limit = 50, int $page = 1): array
    {
        $term = trim($term);
        if ($page < 1) {
            $page = 1;
        }
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 250) {
            $limit = 250;
        }

        // Query orders with the ppobase_manual_order custom field
        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('customFields.ppobase_manual_order', 'yes'));
        $orderCriteria->addAssociation('orderCustomer');
        $orderCriteria->addAssociation('currency');
        $orderCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $orderCriteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $orderCriteria->setLimit($limit);
        $orderCriteria->setOffset(($page - 1) * $limit);

        if ($term !== '') {
            $orderCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('orderNumber', $term),
                new ContainsFilter('orderCustomer.firstName', $term),
                new ContainsFilter('orderCustomer.lastName', $term),
                new ContainsFilter('orderCustomer.email', $term),
                new ContainsFilter('customFields.ppobase_order_reference', $term),
                new ContainsFilter('customFields.ppobase_order_notes', $term),
            ]));
        }

        $result = $this->orderRepository->search($orderCriteria, $context);
        $total = $result->getTotal();

        $orders = [];
        foreach ($result->getEntities() as $order) {
            $customer = $order->getOrderCustomer();
            $orders[] = [
                'id' => $order->getId(),
                'orderId' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'customerName' => $customer ? ($customer->getFirstName() . ' ' . $customer->getLastName()) : 'N/A',
                'customerEmail' => $customer ? $customer->getEmail() : '',
                'total' => $order->getAmountTotal(),
                'currency' => $order->getCurrency() ? $order->getCurrency()->getIsoCode() : 'EUR',
                'createdAt' => $order->getCreatedAt() ? $order->getCreatedAt()->format('c') : null,
                'orderReference' => ($order->getCustomFields() ?? [])['ppobase_order_reference'] ?? null,
            ];
        }

        return [
            'data' => $orders,
            'total' => $total,
        ];
    }

    private function formatOrderAddress($address): array
    {
        return [
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() ? ($address->getCountry()->getTranslation('name') ?? $address->getCountry()->getName()) : '',
            'company' => $address->getCompany(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
        ];
    }

    private function generateCustomerNumber(Context $context): string
    {
        return 'MC-' . strtoupper(substr(Uuid::randomHex(), 0, 8));
    }
}
