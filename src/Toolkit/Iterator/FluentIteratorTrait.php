<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Contract\Iterator\FluentIteratorInterface;

/**
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
    public function getFirstWith($key, $value, bool $strict = false)
    {
        foreach ($this as $current) {
            if (is_array($current)) {
                if (array_key_exists($key, $current)) {
                    $_value = $current[$key];
                } else {
                    continue;
                }
            } elseif (is_object($current)) {
                if (property_exists($current, (string) $key)) {
                    $_value = $current->{$key};
                } else {
                    continue;
                }
            } else {
                continue;
            }
            if ($strict) {
                if ($_value === $value) {
                    return $current;
                }
            } elseif ($_value == $value) {
                return $current;
            }
        }
        return null;
    }
}
