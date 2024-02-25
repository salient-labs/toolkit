<?php declare(strict_types=1);

namespace Salient\Iterator\Concern;

use Salient\Iterator\Contract\FluentIteratorInterface;
use ArrayAccess;

/**
 * Implements FluentIteratorInterface
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see FluentIteratorInterface
 */
trait FluentIteratorTrait
{
    /**
     * @return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public function toArray(bool $preserveKeys = true): array
    {
        if ($preserveKeys) {
            foreach ($this as $key => $value) {
                $array[$key] = $value;
            }
        } else {
            foreach ($this as $value) {
                $array[] = $value;
            }
        }
        return $array ?? [];
    }

    /**
     * @param callable(TValue, TKey): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback)
    {
        foreach ($this as $key => $value) {
            $callback($value, $key);
        }
        return $this;
    }

    /**
     * @param array-key $key
     * @param mixed $value
     * @return TValue|null
     */
    public function nextWithValue($key, $value, bool $strict = false)
    {
        foreach ($this as $current) {
            // Move forward-only iterators to the next element
            if (isset($found)) {
                break;
            }
            if (is_array($current) || $current instanceof ArrayAccess) {
                $_value = $current[$key];
            } else {
                $_value = $current->$key;
            }
            if ($strict) {
                if ($_value === $value) {
                    $found = $current;
                }
            } elseif ($_value == $value) {
                $found = $current;
            }
        }
        return $found ?? null;
    }
}
