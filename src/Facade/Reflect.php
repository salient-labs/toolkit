<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Reflection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

/**
 * A facade for \Lkrms\Utility\Reflection
 *
 * @method static Reflection load() Load and return an instance of the underlying Reflection class
 * @method static Reflection getInstance() Return the underlying Reflection instance
 * @method static bool isLoaded() Return true if an underlying Reflection instance has been loaded
 * @method static void unload() Clear the underlying Reflection instance
 * @method static string[] getAllMethodDocComments(ReflectionMethod $method) Get an array of doc comments for a ReflectionMethod from its declaring class and any ancestors that declare the same method (see {@see Reflection::getAllMethodDocComments()})
 * @method static string[] getAllPropertyDocComments(ReflectionProperty $property) Get an array of doc comments for a ReflectionProperty from its declaring class and any ancestors that declare the same property (see {@see Reflection::getAllPropertyDocComments()})
 * @method static array getAllTraits(ReflectionClass $class) Return an array of traits used by this class and its parent classes (see {@see Reflection::getAllTraits()})
 * @method static string[] getAllTypeNames(?ReflectionType $type) Return the names of all types included in the given ReflectionType (see {@see Reflection::getAllTypeNames()})
 * @method static ReflectionNamedType[]|ReflectionType[] getAllTypes(?ReflectionType $type) Return all types included in the given ReflectionType (see {@see Reflection::getAllTypes()})
 * @method static ReflectionClass getBaseClass(ReflectionClass $class) Follow ReflectionClass->getParentClass() until an ancestor with no parent is found (see {@see Reflection::getBaseClass()})
 * @method static string[] getClassNamesBetween(string|ReflectionClass $child, string|ReflectionClass $parent, bool $includeParent = true) Return the names of a class and its parents, up to and optionally including $parent (see {@see Reflection::getClassNamesBetween()})
 * @method static ReflectionClass getMethodPrototypeClass(ReflectionMethod $method) If a method has a prototype, return its declaring class, otherwise return the method's declaring class (see {@see Reflection::getMethodPrototypeClass()})
 * @method static string[] getNames(array $reflections) Return the names of the given Reflection objects (see {@see Reflection::getNames()})
 * @method static string getParameterDeclaration(ReflectionParameter $parameter, string $classPrefix = '\\', ?callable $typeNameCallback = null, ?string $type = null, ?string $name = null) Convert a ReflectionParameter to a PHP parameter declaration (see {@see Reflection::getParameterDeclaration()})
 * @method static string|null getParameterPhpDoc(ReflectionParameter $parameter, string $classPrefix = '\\', ?callable $typeNameCallback = null, ?string $type = null, ?string $name = null, ?string $documentation = null, bool $force = false) Convert a ReflectionParameter to a PHPDoc tag (see {@see Reflection::getParameterPhpDoc()})
 * @method static string getTypeDeclaration(?ReflectionType $type, string $classPrefix = '\\', ?callable $typeNameCallback = null) Convert the given ReflectionType to a PHP type declaration (see {@see Reflection::getTypeDeclaration()})
 * @method static string getTypeName(ReflectionType $type) Return the name of the given ReflectionNamedType or ReflectionType (see {@see Reflection::getTypeName()})
 *
 * @uses Reflection
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
}
