<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator\Concern;

use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;

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
     * @return array<TKey,TValue>
     */
    public function toArray(): array
    {
        foreach ($this as $current) {
            $array[] = $current;
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
     * @param callable(TValue): bool $callback
     * @return $this
     */
    public function forEachWhileTrue(callable $callback, ?bool &$result = null)
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
     * @return TValue|false `false` if no matching element is found.
     */
    public function nextWithValue($key, $value, bool $strict = false)
    {
        foreach ($this as $current) {
            if (is_array($current) || $current instanceof \ArrayAccess) {
                $_value = $current[$key];
            } else {
                $_value = $current->$key;
            }
            if (($strict && $_value === $value) ||
                    (!$strict && $_value == $value)) {
                return $current;
            }
        }
        return false;
    }
}
