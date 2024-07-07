<?php declare(strict_types=1);

namespace Salient\PHPDoc;

use Salient\Utility\AbstractUtility;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Test;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * @internal
 */
final class PHPDocUtility extends AbstractUtility
{
    /**
     * Get an array of doc comments for a class and its parents
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
     * Get an array of doc comments for a method from its declaring class and
     * its parents
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes, interfaces or traits.
     *
     * @template T of ReflectionClass|null
     *
     * @param T $fromClass If given, entries are returned for `$fromClass` and
     * every parent with `$method`, including any without doc comments or
     * where `$method` is not declared.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
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
     * Get an array of doc comments for a property from its declaring class and
     * its parents
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes or traits.
     *
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
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
     * @param (callable(class-string): (string|null))|null $callback Applied to
     * qualified class names if given. Must return `null` or an unqualified
     * alias.
     */
    public static function getTypeDeclaration(
        ?ReflectionType $type,
        string $classPrefix = '\\',
        ?callable $callback = null,
        bool $phpDoc = false
    ): string {
        if ($type === null) {
            return '';
        }

        $glue = '|';
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $type) {
                if (!$type instanceof ReflectionIntersectionType) {
                    $types[] = $type;
                    continue;
                }
                $type = self::getTypeDeclaration(
                    $type,
                    $classPrefix,
                    $callback,
                    $phpDoc,
                );
                $types[] = "($type)";
            }
        } elseif ($type instanceof ReflectionIntersectionType) {
            $glue = '&';
            $types = $type->getTypes();
        } else {
            $types = $phpDoc ? Reflect::normaliseType($type) : [$type];
        }

        $parts = [];
        /** @var array<ReflectionNamedType|string> $types */
        foreach ($types as $type) {
            if (is_string($type)) {
                $parts[] = $type;
                continue;
            }
            $name = $type->getName();
            if ($callback !== null && !$type->isBuiltin()) {
                /** @var class-string $name */
                $alias = $callback($name);
            } else {
                $alias = null;
            }

            $parts[] =
                ($type->allowsNull() && strcasecmp($name, 'null') ? '?' : '')
                . ($alias === null && !$type->isBuiltin() ? $classPrefix : '')
                . ($alias ?? $name);
        }

        return implode($glue, $parts);
    }

    /**
     * Convert a ReflectionParameter to a PHP parameter declaration
     *
     * @param (callable(class-string): (string|null))|null $callback Applied to
     * qualified class names if given. Must return `null` or an unqualified
     * alias.
     * @param string|null $type If set, ignore the parameter's declared type and
     * use `$type` instead. Do not use when generating code unless `$type` is
     * from a trusted source.
     */
    public static function getParameterDeclaration(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $callback = null,
        ?string $type = null,
        ?string $name = null,
        bool $phpDoc = false
    ): string {
        // Always call getTypeDeclaration so $typeNameCallback is always called,
        // otherwise callback-dependent actions are not taken when $type is set
        $param = self::getTypeDeclaration(
            $parameter->getType(),
            $classPrefix,
            $callback,
            $phpDoc,
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
        if (Regex::match('/^(self|parent|static)::/i', $const)) {
            return "$param$const";
        }
        if ($callback) {
            $_const = Regex::replaceCallback(
                '/^' . Regex::PHP_TYPE . '(?=::)/',
                function (array $matches) use ($callback): string {
                    /** @var array{class-string} $matches */
                    return $callback($matches[0]) ?? $matches[0];
                },
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
     * @param (callable(class-string): (string|null))|null $callback Applied to
     * qualified class names if given. Must return `null` or an unqualified
     * alias.
     * @param string|null $type If set, ignore the parameter's declared type and
     * use `$type` instead.
     */
    public static function getParameterPHPDoc(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $callback = null,
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
            $callback,
            true,
        );

        if ($type !== null) {
            $param = $type;
        }

        $param
            .= ($param === '' ? '' : ' ')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . ($name === null ? $parameter->getName() : $name);

        if (!$force && $documentation === null) {
            $native = Regex::replace(
                ['/ = .*/', '/&(?=(\.\.\.)?\$)/'],
                '',
                self::getParameterDeclaration(
                    $parameter,
                    $classPrefix,
                    $callback,
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
     * @param ReflectionClass<object> $class
     */
    private static function isMethodInClass(
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
                        : Reflect::getBaseClass($a)->getName()
                            <=> Reflect::getBaseClass($b)->getName())
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
