<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Lkrms\Concept\Utility;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use UnexpectedValueException;

/**
 * Work with PHP's Reflector classes
 *
 */
final class Reflect extends Utility
{
    /**
     * Return the names of the given Reflection objects
     *
     * @param array<int,\ReflectionClass|\ReflectionClassConstant|\ReflectionFunctionAbstract|\ReflectionParameter|\ReflectionProperty> $reflections
     * @return string[]
     */
    public static function getNames(array $reflections): array
    {
        return array_map(function ($r) { return $r->name; }, $reflections);
    }

    /**
     * Return the names of a class and its parents, up to and including $parent
     *
     * @param string|ReflectionClass $child
     * @param string|ReflectionClass $parent
     * @param bool $instantiable If set, only instantiable classes will be
     * included.
     * @return string[]
     */
    public static function getClassNamesBetween($child, $parent, bool $instantiable = false): array
    {
        $child  = self::toReflectionClass($child);
        $parent = self::toReflectionClass($parent);

        if (!is_a($child->name, $parent->name, true) || $parent->isInterface())
        {
            throw new RuntimeException("{$child->name} is not a subclass of {$parent->name}");
        }

        $names = [];

        do
        {
            if ($instantiable && !$child->isInstantiable())
            {
                continue;
            }

            $names[] = $child->name;
        }
        while ($child->name != $parent->name && $child = $child->getParentClass());

        return $names;
    }

    /**
     * Return all types included in the given ReflectionType
     *
     * Reflection methods that return a `ReflectionType` may actually return any
     * of the following:
     * - `ReflectionType` (had `isBuiltin()` until becoming `abstract` in PHP 8)
     * - `ReflectionNamedType` (PHP 7.1+)
     * - `ReflectionUnionType` (PHP 8+)
     * - `ReflectionIntersectionType` (PHP 8.1+)
     *
     * Depending on the PHP version, `getAllTypes` returns an array of
     * `ReflectionNamedType` and/or `ReflectionType` instances.
     *
     * @param null|ReflectionType $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @return ReflectionType[]
     * @see Reflect::getAllTypeNames()
     */
    public static function getAllTypes(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType ||
            $type instanceof ReflectionIntersectionType)
        {
            return $type->getTypes();
        }

        return is_null($type) ? [] : [$type];
    }

    /**
     * Return the names of all types included in the given ReflectionType
     *
     * @param null|ReflectionType $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @return string[]
     * @see Reflect::getAllTypes()
     */
    public static function getAllTypeNames(?ReflectionType $type): array
    {
        return array_map(fn(ReflectionType $t) => self::getTypeName($t),
            self::getAllTypes($type));
    }

    /**
     * Return the name of the given ReflectionNamedType or ReflectionType
     *
     * @param ReflectionType $type
     * @return string
     */
    public static function getTypeName(ReflectionType $type): string
    {
        return $type instanceof ReflectionNamedType ? $type->getName() : (string)$type;
    }

    /**
     * Convert the given ReflectionType to a PHP type declaration
     *
     * @param null|ReflectionType $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @param string $classPrefix
     * @param null|callable $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     * @return string
     */
    public static function getTypeDeclaration(
        ?ReflectionType $type,
        string $classPrefix          = "\\",
        ? callable $typeNameCallback = null
    ): string
    {
        $glue = "|";
        if ($type instanceof ReflectionUnionType)
        {
            $types = $type->getTypes();
        }
        elseif ($type instanceof ReflectionIntersectionType)
        {
            $glue  = "&";
            $types = $type->getTypes();
        }
        elseif (is_null($type))
        {
            $types = [];
        }
        else
        {
            $types = [$type];
        }
        $parts = [];
        /** @var ReflectionNamedType|ReflectionType $type */
        foreach ($types as $type)
        {
            $name    = self::getTypeName($type);
            $alias   = $typeNameCallback ? $typeNameCallback($name) : null;
            $parts[] = (($type->allowsNull() && strcasecmp($name, "null") ? "?" : "")
                . ($alias || $type->isBuiltin() ? "" : $classPrefix)
                . ($alias ?: $name));
        }
        return implode($glue, $parts);
    }

    /**
     * Convert the given ReflectionParameter to a PHP parameter declaration
     *
     * @param ReflectionParameter $parameter
     * @param string $classPrefix
     * @param null|callable $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     * @return string
     */
    public static function getParameterDeclaration(
        ReflectionParameter $parameter,
        string $classPrefix          = "\\",
        ? callable $typeNameCallback = null
    ): string
    {
        $param = self::getTypeDeclaration($parameter->getType(), $classPrefix, $typeNameCallback);
        $param = (($param ? "$param " : "mixed ")
            . ($parameter->isPassedByReference() ? "&" : "")
            . ($parameter->isVariadic() ? "..." : "")
            . '$' . $parameter->getName());
        if (!$parameter->isDefaultValueAvailable())
        {
            return $param;
        }
        $param .= " = ";
        if (!$parameter->isDefaultValueConstant())
        {
            $value = $parameter->getDefaultValue();
            $value = is_null($value) ? "null" : var_export($value, true);
            /** @todo Flatten arrays properly */
            if ($value == "array (\n)")
            {
                $value = "[]";
            }
            return $param . $value;
        }
        $const = $parameter->getDefaultValueConstantName();
        if (!preg_match('/^(self|parent|static)::/i', $const))
        {
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
    public static function getAllTraits(ReflectionClass $class): array
    {
        $allTraits = [];

        while ($class && !is_null($traits = $class->getTraits()))
        {
            $allTraits = array_merge($allTraits, $traits);
            $class     = $class->getParentClass();
        }

        if ($class)
        {
            throw new UnexpectedValueException("Error retrieving traits for class {$class->name}");
        }

        return $allTraits;
    }

    private static function toReflectionClass($class): ReflectionClass
    {
        return $class instanceof ReflectionClass ? $class : new ReflectionClass($class);
    }
}
