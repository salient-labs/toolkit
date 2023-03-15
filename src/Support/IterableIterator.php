<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\FluentIterator;

/**
 * Wrap an Iterator with expressive helper methods around an array or
 * Traversable
 *
 * @template TKey
 * @template TValue
 * @template TIterable as \Traversable<TKey,TValue>
 * @extends \IteratorIterator<TKey,TValue,TIterable>
 * @implements FluentIterator<TKey,TValue>
 */
final class IterableIterator extends \IteratorIterator implements FluentIterator
{
    /**
     * @psalm-param \Traversable<TKey,TValue>|array<TKey,TValue> $iterable
     */
    public function __construct(iterable $iterable)
    {
        if (!($iterable instanceof \Traversable)) {
            $iterable = new \ArrayIterator($iterable);
        }
        parent::__construct($iterable);
    }

    public static function from(iterable $iterable): self
    {
        return new self($iterable);
    }

    public function nextWithValue($key, $value, bool $strict = false)
    {
        while ($this->valid()) {
            $item = $this->current();
            $this->next();
            if (is_array($item) || $item instanceof \ArrayAccess) {
                $_value = $item[$key];
            } else {
                $_value = $item->$key;
            }
            if (($strict && $_value === $value) ||
                    (!$strict && $_value == $value)) {
                return $item;
            }
        }

        return false;
    }
}
