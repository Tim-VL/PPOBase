<?php declare(strict_types=1);

namespace PPOBase\Subscriber;

use PPOBase\Service\GroupedProductService;
use PPOBase\Struct\GroupedProductsStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GroupedProductStorefrontSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly GroupedProductService $groupedProductService,
        private readonly EntityRepository      $productRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $product   = $event->getPage()->getProduct();
        $productId = $product->getId();
        $salesChannelContext = $event->getSalesChannelContext();
        $context             = $salesChannelContext->getContext();

        $children = $this->groupedProductService->getChildrenForProduct($productId, $context);

        if (!empty($children)) {
            // Enrich each child with a formatted price for the current currency
            $childIds   = array_column($children, 'childProductId');
            $pCriteria  = new Criteria($childIds);
            $products   = $this->productRepository->search($pCriteria, $context);
            $currencyId = $salesChannelContext->getCurrencyId();
            $symbol     = $salesChannelContext->getCurrency()->getSymbol();

            foreach ($children as &$child) {
                $p = $products->get($child['childProductId']);
                if (!$p) {
                    continue;
                }
                // getCurrencyPrice() returns gross/net prices from the product price table
                $currencyPrice = $p->getPrice()?->getCurrencyPrice($currencyId);
                if ($currencyPrice === null && $p->getParent()) {
                    $currencyPrice = $p->getParent()?->getPrice()?->getCurrencyPrice($currencyId);
                }
                $child['unitPrice']     = $currencyPrice?->getGross() ?? 0.0;
                $child['currencySymbol'] = $symbol;
            }
            unset($child);
        }

        $event->getPage()->addExtension(
            'ppobaseGroupedProducts',
            new GroupedProductsStruct($children)
        );
    }
}
