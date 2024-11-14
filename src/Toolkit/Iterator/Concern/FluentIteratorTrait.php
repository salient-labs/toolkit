<?php declare(strict_types=1);

namespace Salient\Iterator\Concern;

use Salient\Contract\Iterator\FluentIteratorInterface;

/**
 * Implements FluentIteratorInterface
 *
 * @see FluentIteratorInterface
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-require-implements FluentIteratorInterface
 */
trait FluentIteratorTrait
{
    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function forEach(callable $callback)
    {
        foreach ($this as $key => $value) {
            $callback($value, $key);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function nextWithValue($key, $value, bool $strict = false)
    {
        $found = null;
        foreach ($this as $current) {
            // Move forward-only iterators to the next element
            if ($found) {
                break;
            }
            if (is_array($current)) {
                if (array_key_exists($key, $current)) {
                    $_value = $current[$key];
                } else {
                    continue;
                }
            } elseif (is_object($current)) {
                $_value = $current->$key;
            } else {
                continue;
            }
            if ($strict) {
                if ($_value === $value) {
                    $found = $current;
                }
            } elseif ($_value == $value) {
                $found = $current;
            }
        }
        // @phpstan-ignore return.type
        return $found;
    }
}
