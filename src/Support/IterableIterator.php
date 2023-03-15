<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\IIterable;

/**
 * Converts an iterable to an Iterator with expressive helper methods
 *
 * @template TValue
 * @template TIterable as \Traversable<int,TValue>
 * @extends \IteratorIterator<int,TValue,TIterable>
 * @implements IIterable<TValue>
 */
final class IterableIterator extends \IteratorIterator implements IIterable
{
    /**
     * @param iterable<TValue> $iterable
     */
    public function __construct(iterable $iterable)
    {
        if (!($iterable instanceof \Traversable)) {
            $iterable = new \ArrayIterator($iterable);
        }
        parent::__construct($iterable);
    }

    /**
     * @param iterable<TValue> $iterable
     */
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
