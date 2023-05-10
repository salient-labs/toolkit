<?php declare(strict_types=1);

namespace Lkrms\Concern;

use ArrayAccess;
use LogicException;

/**
 * Returns an updated clone of itself
 *
 */
trait HasMutator
{
    use IsMutable;

    /**
     * @param mixed $value
     * @return $this
     */
    final protected function withPropertyValue(string $property, $value, ?string $key = null)
    {
        if ($key) {
            if (!isset($this->$property) || is_array($this->$property)) {
                return $this->applyPropertyKeyValue(
                    __FUNCTION__, $property, $value, $key
                );
            } elseif ($this->$property instanceof ArrayAccess) {
                return $this->applyPropertyKeyValue(
                    __FUNCTION__, $property, $value, $key, true
                );
            } elseif (is_object($this->$property)) {
                return $this->applyPropertyKeyValue(
                    __FUNCTION__, $property, $value, $key, true, false
                );
            } else {
                throw new LogicException(
                    sprintf('%s::$%s is not an array or object', static::class, $property)
                );
            }
        }

        if (isset($this->$property) && $value === $this->$property) {
            return $this;
        }
        $clone = clone $this;
        $clone->$property = $value;

        return $clone->mutate(__FUNCTION__, static::class);
    }

    /**
     * @param mixed $value
     * @return $this
     */
    private function applyPropertyKeyValue(
        string $mutator,
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
        $clone = clone $this;
        $clone->$property = $_value;

        return $clone->mutate($mutator, static::class);
    }
}
