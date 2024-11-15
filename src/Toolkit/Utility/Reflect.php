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
    /** @var array<class-string,array<string,mixed>> */
    private static array $Constants = [];
    /** @var array<class-string,array<int|string,string[]|string>> */
    private static array $ConstantsByValue = [];

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
     * Get the prototype of a method, or null if it has no prototype
     */
    public static function getPrototype(ReflectionMethod $method): ?ReflectionMethod
    {
        try {
            return $method->getPrototype();
        } catch (ReflectionException $ex) {
            if (\PHP_VERSION_ID >= 80200 || $method->isPrivate()) {
                return null;
            }
            // Work around issue where PHP does not return a prototype for
            // methods inserted from traits
            $class = $method->getDeclaringClass();
            $name = $method->getName();
            if ($method->isPublic()) {
                foreach ($class->getInterfaces() as $interface) {
                    if ($interface->hasMethod($name)) {
                        return $interface->getMethod($name);
                    }
                }
            }
            $class = $class->getParentClass();
            if ($class && $class->hasMethod($name)) {
                return $class->getMethod($name);
            }
            return null;
        }
    }

    /**
     * Get the declaring class of a method's prototype, falling back to the
     * method's declaring class if it has no prototype
     *
     * @return ReflectionClass<object>
     */
    public static function getPrototypeClass(ReflectionMethod $method): ReflectionClass
    {
        return (self::getPrototype($method) ?? $method)->getDeclaringClass();
    }

    /**
     * Get the trait method inserted into a class with the given name
     *
     * @param ReflectionClass<object> $class
     */
    public static function getTraitMethod(
        ReflectionClass $class,
        string $methodName
    ): ?ReflectionMethod {
        if ($inserted = self::getTraitAliases($class)[$methodName] ?? null) {
            return new ReflectionMethod(...$inserted);
        }

        foreach ($class->getTraits() as $trait) {
            if ($trait->hasMethod($methodName)) {
                return $trait->getMethod($methodName);
            }
        }

        return null;
    }

    /**
     * Get the trait method aliases of a class as an array that maps aliases to
     * [ trait, method ] arrays
     *
     * @param ReflectionClass<object> $class
     * @return array<string,array{class-string,string}>
     */
    public static function getTraitAliases(ReflectionClass $class): array
    {
        foreach ($class->getTraitAliases() as $alias => $original) {
            /** @var array{class-string,string} */
            $original = explode('::', $original, 2);
            $aliases[$alias] = $original;
        }

        return $aliases ?? [];
    }

    /**
     * Get the trait property inserted into a class with the given name
     *
     * @param ReflectionClass<object> $class
     */
    public static function getTraitProperty(
        ReflectionClass $class,
        string $propertyName
    ): ?ReflectionProperty {
        foreach ($class->getTraits() as $trait) {
            if ($trait->hasProperty($propertyName)) {
                return $trait->getProperty($propertyName);
            }
        }

        return null;
    }

    /**
     * Get the trait constant inserted into a class with the given name
     *
     * @param ReflectionClass<object> $class
     */
    public static function getTraitConstant(
        ReflectionClass $class,
        string $constantName
    ): ?ReflectionClassConstant {
        if (\PHP_VERSION_ID < 80200) {
            return null;
        }

        foreach ($class->getTraits() as $trait) {
            if (
                $trait->hasConstant($constantName)
                && ($constant = $trait->getReflectionConstant($constantName))
            ) {
                return $constant;
            }
        }

        return null;
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

    /**
     * Get the public constants of a class or interface, indexed by name
     *
     * @param ReflectionClass<object>|class-string $class
     * @return array<string,mixed>
     */
    public static function getConstants($class): array
    {
        return self::$Constants[self::getClassName($class)] ??=
            self::doGetConstants($class);
    }

    /**
     * @param ReflectionClass<object>|class-string $class
     * @return array<string,mixed>
     */
    private static function doGetConstants($class): array
    {
        $class = self::getClass($class);
        foreach ($class->getReflectionConstants() as $constant) {
            if ($constant->isPublic()) {
                $constants[$constant->getName()] = $constant->getValue();
            }
        }

        return $constants ?? [];
    }

    /**
     * Get the public constants of a class or interface, indexed by value
     *
     * If the value of a constant is not an integer or string, it is ignored.
     * For any values used by multiple constants, an array is returned.
     *
     * @param ReflectionClass<object>|class-string $class
     * @return array<int|string,string[]|string>
     */
    public static function getConstantsByValue($class): array
    {
        return self::$ConstantsByValue[self::getClassName($class)] ??=
            self::doGetConstantsByValue($class);
    }

    /**
     * @param ReflectionClass<object>|class-string $class
     * @return array<int|string,string[]|string>
     */
    private static function doGetConstantsByValue($class): array
    {
        foreach (self::getConstants($class) as $name => $value) {
            if (!is_int($value) && !is_string($value)) {
                continue;
            }
            if (!isset($constants[$value])) {
                $constants[$value] = $name;
                continue;
            }
            if (!is_array($constants[$value])) {
                $constants[$value] = (array) $constants[$value];
            }
            $constants[$value][] = $name;
        }

        return $constants ?? [];
    }

    /**
     * Check if a class or interface has a public constant with the given value
     *
     * @param ReflectionClass<object>|class-string $class
     * @param mixed $value
     */
    public static function hasConstantWithValue($class, $value): bool
    {
        return in_array($value, self::getConstants($class), true);
    }

    /**
     * Get the name of a public constant with the given value from a class or
     * interface
     *
     * @param ReflectionClass<object>|class-string $class
     * @param mixed $value
     * @throws InvalidArgumentException if `$value` is invalid or matches
     * multiple constants.
     */
    public static function getConstantName($class, $value): string
    {
        foreach (self::getConstants($class) as $name => $_value) {
            if ($_value === $value) {
                $names[] = $name;
            }
        }

        if (!isset($names)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value: %s',
                Format::value($value),
            ));
        }

        if (count($names) > 1) {
            throw new InvalidArgumentException(sprintf(
                'Value matches multiple constants: %s',
                Format::value($value),
            ));
        }

        return $names[0];
    }

    /**
     * Get the value of a public constant with the given name from a class or
     * interface
     *
     * @param ReflectionClass<object>|class-string $class
     * @return mixed
     * @throws InvalidArgumentException if `$name` is invalid.
     */
    public static function getConstantValue($class, string $name, bool $ignoreCase = false)
    {
        $constants = self::getConstants($class);
        if (array_key_exists($name, $constants)) {
            return $constants[$name];
        }

        if ($ignoreCase) {
            $constants = array_change_key_case($constants, \CASE_UPPER);
            if (array_key_exists($upper = Str::upper($name), $constants)) {
                return $constants[$upper];
            }
        }

        throw new InvalidArgumentException(sprintf('Invalid name: %s', $name));
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<T>|class-string<T> $class
     * @return ReflectionClass<T>
     */
    private static function getClass($class): ReflectionClass
    {
        if ($class instanceof ReflectionClass) {
            return $class;
        }
        return new ReflectionClass($class);
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<T>|class-string<T> $class
     * @return class-string<T>
     */
    private static function getClassName($class): string
    {
        if ($class instanceof ReflectionClass) {
            return $class->getName();
        }
        return $class;
    }
}
