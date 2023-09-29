<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Utility\Convert;
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
 * Work with PHP's reflection API
 */
final class Reflect
{
    /**
     * Get a list of names from a list of reflectors
     *
     * @param array<ReflectionClass<object>|\ReflectionClassConstant|\ReflectionFunctionAbstract|ReflectionParameter|ReflectionProperty> $reflectors
     * @return string[]
     */
    public static function getNames(array $reflectors): array
    {
        $names = [];
        foreach ($reflectors as $reflector) {
            $names[] = $reflector->getName();
        }
        return $names;
    }

    /**
     * Follow ReflectionClass->getParentClass() until an ancestor with no parent
     * is found
     *
     * @param ReflectionClass<object>|class-string $class
     * @return ReflectionClass<object>
     */
    public static function getBaseClass($class): ReflectionClass
    {
        if (!($class instanceof ReflectionClass)) {
            $class = new ReflectionClass($class);
        }
        while ($parent = $class->getParentClass()) {
            $class = $parent;
        }
        return $class;
    }

    /**
     * If a method has a prototype, return its declaring class, otherwise return
     * the method's declaring class
     *
     * @return ReflectionClass<object>
     */
    public static function getMethodPrototypeClass(ReflectionMethod $method): ReflectionClass
    {
        try {
            return $method->getPrototype()->getDeclaringClass();
        } catch (ReflectionException $ex) {
            return $method->getDeclaringClass();
        }
    }

    /**
     * Get the simple types in a ReflectionType
     *
     * {@see ReflectionParameter::getType()} and
     * {@see ReflectionProperty::getType()} can return any of the following:
     *
     * - {@see ReflectionType} (until PHP 8)
     * - {@see ReflectionNamedType}
     * - {@see ReflectionUnionType} comprised of {@see ReflectionNamedType} (PHP
     *   8+) and {@see ReflectionIntersectionType} (PHP 8.2+)
     * - {@see ReflectionIntersectionType} comprised of
     *   {@see ReflectionNamedType} (PHP 8.1+)
     *
     * This method normalises them to an array of {@see ReflectionNamedType}.
     *
     * @return ReflectionNamedType[]
     */
    public static function getAllTypes(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }

        $types = [];
        foreach (self::doGetAllTypes($type) as $type) {
            $name = $type->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $types[] = $type;
            $seen[$name] = true;
        }

        return $types;
    }

    /**
     * Get the names of the simple types in a ReflectionType
     *
     * @return string[]
     *
     * @see Reflect::getAllTypes()
     */
    public static function getAllTypeNames(?ReflectionType $type): array
    {
        if ($type === null) {
            return [];
        }

        $names = [];
        foreach (self::doGetAllTypes($type) as $type) {
            $name = $type->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $names[] = $name;
            $seen[$name] = true;
        }

        return $names;
    }

    /**
     * Get an array of doc comments for a ReflectionClass and any ancestors
     *
     * Returns an empty array if no doc comments are found for the class or any
     * inherited classes or interfaces.
     *
     * @param ReflectionClass<object> $class
     * @return array<class-string,string>
     */
    public static function getAllClassDocComments(ReflectionClass $class): array
    {
        $interfaces = self::getInterfaces($class);

        $comments = [];
        do {
            $comment = $class->getDocComment();
            if ($comment !== false) {
                $comments[$class->getName()] = Str::setEol($comment);
            }
        } while ($class = $class->getParentClass());

        foreach ($interfaces as $interface) {
            $comment = $interface->getDocComment();
            if ($comment !== false) {
                $comments[$interface->getName()] = Str::setEol($comment);
            }
        }

        return Convert::stringsToUnique($comments);
    }

    /**
     * Get an array of doc comments for a ReflectionMethod from its declaring
     * class and any ancestors that declare the same method
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes, interfaces or traits.
     *
     * @param array<class-string,string|null>|null $classDocComments If
     * provided, `$classDocComments` is populated with one of the following for
     * each doc comment in the return value:
     * - the doc comment of the declaring class, or
     * - `null` if the declaring class has no doc comment
     * @return array<class-string,string>
     */
    public static function getAllMethodDocComments(
        ReflectionMethod $method,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 1) {
            $classDocComments = [];
        }

        $name = $method->getName();
        $comments = self::doGetAllMethodDocComments($method, $name, $classDocComments);

        foreach (self::getInterfaces($method->getDeclaringClass()) as $interface) {
            if (!$interface->hasMethod($name)) {
                continue;
            }
            $comment = $interface->getMethod($name)->getDocComment();
            if ($comment === false) {
                continue;
            }
            $class = $interface->getName();
            $comments[$class] = Str::setEol($comment);
            if ($classDocComments === null) {
                continue;
            }
            $comment = $interface->getDocComment();
            $classDocComments[$class] =
                $comment === false
                    ? null
                    : Str::setEol($comment);
        }

        return $classDocComments === null
            ? Convert::stringsToUnique($comments)
            : Convert::columnsToUnique($comments, $classDocComments);
    }

    /**
     * Get an array of doc comments for a ReflectionProperty from its declaring
     * class and any ancestors that declare the same property
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes or traits.
     *
     * @param array<class-string,string|null>|null $classDocComments If
     * provided, `$classDocComments` is populated with one of the following for
     * each doc comment in the return value:
     * - the doc comment of the declaring class, or
     * - `null` if the declaring class has no doc comment
     * @return array<class-string,string>
     */
    public static function getAllPropertyDocComments(
        ReflectionProperty $property,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 1) {
            $classDocComments = [];
        }

        $name = $property->getName();
        $comments = self::doGetAllPropertyDocComments($property, $name, $classDocComments);

        return $classDocComments === null
            ? Convert::stringsToUnique($comments)
            : Convert::columnsToUnique($comments, $classDocComments);
    }

    /**
     * Convert the given ReflectionType to a PHP type declaration
     *
     * @param ReflectionType|null $type e.g. the return value of
     * {@see ReflectionParameter::getType()}.
     * @param (callable(string): (string|null))|null $typeNameCallback Applied
     * to qualified class names if given. Must return `null` or an unqualified
     * alias.
     */
    public static function getTypeDeclaration(
        ?ReflectionType $type,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null
    ): string {
        $glue = '|';
        if ($type === null) {
            $types = [];
        } elseif ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $type) {
                if ($type instanceof ReflectionIntersectionType) {
                    $types[] = '(' . self::getTypeDeclaration($type, $classPrefix, $typeNameCallback) . ')';
                    continue;
                }
                $types[] = $type;
            }
        } elseif ($type instanceof ReflectionIntersectionType) {
            $glue = '&';
            $types = $type->getTypes();
        } else {
            $types = [$type];
        }
        $parts = [];
        /** @var array<ReflectionNamedType|string> $types */
        foreach ($types as $type) {
            if (!($type instanceof ReflectionType)) {
                $parts[] = $type;
                continue;
            }
            $name = $type->getName();
            $alias =
                $typeNameCallback === null
                    ? null
                    : $typeNameCallback($name);
            $parts[] = ($type->allowsNull() && strcasecmp($name, 'null') ? '?' : '')
                . ($alias === null && !$type->isBuiltin() ? $classPrefix : '')
                . ($alias ?? $name);
        }

        return implode($glue, $parts);
    }

    /**
     * Convert a ReflectionParameter to a PHP parameter declaration
     *
     * @param callable|null $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     * @param string|null $type If set, ignore the parameter's declared type and
     * use `$type` instead. Do not use when generating code unless `$type` is
     * from a trusted source.
     */
    public static function getParameterDeclaration(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null,
        ?string $type = null,
        ?string $name = null,
        bool $phpDoc = false
    ): string {
        // If getTypeDeclaration isn't called, neither is $typeNameCallback
        $param = self::getTypeDeclaration($parameter->getType(), $classPrefix, $typeNameCallback);
        $param = is_null($type) ? $param : $type;
        $param .= ($param ? ' ' : '')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . ($name ?: $parameter->getName());
        if (!$parameter->isDefaultValueAvailable()) {
            return $param;
        }
        $param .= ' = ';
        if (!$parameter->isDefaultValueConstant()) {
            // Escape commas for phpDocumentor
            return $param . Convert::valueToCode($parameter->getDefaultValue(), ',', '=>', $phpDoc ? ',' : null);
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
     * Convert a ReflectionParameter to a PHPDoc tag
     *
     * Returns `null` if:
     * - `$force` is not set,
     * - `$documentation` is empty or `null`, and
     * - there is no difference between PHPDoc and native data types
     *
     * @param callable|null $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     * @param string|null $type If set, ignore the parameter's declared type and
     * use `$type` instead.
     */
    public static function getParameterPhpDoc(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null,
        ?string $type = null,
        ?string $name = null,
        ?string $documentation = null,
        bool $force = false
    ): ?string {
        // If getTypeDeclaration isn't called, neither is $typeNameCallback
        $param = self::getTypeDeclaration($parameter->getType(), $classPrefix, $typeNameCallback);
        $param = is_null($type) ? $param : $type;
        $param .= ($param ? ' ' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . ($name ?: $parameter->getName());

        if (!$force && !$documentation &&
                preg_replace(
                    ['/ = .*/', '/&(?=(\.\.\.)?\$)/'],
                    '',
                    self::getParameterDeclaration($parameter, $classPrefix, $typeNameCallback, null, $name)
                ) === $param) {
            return null;
        }

        return "@param $param" . ($documentation ? " $documentation" : '');
    }

    /**
     * Get an array of traits used by this class and its parent classes
     *
     * In other words, merge arrays returned by `ReflectionClass::getTraits()`
     * for `$class`, `$class->getParentClass()`, etc.
     *
     * @param ReflectionClass<object> $class
     * @return array<string,ReflectionClass<object>> An array that maps trait
     * names to `ReflectionClass` instances.
     */
    public static function getAllTraits(ReflectionClass $class): array
    {
        $allTraits = [];

        while ($class && ($traits = $class->getTraits())) {
            $allTraits = array_merge($allTraits, $traits);
            $class = $class->getParentClass();
        }

        if ($class) {
            throw new UnexpectedValueException(sprintf('Error retrieving traits for class %s', $class->getName()));
        }

        return $allTraits;
    }

    /**
     * @return ReflectionNamedType[]
     */
    private static function doGetAllTypes(ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $type) {
                if ($type instanceof ReflectionIntersectionType) {
                    array_push($types, ...$type->getTypes());
                    continue;
                }
                $types[] = $type;
            }
            /** @var ReflectionNamedType[] $types */
            return $types;
        }

        if ($type instanceof ReflectionIntersectionType) {
            $types = $type->getTypes();

            /** @var ReflectionNamedType[] $types */
            return $types;
        }

        /** @var ReflectionNamedType $type */
        return [$type];
    }

    /**
     * @param ReflectionClass<object> $class
     * @return array<ReflectionClass<object>>
     */
    private static function getInterfaces(ReflectionClass $class): array
    {
        $interfaces = $class->getInterfaces();

        if (!$interfaces) {
            return [];
        }

        // Group by base interface, then sort children before parents
        usort(
            $interfaces,
            fn(ReflectionClass $a, ReflectionClass $b) =>
                $a->isSubclassOf($b)
                    ? -1
                    : ($b->isSubclassOf($a)
                        ? 1
                        : self::getBaseClass($a)->getName() <=> self::getBaseClass($b)->getName())
        );

        return $interfaces;
    }

    /**
     * @param array<class-string,string|null>|null $classDocComments
     * @return array<class-string,string>
     */
    private static function doGetAllMethodDocComments(
        ReflectionMethod $method,
        string $name,
        ?array &$classDocComments
    ): array {
        $comments = [];
        do {
            $comment = $method->getDocComment();
            $declaring = $method->getDeclaringClass();
            if ($comment !== false) {
                $class = $declaring->getName();
                $comments[$class] = Str::setEol($comment);
                if ($classDocComments !== null) {
                    $comment = $declaring->getDocComment();
                    $classDocComments[$class] =
                        $comment === false
                            ? null
                            : Str::setEol($comment);
                }
            }
            // Interfaces don't have traits, so there's nothing else to do here
            if ($declaring->isInterface()) {
                return $comments;
            }
            // getTraits() doesn't return inherited traits, so recurse into them
            foreach ($declaring->getTraits() as $trait) {
                if (!$trait->hasMethod($name)) {
                    continue;
                }
                $comments = array_merge(
                    $comments,
                    self::doGetAllMethodDocComments(
                        $trait->getMethod($name),
                        $name,
                        $classDocComments
                    )
                );
            }
            $parent = $declaring->getParentClass();
            if (!$parent || !$parent->hasMethod($name)) {
                return $comments;
            }
            $method = $parent->getMethod($name);
        } while (true);
    }

    /**
     * @param array<class-string,string|null>|null $classDocComments
     * @return array<class-string,string>
     */
    private static function doGetAllPropertyDocComments(
        ReflectionProperty $property,
        string $name,
        ?array &$classDocComments
    ): array {
        $comments = [];
        do {
            $comment = $property->getDocComment();
            $declaring = $property->getDeclaringClass();
            if ($comment !== false) {
                $class = $declaring->getName();
                $comments[$class] = Str::setEol($comment);
                if ($classDocComments !== null) {
                    $comment = $declaring->getDocComment();
                    $classDocComments[$class] =
                        $comment === false
                            ? null
                            : Str::setEol($comment);
                }
            }
            foreach ($declaring->getTraits() as $trait) {
                if (!$trait->hasProperty($name)) {
                    continue;
                }
                $comments = array_merge(
                    $comments,
                    self::doGetAllPropertyDocComments(
                        $trait->getProperty($name),
                        $name,
                        $classDocComments
                    )
                );
            }
            $parent = $declaring->getParentClass();
            if (!$parent || !$parent->hasProperty($name)) {
                return $comments;
            }
            $property = $parent->getProperty($name);
        } while (true);
    }
}
