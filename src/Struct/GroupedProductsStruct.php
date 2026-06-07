<?php declare(strict_types=1);

namespace PPOBase\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Wraps the flat array of grouped-product data for page extensions.
 *
 * Implements \Countable and \IteratorAggregate so Twig can check
 * "is not empty" and iterate with "for item in struct" natively.
 *
 * @package PPOBase
 */
class GroupedProductsStruct extends Struct implements \Countable, \IteratorAggregate
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(private readonly array $items = [])
    {
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return array<int, array<string, mixed>> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getApiAlias(): string
    {
        return 'ppobase_grouped_products';
    }
}
