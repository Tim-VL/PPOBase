<?php declare(strict_types=1);

namespace PPOBase\Service;

use Doctrine\DBAL\Connection;
use PPOBase\Entity\GroupedProduct\GroupedProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Content\Product\ProductEntity;

class GroupedProductService
{
    public function __construct(
        private readonly EntityRepository $groupedProductRepository,
        private readonly EntityRepository $productRepository,
        private readonly Connection       $connection
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Used by storefront and cart — respects the is_grouped_active flag.
     */
    public function getChildrenForProduct(string $productId, Context $context): array
    {
        $children = $this->queryChildren($productId, $context);
        if (!empty($children)) {
            return $this->isGroupedActive($productId) ? $children : [];
        }

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        if ($product instanceof ProductEntity && $product->getParentId()) {
            $children = $this->queryChildren($product->getParentId(), $context);
            return (!empty($children) && $this->isGroupedActive($product->getParentId())) ? $children : [];
        }

        return [];
    }

    /**
     * Used by admin — always returns all assigned children regardless of active flag.
     */
    public function getAllChildrenForProduct(string $productId, Context $context): array
    {
        $children = $this->queryChildren($productId, $context);
        if (!empty($children)) {
            return $children;
        }

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        if ($product instanceof ProductEntity && $product->getParentId()) {
            return $this->queryChildren($product->getParentId(), $context);
        }

        return [];
    }

    public function isGroupedActive(string $productId): bool
    {
        $hex   = str_replace('-', '', $productId);
        $value = $this->connection->fetchOne(
            'SELECT is_grouped_active FROM ppobase_product_extension WHERE product_id = UNHEX(:id)',
            ['id' => $hex]
        );

        return $value === false || (bool) $value;
    }

    /** @return array<int, array<string, mixed>> */
    private function queryChildren(string $parentProductId, Context $context): array
    {
        // Step 1: fetch grouped-product rows (just FK data + sort order)
        $gCriteria = new Criteria();
        $gCriteria->addFilter(new EqualsFilter('parentProductId', $parentProductId));
        $gCriteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        $rows = $this->groupedProductRepository->search($gCriteria, $context);
        if ($rows->count() === 0) {
            return [];
        }

        // Step 2: load products DIRECTLY so DAL generates the inheritance COALESCE SQL
        // that merges variant translations with parent translations automatically.
        // Loading through association (childProduct.translations) bypasses this mechanism.
        $childIds = array_values(array_map(
            static fn(GroupedProductEntity $r) => $r->getChildProductId(),
            $rows->getElements()
        ));

        $pCriteria = new Criteria($childIds);
        $pCriteria->addAssociation('cover.media');
        $pCriteria->addAssociation('options.group');

        $products = $this->productRepository->search($pCriteria, $context);
        $parents = $this->loadParents($products->getElements(), $context);

        // Step 3: build output in original sort order
        $children = [];
        /** @var GroupedProductEntity $row */
        foreach ($rows->getElements() as $row) {
            $child = $products->get($row->getChildProductId());
            if (!$child) {
                continue;
            }
            $parent = $parents[$child->getParentId()] ?? null;

            $unitPrice = $this->getGrossPrice($child, $parent);
            $qty       = $row->getQuantity();
            $children[] = [
                'id'             => $row->getId(),
                'childProductId' => $row->getChildProductId(),
                'quantity'       => $qty,
                'position'       => $row->getPosition(),
                'productName'    => $this->getDisplayName($child, $parent),
                'productNumber'  => $child->getProductNumber() ?? '',
                'coverUrl'       => $this->getCoverUrl($child, $parent),
                'price'          => $unitPrice,
                'subtotal'       => $unitPrice !== null ? round($unitPrice * $qty, 2) : null,
            ];
        }

        return $children;
    }

    /**
     * @param array<string, ProductEntity> $products
     *
     * @return array<string, ProductEntity>
     */
    private function loadParents(array $products, Context $context): array
    {
        $parentIds = [];
        foreach ($products as $product) {
            if ($product instanceof ProductEntity && $product->getParentId()) {
                $parentIds[] = $product->getParentId();
            }
        }

        $parentIds = array_values(array_unique($parentIds));
        if (empty($parentIds)) {
            return [];
        }

        $criteria = new Criteria($parentIds);
        $criteria->addAssociation('cover.media');

        $parents = [];
        foreach ($this->productRepository->search($criteria, $context)->getElements() as $parent) {
            if ($parent instanceof ProductEntity) {
                $parents[$parent->getId()] = $parent;
            }
        }

        return $parents;
    }

    private function getDisplayName(ProductEntity $product, ?ProductEntity $parent): string
    {
        $productNumber = $product->getProductNumber() ?? '';

        $name = ($product->getTranslated()['name'] ?? null)
            ?: $product->getName()
            ?: ($parent?->getTranslated()['name'] ?? null)
            ?: $parent?->getName();

        if ($parent && $name === $productNumber) {
            $name = ($parent->getTranslated()['name'] ?? null) ?: $parent->getName() ?: $name;
        }

        if ($parent && $product->getOptions() && $product->getOptions()->count() > 0) {
            $optionNames = [];
            foreach ($product->getOptions() as $option) {
                $optionName = $option->getTranslated()['name'] ?? $option->getName();
                if ($optionName) {
                    $optionNames[] = $optionName;
                }
            }

            if (!empty($optionNames)) {
                $name .= ' (' . implode(', ', $optionNames) . ')';
            }
        }

        return $name ?: $productNumber;
    }

    private function getCoverUrl(ProductEntity $product, ?ProductEntity $parent): ?string
    {
        if ($product->getCover()?->getMedia()) {
            return $product->getCover()->getMedia()->getUrl();
        }

        return $parent?->getCover()?->getMedia()?->getUrl();
    }

    private function getGrossPrice(ProductEntity $product, ?ProductEntity $parent): ?float
    {
        $priceCollection = $product->getPrice() ?? $parent?->getPrice();
        if ($priceCollection === null || $priceCollection->count() === 0) {
            return null;
        }

        $price = $priceCollection->first();

        return $price ? $price->getGross() : null;
    }
}
