<?php declare(strict_types=1);

namespace Salient\PHPStan\Core\Rules;

use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use Salient\Core\Concern\ImmutableTrait;

/**
 * @codeCoverageIgnore
 */
class ImmutableTraitReadWritePropertiesExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return $this->classHasMutator($property);
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return $this->classHasMutator($property);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return $this->classHasMutator($property);
    }

    public function classHasMutator(PropertyReflection $property): bool
    {
        $classReflection = $property->getDeclaringClass();
        foreach ($classReflection->getTraits(true) as $traitReflection) {
            if ($traitReflection->getName() === ImmutableTrait::class) {
                return true;
            }
        }
        return false;
    }
}
