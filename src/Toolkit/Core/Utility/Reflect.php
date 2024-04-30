<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Contract\Core\Regex;
use Salient\Core\Exception\LogicException;
use Salient\Core\AbstractUtility;
use Closure;
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
     * Get a list of classes accepted by the first parameter of a callback
     *
     * @return class-string[]
     * @throws LogicException if `$callback` resolves to a closure with no
     * parameters.
     */
    public static function getFirstCallbackParameterClassNames(callable $callback): array
    {
        if (!($callback instanceof Closure)) {
            $callback = Closure::fromCallable($callback);
        }
        $param = Arr::first((new ReflectionFunction($callback))->getParameters());
        if (!$param) {
            throw new LogicException('$callable has no parameters');
        }
        foreach (self::getAllTypes($param->getType()) as $type) {
            if ($type->isBuiltin()) {
                continue;
            }
            $classes[] = $type->getName();
        }
        return $classes ?? [];
    }

    /**
     * Follow ReflectionClass->getParentClass() until an ancestor with no parent
     * is found
     *
     * @param ReflectionClass<object> $class
     * @return ReflectionClass<object>
     */
    public static function getBaseClass(ReflectionClass $class): ReflectionClass
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
     * Get the prototype of a method and return its declaring class, or return
     * the method's declaring class if it doesn't have a prototype
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
     * Get the properties of a class, including private ancestor properties
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

        foreach (self::doGetAllTypes($type) as $type) {
            $name = $type->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $types[] = $type;
            $seen[$name] = true;
        }

        return $types ?? [];
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

        foreach (self::doGetAllTypes($type) as $type) {
            $name = $type->getName();
            if (isset($seen[$name])) {
                continue;
            }
            $names[] = $name;
            $seen[$name] = true;
        }

        return $names ?? [];
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

        return $comments;
    }

    /**
     * Get an array of doc comments for a ReflectionMethod from its declaring
     * class and any ancestors that declare the same method
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes, interfaces or traits.
     *
     * @template T of ReflectionClass|null
     *
     * @param T $fromClass If given, entries are returned for `$fromClass` and
     * every ancestor with `$method`, including any without doc comments or
     * where `$method` is not declared.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class of each entry in the
     * return value, or `null` if the declaring class has no doc comment.
     * @return (T is null ? array<class-string,string> : array<class-string,string|null>)
     */
    public static function getAllMethodDocComments(
        ReflectionMethod $method,
        ?ReflectionClass $fromClass = null,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 2) {
            $classDocComments = [];
        }

        $name = $method->getName();
        $comments = self::doGetAllMethodDocComments(
            $method,
            $fromClass,
            $name,
            $classDocComments
        );

        foreach (self::getInterfaces($fromClass ?? $method->getDeclaringClass()) as $interface) {
            if (!$interface->hasMethod($name)) {
                continue;
            }
            $comments = array_merge(
                $comments,
                self::doGetAllMethodDocComments(
                    $interface->getMethod($name),
                    $fromClass ? $interface : null,
                    $name,
                    $classDocComments
                )
            );
        }

        return $comments;
    }

    /**
     * @template T of ReflectionClass|null
     *
     * @param T $fromClass
     * @param array<class-string,string|null>|null $classDocComments
     * @return (T is null ? array<class-string,string> : array<class-string,string|null>)
     */
    private static function doGetAllMethodDocComments(
        ReflectionMethod $method,
        ?ReflectionClass $fromClass,
        string $name,
        ?array &$classDocComments
    ): array {
        $comments = [];
        $current = $fromClass ?? $method->getDeclaringClass();
        do {
            // The declaring class of methods declared in traits is always the
            // class or trait that inserted it, so use the location of the
            // declaration's code as an additional check
            $isDeclaring =
                ($fromClass
                    ? $method->getDeclaringClass()->getName() === $current->getName()
                    : true)
                && self::isMethodInClass($method, $current);

            $comment = $isDeclaring ? $method->getDocComment() : false;

            if ($comment !== false || $fromClass) {
                $class = $current->getName();
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);

                if ($classDocComments !== null) {
                    $comment = $current->getDocComment();
                    $classDocComments[$class] =
                        $comment === false
                            ? null
                            : Str::setEol($comment);
                }
            }

            // Interfaces don't have traits and their parents are returned by
            // getInterfaces(), so there's nothing else to do here
            if ($current->isInterface()) {
                return $comments;
            }

            // getTraits() doesn't return inherited traits, so recurse into them
            foreach ($current->getTraits() as $trait) {
                if (!$trait->hasMethod($name)) {
                    continue;
                }
                $comments = array_merge(
                    $comments,
                    self::doGetAllMethodDocComments(
                        $trait->getMethod($name),
                        $fromClass ? $trait : null,
                        $name,
                        $classDocComments
                    )
                );
            }

            $current = $current->getParentClass();
            if (!$current || !$current->hasMethod($name)) {
                return $comments;
            }

            $method = $current->getMethod($name);
            if (!$fromClass) {
                $current = $method->getDeclaringClass();
            }
        } while (true);
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

        return $comments;
    }

    /**
     * Convert a ReflectionType to a PHP type declaration
     *
     * @param (callable(class-string): (string|null))|null $typeNameCallback Applied
     * to qualified class names if given. Must return `null` or an unqualified
     * alias.
     */
    public static function getTypeDeclaration(
        ?ReflectionType $type,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null
    ): string {
        if ($type === null) {
            return '';
        }

        $glue = '|';
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $type) {
                if (!($type instanceof ReflectionIntersectionType)) {
                    $types[] = $type;
                    continue;
                }
                $type = self::getTypeDeclaration(
                    $type,
                    $classPrefix,
                    $typeNameCallback
                );
                $types[] = "($type)";
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
            if (is_string($type)) {
                $parts[] = $type;
                continue;
            }
            $name = $type->getName();
            $alias =
                $typeNameCallback === null || $type->isBuiltin()
                    ? null
                    : $typeNameCallback($name);
            $parts[] =
                ($type->allowsNull() && strcasecmp($name, 'null') ? '?' : '')
                . ($alias === null && !$type->isBuiltin() ? $classPrefix : '')
                . ($alias === null ? $name : $alias);
        }

        return implode($glue, $parts);
    }

    /**
     * Convert a ReflectionParameter to a PHP parameter declaration
     *
     * @param (callable(class-string): (string|null))|null $typeNameCallback Applied
     * to qualified class names if given. Must return `null` or an unqualified
     * alias.
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
        // Always call getTypeDeclaration so $typeNameCallback is always called,
        // otherwise callback-dependent actions are not taken when $type is set
        $param = self::getTypeDeclaration(
            $parameter->getType(),
            $classPrefix,
            $typeNameCallback
        );

        if ($type !== null) {
            $param = $type;
        }

        $param
            .= ($param === '' ? '' : ' ')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . ($name === null ? $parameter->getName() : $name);

        if (!$parameter->isDefaultValueAvailable()) {
            return $param;
        }

        $param .= ' = ';

        if (!$parameter->isDefaultValueConstant()) {
            $value = $parameter->getDefaultValue();
            // Escape commas for phpDocumentor
            $escape = $phpDoc ? ',' : null;
            $param .= Get::code($value, ',', '=>', $escape);
            return $param;
        }

        /** @var string */
        $const = $parameter->getDefaultValueConstantName();
        if (Pcre::match('/^(self|parent|static)::/i', $const)) {
            return "$param$const";
        }
        if ($typeNameCallback) {
            $_const = Pcre::replaceCallback(
                '/^' . Regex::PHP_TYPE . '(?=::)/',
                fn(array $matches): string =>
                    $typeNameCallback($matches[0]) ?? $matches[0],
                $const
            );
            if ($_const !== $const) {
                return "$param$_const";
            }
        }

        return "$param$classPrefix$const";
    }

    /**
     * Convert a ReflectionParameter to a PHPDoc tag
     *
     * Returns `null` if:
     * - `$force` is not set,
     * - `$documentation` is empty or `null`, and
     * - there is no difference between PHPDoc and native data types
     *
     * @param (callable(class-string): (string|null))|null $typeNameCallback Applied
     * to qualified class names if given. Must return `null` or an unqualified
     * alias.
     * @param string|null $type If set, ignore the parameter's declared type and
     * use `$type` instead.
     */
    public static function getParameterPHPDoc(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null,
        ?string $type = null,
        ?string $name = null,
        ?string $documentation = null,
        bool $force = false
    ): ?string {
        // Always call getTypeDeclaration so $typeNameCallback is always called,
        // otherwise callback-dependent actions are not taken when $type is set
        $param = self::getTypeDeclaration(
            $parameter->getType(),
            $classPrefix,
            $typeNameCallback
        );

        if ($type !== null) {
            $param = $type;
        }

        $param
            .= ($param === '' ? '' : ' ')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . ($name === null ? $parameter->getName() : $name);

        if (!$force && $documentation === null) {
            $native = Pcre::replace(
                ['/ = .*/', '/&(?=(\.\.\.)?\$)/'],
                '',
                self::getParameterDeclaration(
                    $parameter,
                    $classPrefix,
                    $typeNameCallback,
                    null,
                    $name
                )
            );
            if ($native === $param) {
                return null;
            }
        }

        return "@param $param"
            . ($documentation === null ? '' : " $documentation");
    }

    /**
     * Check if a method's declaration appears between the first and last line
     * of a class/trait/interface
     *
     * @param ReflectionClass<object> $class
     */
    public static function isMethodInClass(
        ReflectionMethod $method,
        ReflectionClass $class
    ): bool {
        $file = $method->getFileName();
        if ($file === false || $file !== $class->getFileName()) {
            return false;
        }

        [$line, $start, $end] = [
            $method->getStartLine(),
            $class->getStartLine(),
            $class->getEndLine(),
        ];

        return
            ($line && $start && $end)
            && Test::isBetween($line, $start, $end);
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
                        : self::getBaseClass($a)->getName()
                            <=> self::getBaseClass($b)->getName())
        );

        return $interfaces;
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
