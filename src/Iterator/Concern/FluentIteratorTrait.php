<?php declare(strict_types=1);

namespace Lkrms\Iterator\Concern;

use Lkrms\Iterator\Contract\FluentIteratorInterface;

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
     * @return array<TKey,TValue>|list<TValue>
     * @phpstan-return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
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
     * @param callable(TValue): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback)
    {
        foreach ($this as $current) {
            $callback($current);
        }
        return $this;
    }

    /**
     * @param callable(TValue): (true|mixed) $callback
     * @return $this
     */
    public function forEachWhile(callable $callback, ?bool &$result = null)
    {
        foreach ($this as $current) {
            if ($callback($current) !== true) {
                $result = false;
                return $this;
            }
        }
        $result = true;
        return $this;
    }

    /**
     * @param array-key $key
     * @param mixed $value
     * @return TValue|false
     */
    public function nextWithValue($key, $value, bool $strict = false)
    {
        foreach ($this as $current) {
            if (is_array($current) || $current instanceof \ArrayAccess) {
                $_value = $current[$key];
            } else {
                $_value = $current->$key;
            }
            if ($strict) {
                if ($_value === $value) {
                    return $current;
                }
            } elseif ($_value == $value) {
                return $current;
            }
        }
        return false;
    }
}
