<?php declare(strict_types=1);

namespace PPOBase\Cart;

use PPOBase\Service\GroupedProductService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Runs after ProductCartProcessor and forces grouped child line items to €0.
 */
class GroupedProductCartProcessor implements CartProcessorInterface
{
    public function __construct(
        private readonly GroupedProductService $groupedProductService
    ) {
    }

    public function process(
        CartDataCollection  $data,
        Cart                $original,
        Cart                $toCalculate,
        SalesChannelContext $context,
        CartBehavior        $behavior
    ): void {
        foreach ($toCalculate->getLineItems()->getElements() as $lineItem) {
            if (!($lineItem->getPayload()['ppobase_grouped_child'] ?? false)) {
                continue;
            }

            $lineItem->setStackable(true);
            $lineItem->setRemovable(true);
            $lineItem->setPrice(new CalculatedPrice(
                0.0,
                0.0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                $lineItem->getQuantity()
            ));
        }

        foreach ($toCalculate->getLineItems()->getElements() as $parentLineItem) {
            if ($parentLineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }
            if ($parentLineItem->getPayload()['ppobase_grouped_child'] ?? false) {
                continue;
            }
            if ($parentLineItem->getPayload()['ppobase_skip_grouped_children'] ?? false) {
                continue;
            }

            $parentProductId = $parentLineItem->getReferencedId();
            if (!$parentProductId) {
                continue;
            }

            $children = $this->groupedProductService->getChildrenForProduct($parentProductId, $context->getContext());
            foreach ($children as $child) {
                $childProductId = (string) ($child['childProductId'] ?? '');
                if ($childProductId === '' || $childProductId === $parentProductId) {
                    continue;
                }
                if ($this->isChildRemovedFromParent($parentLineItem, $childProductId)) {
                    continue;
                }

                $childLineItemId = $this->getGroupedChildLineItemId($parentLineItem->getId(), $childProductId);
                if ($toCalculate->has($childLineItemId)) {
                    continue;
                }

                $quantity = max(1, $parentLineItem->getQuantity()) * max(1, (int) ($child['quantity'] ?? 1));
                $childLineItem = new LineItem(
                    $childLineItemId,
                    LineItem::PRODUCT_LINE_ITEM_TYPE,
                    $childProductId,
                    $quantity
                );
                $childLineItem->setLabel(($child['productName'] ?? null) ?: ($child['productNumber'] ?? null) ?: $childProductId);
                $childLineItem->setStackable(true);
                $childLineItem->setRemovable(true);
                $childLineItem->setPayloadValue('ppobase_grouped_child', true);
                $childLineItem->setPayloadValue('ppobase_grouped_parent_line_item_id', $parentLineItem->getId());
                $childLineItem->setPayloadValue('ppobase_grouped_parent_product_id', $parentProductId);
                $childLineItem->setPayloadValue('ppobase_child_base_qty', max(1, (int) ($child['quantity'] ?? 1)));
                if (!empty($child['coverUrl'])) {
                    $childLineItem->setPayloadValue('ppobase_cover_url', $child['coverUrl']);
                }
                if (!empty($child['productNumber'])) {
                    $childLineItem->setPayloadValue('productNumber', $child['productNumber']);
                }
                $childLineItem->setPrice(new CalculatedPrice(
                    0.0,
                    0.0,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection(),
                    $quantity
                ));

                $toCalculate->add($childLineItem);
            }
        }
    }

    private function getGroupedChildLineItemId(string $parentLineItemId, string $childProductId): string
    {
        return 'ppobase-' . md5($parentLineItemId . '-' . $childProductId);
    }

    private function isChildRemovedFromParent(LineItem $parentLineItem, string $childProductId): bool
    {
        $removedChildIds = $parentLineItem->getPayload()['ppobase_removed_child_product_ids'] ?? [];

        return \is_array($removedChildIds) && \in_array($childProductId, $removedChildIds, true);
    }
}
