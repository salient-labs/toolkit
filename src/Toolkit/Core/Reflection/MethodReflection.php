<?php declare(strict_types=1);

namespace Salient\Core\Reflection;

use Salient\Utility\Reflect;
use Closure;
use DateTimeInterface;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * @api
 */
class MethodReflection extends ReflectionMethod
{
    /** @var class-string */
    private string $ClassUnderReflection;

    /**
     * @api
     *
     * @param object|class-string $objectOrClass
     */
    public function __construct($objectOrClass, string $method)
    {
        $this->ClassUnderReflection = is_object($objectOrClass)
            ? get_class($objectOrClass)
            : $objectOrClass;

        parent::__construct($objectOrClass, $method);
    }

    /**
     * Check if a parameter has a type hint and accepts values of a given type
     *
     * Limitations:
     * - Intersection types are ignored
     * - Relative class types are not resolved
     *
     * @param int<0,max> $position
     */
    public function accepts(string $typeName, bool $isBuiltin = false, int $position = 0): bool
    {
        $types = Reflect::normaliseType($this->getParameter($position)->getType());
        foreach ($types as $type) {
            if (
                !is_array($type)
                && $type->isBuiltin() === $isBuiltin
                && (
                    !strcasecmp($name = $type->getName(), $typeName)
                    || (!$isBuiltin && is_a($typeName, $name, true))
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the method has a type hint and returns a given type
     *
     * Limitations:
     * - Always returns `false` for intersection types
     * - Relative class types are not resolved
     */
    public function returns(string $typeName, bool $isBuiltin = false, bool $allowNull = true): bool
    {
        $types = Reflect::normaliseType($this->getReturnType());
        if (!$types) {
            return false;
        }
        foreach ($types as $type) {
            if (is_array($type) || !(
                (
                    ($builtin = $type->isBuiltin()) === $isBuiltin
                    && (
                        !strcasecmp($name = $type->getName(), $typeName)
                        || (!$isBuiltin && is_a($name, $typeName, true))
                    )
                ) || (
                    $allowNull
                    && $builtin
                    && !strcasecmp($type->getName(), 'null')
                )
            )) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get the parameter at the given position
     *
     * @param int<0,max> $position
     * @throws ReflectionException if there is no parameter at `$position`.
     */
    public function getParameter(int $position): ReflectionParameter
    {
        if ($position >= $this->getNumberOfParameters()) {
            throw new ReflectionException(sprintf(
                'Method %s does not have a parameter at position %d',
                $this->name,
                $position,
            ));
        }
        return $this->getParameters()[$position];
    }

    /**
     * Get a parameter index for the method
     *
     * @param (Closure(string $name, bool $fromData=): string)|null $normaliser
     */
    public function getParameterIndex(?Closure $normaliser = null): ParameterIndex
    {
        $class = new ClassReflection($this->ClassUnderReflection);
        $normaliser ??= $class->getNormaliser();

        foreach ($this->getParameters() as $param) {
            $name = $normaliser
                ? $normaliser($param->name, false)
                : $param->name;
            $type = $param->getType();
            $isOptional = $param->isOptional();

            $names[$name] = $param->name;

            if (!$param->isVariadic()) {
                $defaultArgs[] = $isOptional && $param->isDefaultValueAvailable()
                    ? $param->getDefaultValue()
                    : null;
            }

            if (!$param->allowsNull()) {
                $notNullable[$name] = $param->name;
                if (!$isOptional) {
                    $required[$name] = $param->name;
                }
            }

            if ($param->isPassedByReference()) {
                $byRef[$name] = $param->name;
            }

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                if ($type->isBuiltin()) {
                    $builtins[$name] = $typeName;
                } else {
                    /** @var class-string $typeName */
                    $services[$name] = $typeName;
                    if (!strcasecmp($typeName, DateTimeInterface::class)) {
                        $date[$name] = $param->name;
                    }
                }
            }
        }

        return new ParameterIndex(
            $names ?? [],
            array_flip(array_values($names ?? [])),
            $defaultArgs ?? [],
            $notNullable ?? [],
            $required ?? [],
            $byRef ?? [],
            $date ?? [],
            $builtins ?? [],
            $services ?? [],
            $this->getNumberOfRequiredParameters(),
        );
    }
}
