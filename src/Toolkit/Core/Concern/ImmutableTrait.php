<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Immutable;
use ReflectionProperty;

/**
 * @api
 *
 * @phpstan-require-implements Immutable
 */
trait ImmutableTrait
{
    /**
     * Get a copy of the object with a value assigned to a property if its
     * current value differs, otherwise return the object
     *
     * @param mixed $value
     * @return static
     */
    private function with(string $property, $value)
    {
        if ((
            isset($this->$property)
            || ($value === null && $this->propertyIsInitialized($property))
        ) && $value === $this->$property) {
            return $this;
        }

        $clone = clone $this;
        $clone->$property = $value;
        $clone->handlePropertyChanged($property);
        return $clone;
    }

    /**
     * Get a copy of the object where a property is unset if it is currently
     * set, otherwise return the object
     *
     * @return static
     */
    private function without(string $property)
    {
        if (
            !isset($this->$property)
            && !$this->propertyIsInitialized($property)
        ) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->$property);
        $clone->handlePropertyChanged($property);
        return $clone;
    }

    /**
     * Called after a property of the object is changed or unset via one of its
     * mutator methods
     */
    private function handlePropertyChanged(string $property): void {}

    /**
     * @internal
     */
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
