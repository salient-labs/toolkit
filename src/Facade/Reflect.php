<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Reflection;
use Lkrms\Utility\ReflectionClassConstant;
use Lkrms\Utility\ReflectionFunctionAbstract;
use Lkrms\Utility\ReflectionProperty;
use ReflectionClass;
use ReflectionParameter;
use ReflectionType;

/**
 * A facade for Reflection
 *
 * @method static array<string,ReflectionClass> getAllTraits(ReflectionClass $class) Return an array of traits used by this class and its parent classes
 * @method static string[] getAllTypeNames(?ReflectionType $type) Return the names of all types included in the given ReflectionType
 * @method static ReflectionType[] getAllTypes(?ReflectionType $type) Return all types included in the given ReflectionType
 * @method static string[] getClassNamesBetween(string|ReflectionClass $child, string|ReflectionClass $parent, bool $instantiable = false) Return the names of a class and its parents, up to and including $parent
 * @method static string[] getNames(array<int,\ReflectionClass|\ReflectionClassConstant|\ReflectionFunctionAbstract|\ReflectionParameter|\ReflectionProperty> $reflections) Return the names of the given Reflection objects
 * @method static string getParameterDeclaration(ReflectionParameter $parameter, string $classPrefix = '\\', ?callable $typeNameCallback = null, ?string $type = null) Convert the given ReflectionParameter to a PHP parameter declaration
 * @method static string getTypeDeclaration(?ReflectionType $type, string $classPrefix = '\\', ?callable $typeNameCallback = null) Convert the given ReflectionType to a PHP type declaration
 * @method static string getTypeName(ReflectionType $type) Return the name of the given ReflectionNamedType or ReflectionType
 *
 * @uses Reflection
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Reflection' --generate='Lkrms\Facade\Reflect'
 */
final class Reflect extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Reflection::class;
    }
}
