<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Reflection;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

/**
 * A facade for \Lkrms\Utility\Reflection
 *
 * @method static Reflection load() Load and return an instance of the underlying Reflection class
 * @method static Reflection getInstance() Get the underlying Reflection instance
 * @method static bool isLoaded() True if an underlying Reflection instance has been loaded
 * @method static void unload() Clear the underlying Reflection instance
 * @method static string[] getAllClassDocComments(ReflectionClass $class) Get an array of doc comments for a ReflectionClass and any ancestors (see {@see Reflection::getAllClassDocComments()})
 * @method static array<string,ReflectionClass> getAllTraits(ReflectionClass $class) Get an array of traits used by this class and its parent classes (see {@see Reflection::getAllTraits()})
 * @method static string[] getAllTypeNames(?ReflectionType $type) Get the names of all types in a ReflectionType (see {@see Reflection::getAllTypeNames()})
 * @method static array<ReflectionNamedType|ReflectionType> getAllTypes(?ReflectionType $type) Get all types in a ReflectionType (see {@see Reflection::getAllTypes()})
 * @method static ReflectionClass getBaseClass(ReflectionClass $class) Follow ReflectionClass->getParentClass() until an ancestor with no parent is found
 * @method static string[] getClassNamesBetween(string|ReflectionClass $child, string|ReflectionClass $parent, bool $includeParent = true) Get the names of a class and its parents, up to and optionally including $parent (see {@see Reflection::getClassNamesBetween()})
 * @method static ReflectionClass getMethodPrototypeClass(ReflectionMethod $method) If a method has a prototype, return its declaring class, otherwise return the method's declaring class
 * @method static string[] getNames(array<ReflectionClass|ReflectionClassConstant|ReflectionFunctionAbstract|ReflectionParameter|ReflectionProperty> $reflectors) Get the names of Reflector objects
 * @method static string getParameterDeclaration(ReflectionParameter $parameter, string $classPrefix = '\\', callable|null $typeNameCallback = null, string|null $type = null, ?string $name = null, bool $phpDoc = false) Convert a ReflectionParameter to a PHP parameter declaration (see {@see Reflection::getParameterDeclaration()})
 * @method static string|null getParameterPhpDoc(ReflectionParameter $parameter, string $classPrefix = '\\', callable|null $typeNameCallback = null, string|null $type = null, ?string $name = null, ?string $documentation = null, bool $force = false) Convert a ReflectionParameter to a PHPDoc tag (see {@see Reflection::getParameterPhpDoc()})
 * @method static string getTypeDeclaration(ReflectionType|null $type, string $classPrefix = '\\', callable|null $typeNameCallback = null) Convert the given ReflectionType to a PHP type declaration (see {@see Reflection::getTypeDeclaration()})
 *
 * @uses Reflection
 *
 * @extends Facade<Reflection>
 *
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Reflection' 'Lkrms\Facade\Reflect'
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

    /**
     * Get an array of doc comments for a ReflectionMethod from its declaring class and any ancestors that declare the same method
     *
     * @param array<string|null>|null $classDocComments
     * @return string[]
     * @see Reflection::getAllMethodDocComments()
     */
    public static function getAllMethodDocComments(ReflectionMethod $method, ?array &$classDocComments = null): array
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->getAllMethodDocComments($method, $classDocComments);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }

    /**
     * Get an array of doc comments for a ReflectionProperty from its declaring class and any ancestors that declare the same property
     *
     * @param array<string|null>|null $classDocComments
     * @return string[]
     * @see Reflection::getAllPropertyDocComments()
     */
    public static function getAllPropertyDocComments(ReflectionProperty $property, ?array &$classDocComments = null): array
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->getAllPropertyDocComments($property, $classDocComments);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }
}
