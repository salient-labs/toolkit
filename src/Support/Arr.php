<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Contract\IImmutable;

/**
 * Wraps (some of) PHP's array functions in a fluent interface
 */
final class Arr implements IImmutable
{
    /**
     * @var array
     */
    private $Array;

    public function __construct(array $array)
    {
        $this->Array = $array;
    }

    public static function with(array $array): self
    {
        return new self($array);
    }

    /**
     * @return $this
     */
    public function map(? callable $callback, array ...$arrays)
    {
        return (clone $this)->_map($callback, ...$arrays);
    }

    /**
     * @return $this
     */
    public function filter(? callable $callback, int $mode = 0)
    {
        return (clone $this)->_filter($callback, $mode);
    }

    /**
     * @return $this
     */
    public function merge(array ...$arrays)
    {
        return (clone $this)->_merge(...$arrays);
    }

    /**
     * Keep entries whose values are absent from all $arrays, preserving keys
     *
     * @return $this
     */
    public function diff(array ...$arrays)
    {
        return (clone $this)->_diff(...$arrays);
    }

    /**
     * Keep entries whose keys are absent from all $arrays
     *
     * @return $this
     */
    public function diffKey(array ...$arrays)
    {
        return (clone $this)->_diff_key(...$arrays);
    }

    /**
     * Keep entries whose values exist in all $arrays
     *
     * @return $this
     */
    public function intersect(array ...$arrays)
    {
        return (clone $this)->_intersect(...$arrays);
    }

    /**
     * Keep entries whose keys exist in all $arrays
     *
     * @return $this
     */
    public function intersectKey(array ...$arrays)
    {
        return (clone $this)->_intersect_key(...$arrays);
    }

    public function toArray(): array
    {
        return $this->Array;
    }

    /**
     * @return $this
     */
    private function _map(? callable $callback, array ...$arrays)
    {
        $this->Array = array_map($callback, $this->Array, ...$arrays);

        return $this;
    }

    /**
     * @return $this
     */
    private function _filter(? callable $callback, int $mode)
    {
        $this->Array = array_filter($this->Array, $callback, $mode);

        return $this;
    }

    /**
     * @return $this
     */
    private function _merge(array ...$arrays)
    {
        $this->Array = array_merge($this->Array, ...$arrays);

        return $this;
    }

    /**
     * @return $this
     */
    private function _diff(array ...$arrays)
    {
        $this->Array = array_diff($this->Array, ...$arrays);

        return $this;
    }

    /**
     * @return $this
     */
    private function _diff_key(array ...$arrays)
    {
        $this->Array = array_diff_key($this->Array, ...$arrays);

        return $this;
    }

    /**
     * @return $this
     */
    private function _intersect(array ...$arrays)
    {
        $this->Array = array_intersect($this->Array, ...$arrays);

        return $this;
    }

    /**
     * @return $this
     */
    private function _intersect_key(array ...$arrays)
    {
        $this->Array = array_intersect_key($this->Array, ...$arrays);

        return $this;
    }

}
