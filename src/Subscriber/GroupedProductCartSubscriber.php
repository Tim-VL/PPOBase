<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use PPOBase\Service\GroupedProductService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps configured grouped-product children in the cart as real product line items.
 */
class GroupedProductCartSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GroupedProductService $groupedProductService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeLineItemAddedEvent::class => 'onLineItemAdded',
            BeforeLineItemQuantityChangedEvent::class => 'onLineItemQuantityChanged',
            BeforeLineItemRemovedEvent::class => 'onLineItemRemoved',
            CartLoadedEvent::class => 'onCartLoaded',
            CheckoutCartPageLoadedEvent::class => 'onCheckoutCartPageLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            OffcanvasCartPageLoadedEvent::class => 'onOffcanvasCartPageLoaded',
        ];
    }

    /**
     * Runs on every cart load so that grouped children stored before this change
     * (when removable was true) are corrected without needing a cart reset.
     */
    public function onCartLoaded(CartLoadedEvent $event): void
    {
        $this->disableGroupedChildRemoval($event->getCart());
    }

    public function onCheckoutCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $this->disableGroupedChildRemoval($event->getPage()->getCart());
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $this->disableGroupedChildRemoval($event->getPage()->getCart());
    }

    public function onOffcanvasCartPageLoaded(OffcanvasCartPageLoadedEvent $event): void
    {
        $this->disableGroupedChildRemoval($event->getPage()->getCart());
    }

    public function onLineItemAdded(BeforeLineItemAddedEvent $event): void
    {
        $lineItem = $this->getCurrentLineItem($event->getCart(), $event->getLineItem());
        if (!$this->isGroupedParentCandidate($lineItem)) {
            return;
        }

        $this->syncGroupedChildren($event->getCart(), $lineItem, $event->getContext());
    }

    public function onLineItemQuantityChanged(BeforeLineItemQuantityChangedEvent $event): void
    {
        $lineItem = $event->getLineItem();
        if (($lineItem->getPayload()['ppobase_grouped_child'] ?? false) === true) {
            $this->resetGroupedChildQuantity($event->getCart(), $lineItem, $event->getBeforeUpdateQuantity());

            return;
        }

        if (!$this->isGroupedParentCandidate($lineItem)) {
            return;
        }

        $this->syncGroupedChildren($event->getCart(), $lineItem, $event->getContext());
    }

    public function onLineItemRemoved(BeforeLineItemRemovedEvent $event): void
    {
        $lineItem = $event->getLineItem();
        if (($lineItem->getPayload()['ppobase_grouped_child'] ?? false) === true) {
            // Grouped children are not user-removable; add back immediately so
            // that if the route still calls cart->remove() the re-sync on the
            // next cart recalculation will restore it.
            $event->getCart()->add($lineItem);

            return;
        }

        if (!$this->isGroupedParentCandidate($lineItem)) {
            return;
        }

        // Parent removed → remove all its children.
        $lineItems = $event->getCart()->getLineItems();
        foreach ($lineItems as $cartLineItem) {
            if (($cartLineItem->getPayload()['ppobase_grouped_parent_line_item_id'] ?? null) === $lineItem->getId()) {
                $lineItems->remove($cartLineItem->getId());
            }
        }
    }

    private function syncGroupedChildren(Cart $cart, LineItem $parentLineItem, Context $context): void
    {
        $parentProductId = $parentLineItem->getReferencedId();
        if (!$parentProductId) {
            return;
        }

        $children = $this->groupedProductService->getChildrenForProduct($parentProductId, $context);

        foreach ($children as $child) {
            $childProductId = (string) ($child['childProductId'] ?? '');
            if ($childProductId === '' || $childProductId === $parentProductId) {
                continue;
            }
            if ($this->isChildRemovedFromParent($parentLineItem, $childProductId)) {
                continue;
            }

            $quantity = max(1, $parentLineItem->getQuantity()) * max(1, (int) ($child['quantity'] ?? 1));
            $childLineItemId = $this->getGroupedChildLineItemId($parentLineItem->getId(), $childProductId);

            $existingChildLineItem = $cart->get($childLineItemId);
            if ($existingChildLineItem instanceof LineItem) {
                $existingChildLineItem->setStackable(true);
                $existingChildLineItem->setQuantity($quantity);
                $this->setGroupedChildPayload($existingChildLineItem, $parentLineItem, $child, $quantity);
                continue;
            }

            $childLineItem = new LineItem(
                $childLineItemId,
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                $childProductId,
                $quantity
            );
            $childLineItem->setLabel(($child['productName'] ?? null) ?: ($child['productNumber'] ?? null) ?: $childProductId);
            $this->setGroupedChildPayload($childLineItem, $parentLineItem, $child, $quantity);

            $cart->add($childLineItem);
        }
    }

    /**
     * Grouped children are real product line items, but the child quantity is owned by the admin ratio.
     */
    private function setGroupedChildPayload(
        LineItem $childLineItem,
        LineItem $parentLineItem,
        array $child,
        int $quantity
    ): void {
        $childLineItem->setStackable(true);
        $childLineItem->setQuantity($quantity);
        $childLineItem->setRemovable(false);
        $childLineItem->setPayloadValue('ppobase_grouped_child', true);
        $childLineItem->setPayloadValue('ppobase_grouped_parent_line_item_id', $parentLineItem->getId());
        $childLineItem->setPayloadValue('ppobase_grouped_parent_product_id', $parentLineItem->getReferencedId());
        $childLineItem->setPayloadValue('ppobase_child_base_qty', max(1, (int) ($child['quantity'] ?? 1)));
        if (!empty($child['coverUrl'])) {
            $childLineItem->setPayloadValue('ppobase_cover_url', $child['coverUrl']);
        }
        if (!empty($child['productNumber'])) {
            $childLineItem->setPayloadValue('productNumber', $child['productNumber']);
        }
    }

    private function resetGroupedChildQuantity(Cart $cart, LineItem $childLineItem, int $fallbackQuantity): void
    {
        $parentLineItemId = $childLineItem->getPayload()['ppobase_grouped_parent_line_item_id'] ?? null;
        $baseQuantity = max(1, (int) ($childLineItem->getPayload()['ppobase_child_base_qty'] ?? 1));
        $parentLineItem = $parentLineItemId ? $cart->get((string) $parentLineItemId) : null;
        $quantity = $parentLineItem instanceof LineItem
            ? max(1, $parentLineItem->getQuantity()) * $baseQuantity
            : max(1, $fallbackQuantity);

        $childLineItem->setQuantity($quantity);
    }

    private function getCurrentLineItem(Cart $cart, LineItem $lineItem): LineItem
    {
        return $cart->get($lineItem->getId()) ?? $lineItem;
    }

    private function disableGroupedChildRemoval(Cart $cart): void
    {
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getPayload()['ppobase_grouped_child'] ?? false) {
                $lineItem->setRemovable(false);
            }
        }
    }

    private function rememberRemovedChild(Cart $cart, LineItem $childLineItem): void
    {
        $parentLineItemId = $childLineItem->getPayload()['ppobase_grouped_parent_line_item_id'] ?? null;
        $childProductId = $childLineItem->getReferencedId();
        if (!$parentLineItemId || !$childProductId) {
            return;
        }

        $parentLineItem = $cart->get((string) $parentLineItemId);
        if (!$parentLineItem instanceof LineItem) {
            return;
        }

        $removedChildIds = $parentLineItem->getPayload()['ppobase_removed_child_product_ids'] ?? [];
        if (!\is_array($removedChildIds)) {
            $removedChildIds = [];
        }
        if (!\in_array($childProductId, $removedChildIds, true)) {
            $removedChildIds[] = $childProductId;
        }

        $parentLineItem->setPayloadValue('ppobase_removed_child_product_ids', $removedChildIds);
    }

    private function isChildRemovedFromParent(LineItem $parentLineItem, string $childProductId): bool
    {
        $removedChildIds = $parentLineItem->getPayload()['ppobase_removed_child_product_ids'] ?? [];

        return \is_array($removedChildIds) && \in_array($childProductId, $removedChildIds, true);
    }

    private function isGroupedParentCandidate(LineItem $lineItem): bool
    {
        return $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE
            && !($lineItem->getPayload()['ppobase_grouped_child'] ?? false)
            && $lineItem->getReferencedId() !== null;
    }

    private function getGroupedChildLineItemId(string $parentLineItemId, string $childProductId): string
    {
        return 'ppobase-' . md5($parentLineItemId . '-' . $childProductId);
    }
}
