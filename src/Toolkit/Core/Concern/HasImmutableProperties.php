<?php declare(strict_types=1);

namespace Salient\Core\Concern;

trait HasImmutableProperties
{
    /**
     * Clone the object
     *
     * @return static
     */
    protected function clone()
    {
        return clone $this;
    }

    /**
     * Apply a value to a clone of the object if the current value differs
     *
     * @param mixed $value
     * @return static
     */
    protected function withPropertyValue(string $property, $value)
    {
        if (isset($this->$property) && $value === $this->$property) {
            return $this;
        }

        $clone = $this->clone();
        $clone->$property = $value;
        return $clone;
    }
}
