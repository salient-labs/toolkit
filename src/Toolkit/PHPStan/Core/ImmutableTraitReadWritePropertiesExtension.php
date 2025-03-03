<?php declare(strict_types=1);

namespace Salient\PHPStan\Core;

use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Reflect;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class ImmutableTraitReadWritePropertiesExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return $this->propertyIsMutable($property);
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return $this->propertyIsMutable($property);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return $this->propertyIsMutable($property);
    }

    private function propertyIsMutable(PropertyReflection $property): bool
    {
        if (!$property->isStatic()) {
            $traits = Reflect::getAllTraits(
                $property->getDeclaringClass()->getNativeReflection(),
                !$property->isPrivate(),
            );
            foreach (array_keys($traits) as $trait) {
                if ($trait === ImmutableTrait::class) {
                    return true;
                }
            }
        }
        return false;
    }
}
