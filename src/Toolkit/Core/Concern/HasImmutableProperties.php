<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use ReflectionProperty;

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
        if ((
            isset($this->$property)
            || ($value === null && $this->propertyIsInitialized($property))
        ) && $value === $this->$property) {
            return $this;
        }

        $clone = $this->clone();
        $clone->$property = $value;
        return $clone;
    }

    /**
     * Remove a property from a clone of the object if it is currently set or
     * initialized
     *
     * @return static
     */
    protected function withoutProperty(string $property)
    {
        if (
            !isset($this->$property)
            && !$this->propertyIsInitialized($property)
        ) {
            return $this;
        }

        $clone = $this->clone();
        unset($clone->$property);
        return $clone;
    }

    private function propertyIsInitialized(string $property): bool
    {
        if (!property_exists($this, $property)) {
            return false;
        }

        $property = new ReflectionProperty($this, $property);
        $property->setAccessible(true);

        return $property->isInitialized($this);
    }
}
