<?php declare(strict_types=1);

namespace Lkrms\Support;

use Iterator;
use Lkrms\Concern\HasMutator;
use Lkrms\Contract\IImmutable;
use UnexpectedValueException;

/**
 * Wraps (some of) PHP's array functions in a fluent interface
 *
 * @template TKey of array-key
 * @template TValue
 */
final class FluentArray implements IImmutable
{
    use HasMutator;

    /**
     * @var array<TKey,TValue>
     */
    private $Array;

    /**
     * @param array<TKey,TValue>|Iterator<TKey,TValue> $array
     */
    public function __construct($array)
    {
        $this->Array = $this->getArray($array);
    }

    /**
     * @param array<TKey,TValue>|Iterator<TKey,TValue> $array
     * @return self<TKey,TValue>
     */
    public static function from($array): self
    {
        return new self($array);
    }

    /**
     * @template T
     * @param callable(TValue, mixed...): T $callback
     * @param array<array-key,mixed>|Iterator<array-key,mixed>|FluentArray<array-key,mixed> ...$arrays
     * @return $this<TKey,T>
     */
    public function map(?callable $callback, ...$arrays)
    {
        $clone = $this->mutate();
        $clone->Array = array_map($callback, $clone->Array, ...$this->getArrays($arrays));
        return $clone;
    }

    /**
     * @param (callable(TValue): bool)|(callable(TKey): bool)|(callable(TValue, TKey): bool) $callback
     * @return $this
     */
    public function filter(?callable $callback, int $mode = 0)
    {
        $clone = $this->mutate();
        $clone->Array = array_filter($clone->Array, $callback, $mode);
        return $clone;
    }

    /**
     * @template T0 of array-key
     * @template T1
     *
     * @param array<T0,T1>|Iterator<T0,T1>|FluentArray<T0,T1> ...$arrays
     * @return $this<TKey|T0,TValue|T1>
     */
    public function merge(...$arrays)
    {
        $clone = $this->mutate();
        $clone->Array = array_merge($clone->Array, ...$this->getArrays($arrays));
        return $clone;
    }

    /**
     * Keep entries whose values are absent from all $arrays, preserving keys
     *
     * @param array<array-key,mixed>|Iterator<array-key,mixed>|FluentArray<array-key,mixed> ...$arrays
     * @return $this
     */
    public function diff(...$arrays)
    {
        $clone = $this->mutate();
        $clone->Array = array_diff($clone->Array, ...$this->getArrays($arrays));
        return $clone;
    }

    /**
     * Keep entries whose keys are absent from all $arrays
     *
     * @param array<array-key,mixed>|Iterator<array-key,mixed>|FluentArray<array-key,mixed> ...$arrays
     * @return $this
     */
    public function diffKey(...$arrays)
    {
        $clone = $this->mutate();
        $clone->Array = array_diff_key($clone->Array, ...$this->getArrays($arrays));
        return $clone;
    }

    /**
     * Keep entries whose values exist in all $arrays
     *
     * @param array<array-key,mixed>|Iterator<array-key,mixed>|FluentArray<array-key,mixed> ...$arrays
     * @return $this
     */
    public function intersect(...$arrays)
    {
        $clone = $this->mutate();
        $clone->Array = array_intersect($clone->Array, ...$this->getArrays($arrays));
        return $clone;
    }

    /**
     * Keep entries whose keys exist in all $arrays
     *
     * @param array<array-key,mixed>|Iterator<array-key,mixed>|FluentArray<array-key,mixed> ...$arrays
     * @return $this
     */
    public function intersectKey(...$arrays)
    {
        $clone = $this->mutate();
        $clone->Array = array_intersect_key($clone->Array, ...$this->getArrays($arrays));
        return $clone;
    }

    /**
     * @return array<TKey,TValue>
     */
    public function toArray(): array
    {
        return $this->Array;
    }

    /**
     * @template T0 of array-key
     * @template T1
     *
     * @param array<T0,T1>|Iterator<T0,T1>|FluentArray<T0,T1> $array
     * @return array<T0,T1>
     */
    private function getArray($array): array
    {
        if ($array instanceof FluentArray) {
            return $array->Array;
        }
        if ($array instanceof Iterator) {
            return iterator_to_array($array);
        }
        if (is_array($array)) {
            return $array;
        }

        // @phpstan-ignore-next-line
        throw new UnexpectedValueException(sprintf(
            'Argument #1 ($array) must be of type array|Iterator|%s, %s given',
            static::class,
            is_object($array) ? get_class($array) : gettype($array)
        ));
    }

    /**
     * @param array<array<array-key,mixed>|Iterator<array-key,mixed>|FluentArray<array-key,mixed>> $arrays
     * @return array<array<array-key,mixed>>
     */
    private function getArrays(array $arrays)
    {
        foreach ($arrays as &$array) {
            $array = $this->getArray($array);
        }

        return $arrays;
    }
}
