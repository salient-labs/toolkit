<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Lkrms\Concept\Utility;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
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
     * Return all types represented by the given ReflectionType
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
        if ($type instanceof ReflectionUnionType)
        {
            return $type->getTypes();
        }
        elseif ($type instanceof ReflectionIntersectionType)
        {
            return [];
        }

        return is_null($type) ? [] : [$type];
    }

    /**
     * Return the names of all types represented by the given ReflectionType
     *
     * @param null|ReflectionType $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @return string[]
     * @see Reflect::getAllTypes()
     */
    public static function getAllTypeNames(?ReflectionType $type): array
    {
        return array_map(
            function (ReflectionType $t) { return $t instanceof ReflectionNamedType ? $t->getName() : (string)$t; },
            self::getAllTypes($type)
        );
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
