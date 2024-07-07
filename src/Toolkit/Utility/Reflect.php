<?php declare(strict_types=1);

namespace Salient\Utility;

use Salient\Utility\Internal\NamedType;
use Closure;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionExtension;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use ReflectionZendExtension;

/**
 * Work with PHP's reflection API
 *
 * @api
 */
final class Reflect extends AbstractUtility
{
    /**
     * Get a list of names from a list of reflectors
     *
     * @param array<ReflectionAttribute<object>|ReflectionClass<object>|ReflectionClassConstant|ReflectionExtension|ReflectionFunctionAbstract|ReflectionNamedType|ReflectionParameter|ReflectionProperty|ReflectionZendExtension> $reflectors
     * @return string[]
     */
    public static function getNames(array $reflectors): array
    {
        foreach ($reflectors as $reflector) {
            $names[] = $reflector->getName();
        }
        return $names ?? [];
    }

    /**
     * Get a list of types accepted by the given parameter of a function or
     * callable
     *
     * @param ReflectionFunctionAbstract|callable $function
     * @return ($skipBuiltins is true ? array<class-string[]|class-string> : array<string[]|string>)
     * @throws InvalidArgumentException if `$function` has no parameter at the
     * given position.
     */
    public static function getAcceptedTypes(
        $function,
        bool $skipBuiltins = false,
        int $param = 0
    ): array {
        if (!$function instanceof ReflectionFunctionAbstract) {
            if (!$function instanceof Closure) {
                $function = Closure::fromCallable($function);
            }
            $function = new ReflectionFunction($function);
        }

        $params = $function->getParameters();
        if (!isset($params[$param])) {
            throw new InvalidArgumentException(sprintf(
                '$function has no parameter at position %d',
                $param,
            ));
        }

        $types = self::normaliseType($params[$param]->getType());
        foreach ($types as $type) {
            $intersection = [];
            foreach (Arr::wrap($type) as $type) {
                if ($skipBuiltins && $type->isBuiltin()) {
                    continue 2;
                }
                $intersection[] = $type->getName();
            }
            $union[] = Arr::unwrap($intersection);
        }

        /** @var array<class-string[]|class-string> */
        return $union ?? [];
    }

    /**
     * Follow parents of a class to the root class
     *
     * @param ReflectionClass<object> $class
     * @return ReflectionClass<object>
     */
    public static function getBaseClass(ReflectionClass $class): ReflectionClass
    {
        while ($parent = $class->getParentClass()) {
            $class = $parent;
        }
        return $class;
    }

    /**
     * Get the declaring class of a method's prototype, falling back to the
     * method's declaring class if it has no prototype
     *
     * @return ReflectionClass<object>
     */
    public static function getPrototypeClass(ReflectionMethod $method): ReflectionClass
    {
        try {
            return $method->getPrototype()->getDeclaringClass();
        } catch (ReflectionException $ex) {
            return $method->getDeclaringClass();
        }
    }

    /**
     * Get the properties of a class, including private parent properties
     *
     * @param ReflectionClass<object> $class
     * @return ReflectionProperty[]
     */
    public static function getAllProperties(ReflectionClass $class): array
    {
        do {
            foreach ($class->getProperties() as $property) {
                $name = $property->getName();
                if (isset($seen[$name])) {
                    continue;
                }
                $properties[] = $property;
                $seen[$name] = true;
            }
        } while ($class = $class->getParentClass());

        return $properties ?? [];
    }

    /**
     * Resolve a ReflectionType to an array of ReflectionNamedType instances
     *
     * PHP reflection methods like {@see ReflectionParameter::getType()} and
     * {@see ReflectionFunctionAbstract::getReturnType()} can return any of the
     * following:
     *
     * - {@see ReflectionType} (until PHP 8)
     * - {@see ReflectionNamedType}
     * - {@see ReflectionUnionType} comprised of {@see ReflectionNamedType} (PHP
     *   8+) and {@see ReflectionIntersectionType} (PHP 8.2+)
     * - {@see ReflectionIntersectionType} comprised of
     *   {@see ReflectionNamedType} (PHP 8.1+)
     * - `null`
     *
     * This method normalises these to an array that represents an equivalent
     * union type, where each element is either:
     *
     * - a {@see ReflectionNamedType} instance, or
     * - a list of {@see ReflectionNamedType} instances that represent an
     *   intersection type
     *
     * @return array<ReflectionNamedType[]|ReflectionNamedType>
     */
    public static function normaliseType(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }

        return self::doNormaliseType($type);
    }

    /**
     * Get the types in a ReflectionType
     *
     * @return ReflectionNamedType[]
     */
    public static function getTypes(?ReflectionType $type): array
    {
        return self::doGetTypes($type, false);
    }

    /**
     * Get the name of each type in a ReflectionType
     *
     * @return string[]
     */
    public static function getTypeNames(?ReflectionType $type): array
    {
        return self::doGetTypes($type, true);
    }

    /**
     * @return ($names is true ? string[] : ReflectionNamedType[])
     */
    private static function doGetTypes(?ReflectionType $type, bool $names): array
    {
        if ($type === null) {
            return [];
        }

        foreach (Arr::flatten(self::doNormaliseType($type)) as $type) {
            /** @var ReflectionNamedType $type */
            $name = $type->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $types[] = $names ? $name : $type;
            $seen[$name] = true;
        }

        return $types ?? [];
    }

    /**
     * @return array<ReflectionNamedType[]|ReflectionNamedType>
     */
    private static function doNormaliseType(ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $type) {
                if ($type instanceof ReflectionIntersectionType) {
                    $types[] = $type->getTypes();
                    continue;
                }
                $types[] = $type;
            }
            /** @var array<ReflectionNamedType[]|ReflectionNamedType> */
            return $types ?? [];
        }

        if ($type instanceof ReflectionIntersectionType) {
            $types = [$type->getTypes()];
            /** @var array<ReflectionNamedType[]> */
            return $types;
        }

        /** @var ReflectionNamedType $type */
        return self::expandNullableType($type);
    }

    /**
     * @param ReflectionNamedType $type
     * @return array<ReflectionNamedType>
     */
    private static function expandNullableType(ReflectionType $type): array
    {
        if ($type->allowsNull() && (
            !$type->isBuiltin()
            || strcasecmp($type->getName(), 'null')
        )) {
            return [
                new NamedType($type->getName(), $type->isBuiltin(), false),
                new NamedType('null', true, true),
            ];
        }

        return [$type];
    }
}
