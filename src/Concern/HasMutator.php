<?php declare(strict_types=1);

namespace Lkrms\Concern;

use ArrayAccess;
use LogicException;

/**
 * Creates clones of itself with a "mutant" flag applied
 *
 */
trait HasMutator
{
    private bool $_IsMutant = false;

    /**
     * Get a clone of the object and mark it as a mutant
     *
     * @return static
     */
    final protected function mutate()
    {
        $clone = clone $this;
        $clone->_IsMutant = true;
        return $clone;
    }

    /**
     * If the object is marked as a mutant, get a clone with the "mutant" flag
     * cleared
     *
     * @return static|$this
     */
    final protected function asNew()
    {
        if (!$this->_IsMutant) {
            return $this;
        }

        $clone = clone $this;
        $clone->_IsMutant = false;
        return $clone;
    }

    /**
     * True if the object is marked as a mutant
     *
     */
    final protected function isMutant(): bool
    {
        return $this->_IsMutant;
    }

    /**
     * Get a clone of the object, mark it as a mutant, and apply a value to one
     * of its properties
     *
     * If `$key` is specified, the outcome depends on the value of
     * `$this->{$property}`:
     * - unset or array: `$value` is assigned to `$this->{$property}[$key]`
     * - object: `$this->{$property}` is replaced with a clone of itself, then
     *   if it implements `ArrayAccess`, `$value` is assigned to
     *   `$this->{$property}[$key]`, otherwise `$value` is assigned to
     *   `$this->{$property}->{$key}`
     * - other: `LogicException` is thrown
     *
     * In all cases, if `$value` is identical to the value it's replacing, the
     * object is returned as-is.
     *
     * @param mixed $value
     * @return static|$this
     */
    final protected function withPropertyValue(string $property, $value, ?string $key = null)
    {
        if ($key) {
            if (!isset($this->$property) || is_array($this->$property)) {
                return $this->applyPropertyKeyValue($property, $value, $key);
            } elseif ($this->$property instanceof ArrayAccess) {
                return $this->applyPropertyKeyValue($property, $value, $key, true);
            } elseif (is_object($this->$property)) {
                return $this->applyPropertyKeyValue($property, $value, $key, true, false);
            } else {
                throw new LogicException(
                    sprintf('%s::$%s is not an array or object', static::class, $property)
                );
            }
        }

        if (isset($this->$property) && $value === $this->$property) {
            return $this;
        }

        $clone = $this->mutate();
        $clone->$property = $value;
        return $clone;
    }

    /**
     * @param mixed $value
     * @return static|$this
     */
    private function applyPropertyKeyValue(
        string $property,
        $value,
        string $key,
        bool $clone = false,
        bool $array = true
    ) {
        if (($array && isset($this->$property[$key]) &&
                $value === $this->$property[$key]) ||
            (!$array && isset($this->$property->$key) &&
                $value === $this->$property->$key)) {
            return $this;
        }

        $_value = $clone
            ? clone $this->$property
            : ($this->$property ?? null);

        if ($array) {
            $_value[$key] = $value;
        } else {
            $_value->$key = $value;
        }

        $clone = $this->mutate();
        $clone->$property = $_value;
        return $clone;
    }
}
