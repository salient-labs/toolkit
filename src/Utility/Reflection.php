<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Convert;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use UnexpectedValueException;

/**
 * Work with PHP's Reflector classes
 *
 */
final class Reflection
{
    /**
     * Return the names of the given Reflection objects
     *
     * @param array<int,\ReflectionClass|\ReflectionClassConstant|\ReflectionFunctionAbstract|\ReflectionParameter|\ReflectionProperty> $reflections
     * @return string[]
     */
    public function getNames(array $reflections): array
    {
        return array_map(function ($r) {return $r->name;}, $reflections);
    }

    /**
     * Return the names of a class and its parents, up to and optionally
     * including $parent
     *
     * @param string|ReflectionClass $child
     * @param string|ReflectionClass $parent
     * @param bool $includeParent If `true`, include `$parent` in the returned
     * array.
     * @return string[]
     */
    public function getClassNamesBetween($child, $parent, bool $includeParent = true): array
    {
        $child  = $this->toReflectionClass($child);
        $parent = $this->toReflectionClass($parent);

        if (!is_a($child->name, $parent->name, true) || $parent->isInterface()) {
            throw new UnexpectedValueException("{$child->name} is not a subclass of {$parent->name}");
        }

        $names = [];
        do {
            if ($child == $parent && !$includeParent) {
                break;
            }

            $names[] = $child->name;
        } while ($child != $parent && $child = $child->getParentClass());

        return $names;
    }

    /**
     * Follow ReflectionClass->getParentClass() until an ancestor with no parent
     * is found
     *
     */
    public function getBaseClass(ReflectionClass $class): ReflectionClass
    {
        while ($parent = $class->getParentClass()) {
            $class = $parent;
        }

        return $class;
    }

    /**
     * If a method has a prototype, return its declaring class, otherwise return
     * the method's declaring class
     *
     */
    public function getMethodPrototypeClass(ReflectionMethod $method): ReflectionClass
    {
        try {
            return $method->getPrototype()->getDeclaringClass();
        } catch (ReflectionException $ex) {
            return $method->getDeclaringClass();
        }
    }

    /**
     * Return all types included in the given ReflectionType
     *
     * Reflection methods that return a `ReflectionType` may actually return any
     * of the following:
     * - `ReflectionType` (became `abstract` in PHP 8)
     * - `ReflectionNamedType` (PHP 7.1+)
     * - `ReflectionUnionType` (PHP 8+)
     * - `ReflectionIntersectionType` (PHP 8.1+)
     *
     * Depending on the PHP version, `getAllTypes` returns an array of
     * `ReflectionNamedType` and/or `ReflectionType` instances.
     *
     * @param ReflectionType|null $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @return ReflectionNamedType[]|ReflectionType[]
     * @see Reflection::getAllTypeNames()
     */
    public function getAllTypes(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType ||
                $type instanceof ReflectionIntersectionType) {
            return $type->getTypes();
        }

        return is_null($type) ? [] : [$type];
    }

    /**
     * Return the names of all types included in the given ReflectionType
     *
     * @param ReflectionType|null $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @return string[]
     * @see Reflection::getAllTypes()
     */
    public function getAllTypeNames(?ReflectionType $type): array
    {
        return array_map(fn(ReflectionType $t) => $this->getTypeName($t),
            $this->getAllTypes($type));
    }

    /**
     * Return the name of the given ReflectionNamedType or ReflectionType
     *
     * @param ReflectionType $type
     * @return string
     */
    public function getTypeName(ReflectionType $type): string
    {
        return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    }

    /**
     * Get an array of doc comments for a ReflectionMethod from its declaring
     * class and any ancestors that declare the same method
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes, traits or interfaces.
     *
     * @return string[]
     */
    public function getAllMethodDocComments(ReflectionMethod $method): array
    {
        $name       = $method->getName();
        $interfaces = $method->getDeclaringClass()->getInterfaces();
        $comments   = [];
        do {
            if (($comment = $method->getDocComment()) !== false) {
                $comments[] = $comment;
            }
            if ($method->getDeclaringClass()->isInterface()) {
                continue;
            }
            foreach ($method->getDeclaringClass()->getTraits() as $trait) {
                if ($trait->hasMethod($name)) {
                    array_push($comments, ...$this->getAllMethodDocComments($trait->getMethod($name)));
                }
            }
        } while (($parent = $method->getDeclaringClass()->getParentClass()) &&
            $parent->hasMethod($name) &&
            ($method = $parent->getMethod($name)));

        // Group by base interface, then sort children before parents
        usort($interfaces,
            fn(ReflectionClass $a, ReflectionClass $b) => (
                $a->isSubclassOf($b) ? -1 : (
                    $b->isSubclassOf($a) ? 1
                        : $this->getBaseClass($a)->getName() <=> $this->getBaseClass($b)->getName()
                )
            ));

        foreach ($interfaces as $interface) {
            if ($interface->hasMethod($name)) {
                array_push($comments, ...$this->getAllMethodDocComments($interface->getMethod($name)));
            }
        }

        return Convert::stringsToUniqueList($comments);
    }

    /**
     * Get an array of doc comments for a ReflectionProperty from its declaring
     * class and any ancestors that declare the same property
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes or traits.
     *
     * @return string[]
     */
    public function getAllPropertyDocComments(ReflectionProperty $property): array
    {
        $name     = $property->getName();
        $comments = [];
        do {
            if (($comment = $property->getDocComment()) !== false) {
                $comments[] = $comment;
            }
            foreach ($property->getDeclaringClass()->getTraits() as $trait) {
                if ($trait->hasProperty($name)) {
                    array_push($comments, ...$this->getAllPropertyDocComments($trait->getProperty($name)));
                }
            }
        } while (($parent = $property->getDeclaringClass()->getParentClass()) &&
            $parent->hasProperty($name) &&
            ($property = $parent->getProperty($name)));

        return Convert::stringsToUniqueList($comments);
    }

    /**
     * Convert the given ReflectionType to a PHP type declaration
     *
     * @param ReflectionType|null $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @param string $classPrefix
     * @param callable|null $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     * @return string
     */
    public function getTypeDeclaration(?ReflectionType $type, string $classPrefix = '\\', ?callable $typeNameCallback = null): string
    {
        $glue = '|';
        if ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();
        } elseif ($type instanceof ReflectionIntersectionType) {
            $glue  = '&';
            $types = $type->getTypes();
        } elseif (is_null($type)) {
            $types = [];
        } else {
            $types = [$type];
        }
        $parts = [];
        /** @var ReflectionNamedType|ReflectionType $type */
        foreach ($types as $type) {
            $name    = $this->getTypeName($type);
            $alias   = $typeNameCallback ? $typeNameCallback($name) : null;
            $parts[] = ($type->allowsNull() && strcasecmp($name, 'null') ? '?' : '')
                . ($alias || $type->isBuiltin() ? '' : $classPrefix)
                . ($alias ?: $name);
        }

        return implode($glue, $parts);
    }

    /**
     * Convert the given ReflectionParameter to a PHP parameter declaration
     *
     * @param ReflectionParameter $parameter
     * @param string $classPrefix
     * @param callable|null $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     * @param string|null $type If set, ignore the parameter's declared type and
     * use `$type` instead. Do not use when generating code unless `$type` is
     * from a trusted source.
     * @return string
     */
    public function getParameterDeclaration(ReflectionParameter $parameter, string $classPrefix = '\\', ?callable $typeNameCallback = null, string $type = null): string
    {
        // If getTypeDeclaration isn't called, neither is $typeNameCallback
        $param  = $this->getTypeDeclaration($parameter->getType(), $classPrefix, $typeNameCallback);
        $param  = is_null($type) ? ($param ?: 'mixed') : $type;
        $param .= ($param ? ' ' : '')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . $parameter->getName();
        if (!$parameter->isDefaultValueAvailable()) {
            return $param;
        }
        $param .= ' = ';
        if (!$parameter->isDefaultValueConstant()) {
            return $param . Convert::valueToCode($parameter->getDefaultValue(), ',', '=>');
        }
        $const = $parameter->getDefaultValueConstantName();
        if (!preg_match('/^(self|parent|static)::/i', $const)) {
            if ($typeNameCallback &&
                ($_const = preg_replace_callback(
                    '/^[^:\\\\]+(?:\\\\[^:\\\\]+)+(?=::)/',
                    fn($matches) => $typeNameCallback($matches[0]) ?: $matches[0],
                    $const
                )) !== $const) {
                return "$param$_const";
            }

            return "$param$classPrefix$const";
        }

        return "$param$const";
    }

    /**
     * Return an array of traits used by this class and its parent classes
     *
     * In other words, merge arrays returned by `ReflectionClass::getTraits()`
     * for `$class`, `$class->getParentClass()`, etc.
     *
     * @param ReflectionClass $class
     * @return array<string,ReflectionClass> An array that maps trait names to
     * `ReflectionClass` instances.
     */
    public function getAllTraits(ReflectionClass $class): array
    {
        $allTraits = [];

        while ($class && !is_null($traits = $class->getTraits())) {
            $allTraits = array_merge($allTraits, $traits);
            $class     = $class->getParentClass();
        }

        if ($class) {
            throw new UnexpectedValueException("Error retrieving traits for class {$class->name}");
        }

        return $allTraits;
    }

    private function toReflectionClass($class): ReflectionClass
    {
        return $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
    }
}
