<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\ListInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;

/**
 * Implements ListInterface
 *
 * Unless otherwise noted, {@see ListTrait} methods operate on one instance of
 * the class. Immutable lists should use {@see ImmutableListTrait} instead.
 *
 * @see ListInterface
 *
 * @todo Remove `@template TKey of int` when PHPStan propagates trait templates
 * properly
 *
 * @api
 *
 * @template TKey of int
 * @template TValue
 *
 * @phpstan-require-implements ListInterface
 */
trait ListTrait
{
    /** @use CollectionTrait<int,TValue> */
    use CollectionTrait {
        getItems as private _getItems;
        replaceItems as private _replaceItems;
    }

    /**
     * @inheritDoc
     */
    public function add($value)
    {
        $items = $this->Items;
        $items[] = $value;
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value)
    {
        $this->checkKey($key);
        $items = $this->Items;
        $items[$key] = $value;
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function merge($items)
    {
        $_items = $this->getItems($items);
        if (!$_items) {
            return $this;
        }
        $items = array_merge($this->Items, $_items);
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function push(...$item)
    {
        if (!$item) {
            return $this;
        }
        $clone = $this->maybeClone();
        array_push($clone->Items, ...$item);
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function unshift(...$item)
    {
        if (!$item) {
            return $this;
        }
        $clone = $this->maybeClone();
        array_unshift($clone->Items, ...$item);
        return $clone;
    }

    /**
     * @param int|null $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->Items[] = $value;
            return;
        }
        $this->checkKey($offset, 'offset');
        $this->Items[$offset] = $value;
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset): void
    {
        if (
            !array_key_exists($offset, $this->Items)
            || $offset === array_key_last($this->Items)
        ) {
            unset($this->Items[$offset]);
            return;
        }
        $items = $this->Items;
        unset($items[$offset]);
        $this->Items = array_values($items);
    }

    /**
     * @param Arrayable<array-key,TValue>|iterable<array-key,TValue> $items
     * @return list<TValue>
     */
    protected function getItems($items): array
    {
        return array_values($this->_getItems($items));
    }

    /**
     * @param array<int,TValue> $items
     * @return static
     */
    protected function replaceItems(array $items, ?bool $getClone = null)
    {
        $key = array_key_last($items);
        if ($key === null || $key === count($items) - 1) {
            return $this->_replaceItems($items, $getClone);
        }
        return $this->_replaceItems(array_values($items), $getClone);
    }

    /**
     * @param int $key
     * @throws InvalidArgumentException if `$key` is not an integer, or does not
     * resolve to an existing item and is not the next numeric key in the list.
     */
    private function checkKey($key, string $argument = 'key'): void
    {
        if (!is_int($key)) {
            throw new InvalidArgumentTypeException(1, $argument, 'int', $key);
        }

        if (
            !array_key_exists($key, $this->Items)
            && $key !== count($this->Items)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Item cannot be added with key: %d',
                $key,
            ));
        }
    }
}
