<?php

declare(strict_types=1);

namespace Lkrms;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnexpectedValueException;

/**
 * Sometimes The Reflector Is Not Enough
 *
 * @package Lkrms
 */
class Reflect
{
    /**
     * Return Reflector->name for the given Reflector or list thereof
     *
     * @param Reflector|Reflector[] $reflection
     * @return string|string[]
     */
    public static function getName($reflection)
    {
        if (is_array($reflection))
        {
            return array_map(function ($r) { return $r->name; }, $reflection);
        }

        return $reflection->name;
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
     * @see Convert::getAllTypeNames()
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
     * @see Convert::getAllTypes()
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

        if (is_null($traits))
        {
            throw new UnexpectedValueException("Error retrieving traits for class {$class->name}");
        }

        return $traits;
    }
}

