<?php declare(strict_types=1);

namespace Salient\PHPDoc;

use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionClassConstant;
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
final class PHPDocUtil extends AbstractUtility
{
    private const PHPDOC_TYPE = '`^' . PHPDocRegex::PHPDOC_TYPE . '$`D';

    /**
     * Get an array of doc comments for a class and its parents
     *
     * @param ReflectionClass<*> $class
     * @param bool $fromClass If `true`, entries are returned for `$class` and
     * every parent, including any without doc comments.
     * @param bool $groupTraits If `true`, entries for traits are grouped with
     * class entries in a nested array.
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is false ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is false ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    public static function getAllClassDocComments(
        ReflectionClass $class,
        bool $fromClass = false,
        bool $groupTraits = false
    ): array {
        $comments = [];
        $current = $class;
        $seen = [];
        do {
            $name = $current->getName();
            $comment = $current->getDocComment();
            if ($comment !== false || $fromClass) {
                $comments[$name] = $comment === false
                    ? null
                    : Str::setEol($comment);
            }

            if ($current->isInterface()) {
                break;
            }

            if ($traits = $current->getTraits()) {
                if ($groupTraits) {
                    $group = [];
                    if ($comment !== false || $fromClass) {
                        /** @var string|null */
                        $comment = $comments[$name];
                        $group[$name] = $comment;
                    }
                    $originalGroup = $group;
                    foreach ($traits as $traitName => $trait) {
                        // Recurse into inserted traits not already seen
                        if (!array_key_exists($traitName, $seen)) {
                            $traitComments = array_diff_key(
                                self::getAllClassDocComments($trait, $fromClass),
                                $seen,
                            );
                            $group += $traitComments;
                            $seen += $traitComments;
                        }
                    }
                    if ($group !== $originalGroup) {
                        $comments[$name] = $group;
                    }
                } else {
                    foreach ($traits as $trait) {
                        // Recurse into inserted traits
                        $comments = array_merge(
                            $comments,
                            self::getAllClassDocComments($trait, $fromClass),
                        );
                    }
                }
            }
        } while ($current = $current->getParentClass());

        if ($class->isTrait()) {
            return $comments;
        }

        foreach (self::getInterfaces($class) as $name => $interface) {
            $comment = $interface->getDocComment();
            if ($comment !== false || $fromClass) {
                $comments[$name] = $comment === false
                    ? null
                    : Str::setEol($comment);
            }
        }

        return $comments;
    }

    /**
     * Get an array of doc comments for a method from its declaring class and
     * its parents
     *
     * @param ReflectionClass<*>|null $fromClass If given, entries are
     * returned for `$fromClass` and every parent with `$method`, including any
     * without doc comments or where `$method` is not declared.
     * @param bool $groupTraits If `true`, entries for traits are grouped with
     * class entries in a nested array.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is null ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    public static function getAllMethodDocComments(
        ReflectionMethod $method,
        ?ReflectionClass $fromClass = null,
        bool $groupTraits = false,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 3) {
            $classDocComments = [];
        }

        $declaring = $method->getDeclaringClass();
        $class = $fromClass ?? $declaring;
        $name = self::getMethodName($method, $class);
        if ($fromClass && (
            !$fromClass->hasMethod($name)
            || $fromClass->getMethod($name)->getDeclaringClass()->getName()
                !== $declaring->getName()
        )) {
            throw new InvalidArgumentException(sprintf(
                '$fromClass (%s) does not inherit %s::%s()',
                $fromClass->getName(),
                $declaring->getName(),
                $name,
            ));
        }

        $comments = self::doGetAllMethodDocComments(
            $method,
            $fromClass,
            $declaring,
            $name,
            $classDocComments,
            $groupTraits,
        );

        if ($class->isTrait()) {
            return $comments;
        }

        foreach (self::getInterfaces($class) as $interface) {
            if ($interface->hasMethod($name)) {
                $comments = array_merge(
                    $comments,
                    self::doGetAllMethodDocComments(
                        $interface->getMethod($name),
                        $fromClass ? $interface : null,
                        $declaring,
                        $name,
                        $classDocComments,
                    ),
                );
            }
        }

        return $comments;
    }

    /**
     * @param ReflectionClass<*>|null $fromClass
     * @param ReflectionClass<*> $declaring
     * @param array<class-string,string|null>|null $classDocComments
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is null ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    private static function doGetAllMethodDocComments(
        ReflectionMethod $method,
        ?ReflectionClass $fromClass,
        ReflectionClass $declaring,
        string $name,
        ?array &$classDocComments,
        bool $groupTraits = false
    ): array {
        $comments = [];
        $current = $fromClass ?? $method->getDeclaringClass();
        $seen = [];
        do {
            $class = $current->getName();
            $comment = (
                !$fromClass
                || $method->getDeclaringClass()->getName() === $class
            ) && self::isMethodInClass($method, $current, $name)
                ? $method->getDocComment()
                : false;

            if ($comment !== false || $fromClass) {
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);

                if ($classDocComments !== null) {
                    $classComment = $current->getDocComment();
                    $classDocComments[$class] = $classComment === false
                        ? null
                        : Str::setEol($classComment);
                }
            }

            if ($current->isInterface()) {
                return $comments;
            }

            if ($traits = $current->getTraits()) {
                $aliases = self::getTraitAliases($current);
                if ($groupTraits) {
                    $group = [];
                    if ($comment !== false || $fromClass) {
                        /** @var string|null */
                        $comment = $comments[$class];
                        $group[$class] = $comment;
                    }
                    $originalGroup = $group;
                    foreach ($traits as $traitName => $trait) {
                        $originalName = $aliases[$traitName][$name] ?? $name;
                        // Recurse into inserted traits not already seen
                        if (
                            $trait->hasMethod($originalName)
                            && !array_key_exists($traitName, $seen)
                        ) {
                            $traitComments = array_diff_key(
                                self::doGetAllMethodDocComments(
                                    $trait->getMethod($originalName),
                                    $fromClass ? $trait : null,
                                    $declaring,
                                    $originalName,
                                    $classDocComments,
                                ),
                                $seen,
                            );
                            $group += $traitComments;
                            $seen += $traitComments;
                        }
                    }
                    if ($group !== $originalGroup) {
                        $comments[$class] = $group;
                    }
                } else {
                    foreach ($traits as $traitName => $trait) {
                        $originalName = $aliases[$traitName][$name] ?? $name;
                        // Recurse into inserted traits
                        if ($trait->hasMethod($originalName)) {
                            $comments = array_merge(
                                $comments,
                                self::doGetAllMethodDocComments(
                                    $trait->getMethod($originalName),
                                    $fromClass ? $trait : null,
                                    $declaring,
                                    $originalName,
                                    $classDocComments,
                                ),
                            );
                        }
                    }
                }
            }

            $current = $current->getParentClass();
            if (!$current || !$current->hasMethod($name)) {
                return $comments;
            }

            $method = $current->getMethod($name);
            if ($method->isPrivate() && (
                !$fromClass
                || $declaring->isSubclassOf($method->getDeclaringClass())
            )) {
                return $comments;
            }
            if (!$fromClass) {
                $current = $method->getDeclaringClass();
            }
        } while (true);
    }

    /**
     * Get an array of doc comments for a property from its declaring class and
     * its parents
     *
     * @param ReflectionClass<*>|null $fromClass If given, entries are
     * returned for `$fromClass` and every parent with `$property`, including
     * any without doc comments or where `$property` is not declared.
     * @param bool $groupTraits If `true`, entries for traits are grouped with
     * class entries in a nested array.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is null ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    public static function getAllPropertyDocComments(
        ReflectionProperty $property,
        ?ReflectionClass $fromClass = null,
        bool $groupTraits = false,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 3) {
            $classDocComments = [];
        }

        $declaring = $property->getDeclaringClass();
        $name = $property->getName();
        if ($fromClass && (
            !$fromClass->hasProperty($name)
            || $fromClass->getProperty($name)->getDeclaringClass()->getName()
                !== $declaring->getName()
        )) {
            throw new InvalidArgumentException(sprintf(
                '$fromClass (%s) does not inherit %s::$%s',
                $fromClass->getName(),
                $declaring->getName(),
                $name,
            ));
        }

        $comments = self::doGetAllPropertyDocComments(
            $property,
            $fromClass,
            $declaring,
            $name,
            $classDocComments,
            $groupTraits,
        );

        return $comments;
    }

    /**
     * @param ReflectionClass<*>|null $fromClass
     * @param ReflectionClass<*> $declaring
     * @param array<class-string,string|null>|null $classDocComments
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is null ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    private static function doGetAllPropertyDocComments(
        ReflectionProperty $property,
        ?ReflectionClass $fromClass,
        ReflectionClass $declaring,
        string $name,
        ?array &$classDocComments,
        bool $groupTraits = false
    ): array {
        $comments = [];
        $current = $fromClass ?? $property->getDeclaringClass();
        $seen = [];
        do {
            $class = $current->getName();
            $comment = (
                !$fromClass
                || $property->getDeclaringClass()->getName() === $class
            ) && self::isPropertyInClass($property, $current, $name)
                ? $property->getDocComment()
                : false;

            if ($comment !== false || $fromClass) {
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);

                if ($classDocComments !== null) {
                    $classComment = $current->getDocComment();
                    $classDocComments[$class] = $classComment === false
                        ? null
                        : Str::setEol($classComment);
                }
            }

            if ($current->isInterface()) {
                return $comments;
            }

            if ($traits = $current->getTraits()) {
                if ($groupTraits) {
                    $group = [];
                    if ($comment !== false || $fromClass) {
                        /** @var string|null */
                        $comment = $comments[$class];
                        $group[$class] = $comment;
                    }
                    $originalGroup = $group;
                    foreach ($traits as $traitName => $trait) {
                        // Recurse into inserted traits not already seen
                        if (
                            $trait->hasProperty($name)
                            && !array_key_exists($traitName, $seen)
                        ) {
                            $traitComments = array_diff_key(
                                self::doGetAllPropertyDocComments(
                                    $trait->getProperty($name),
                                    $fromClass ? $trait : null,
                                    $declaring,
                                    $name,
                                    $classDocComments,
                                ),
                                $seen,
                            );
                            $group += $traitComments;
                            $seen += $traitComments;
                        }
                    }
                    if ($group !== $originalGroup) {
                        $comments[$class] = $group;
                    }
                } else {
                    foreach ($traits as $trait) {
                        // Recurse into inserted traits
                        if ($trait->hasProperty($name)) {
                            $comments = array_merge(
                                $comments,
                                self::doGetAllPropertyDocComments(
                                    $trait->getProperty($name),
                                    $fromClass ? $trait : null,
                                    $declaring,
                                    $name,
                                    $classDocComments,
                                )
                            );
                        }
                    }
                }
            }

            $current = $current->getParentClass();
            if (!$current || !$current->hasProperty($name)) {
                return $comments;
            }

            $property = $current->getProperty($name);
            if ($property->isPrivate() && (
                !$fromClass
                || $declaring->isSubclassOf($property->getDeclaringClass())
            )) {
                return $comments;
            }
            if (!$fromClass) {
                $current = $property->getDeclaringClass();
            }
        } while (true);
    }

    /**
     * Get an array of doc comments for a class constant from its declaring
     * class and its parents
     *
     * @param ReflectionClass<*>|null $fromClass If given, entries are
     * returned for `$fromClass` and every parent with `$constant`, including
     * any without doc comments or where `$constant` is not declared.
     * @param bool $groupTraits If `true`, entries for traits are grouped with
     * class entries in a nested array.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is null ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    public static function getAllConstantDocComments(
        ReflectionClassConstant $constant,
        ?ReflectionClass $fromClass = null,
        bool $groupTraits = false,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 3) {
            $classDocComments = [];
        }

        $declaring = $constant->getDeclaringClass();
        $class = $fromClass ?? $declaring;
        $name = $constant->getName();
        if ($fromClass && (
            !$fromClass->hasConstant($name)
            || !($fromConstant = $fromClass->getReflectionConstant($name))
            || $fromConstant->getDeclaringClass()->getName()
                !== $declaring->getName()
        )) {
            throw new InvalidArgumentException(sprintf(
                '$fromClass (%s) does not inherit %s::%s',
                $fromClass->getName(),
                $declaring->getName(),
                $name,
            ));
        }

        $comments = self::doGetAllConstantDocComments(
            $constant,
            $fromClass,
            $declaring,
            $name,
            $classDocComments,
            $groupTraits,
        );

        if ($class->isTrait()) {
            return $comments;
        }

        foreach (self::getInterfaces($class) as $interface) {
            if (
                $interface->hasConstant($name)
                && ($constant = $interface->getReflectionConstant($name))
            ) {
                $comments = array_merge(
                    $comments,
                    self::doGetAllConstantDocComments(
                        $constant,
                        $fromClass ? $interface : null,
                        $declaring,
                        $name,
                        $classDocComments,
                    )
                );
            }
        }

        return $comments;
    }

    /**
     * @param ReflectionClass<*>|null $fromClass
     * @param ReflectionClass<*> $declaring
     * @param array<class-string,string|null>|null $classDocComments
     * @return (
     *     $groupTraits is false
     *     ? ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     *     : ($fromClass is null ? array<class-string,array<class-string,string>|string> : array<class-string,array<class-string,string|null>|string|null>)
     * )
     */
    private static function doGetAllConstantDocComments(
        ReflectionClassConstant $constant,
        ?ReflectionClass $fromClass,
        ReflectionClass $declaring,
        string $name,
        ?array &$classDocComments,
        bool $groupTraits = false
    ): array {
        $comments = [];
        $current = $fromClass ?? $constant->getDeclaringClass();
        $seen = [];
        do {
            $class = $current->getName();
            $comment = (
                !$fromClass
                || $constant->getDeclaringClass()->getName() === $class
            ) && self::isConstantInClass($constant, $current, $name)
                ? $constant->getDocComment()
                : false;

            if ($comment !== false || $fromClass) {
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);

                if ($classDocComments !== null) {
                    $classComment = $current->getDocComment();
                    $classDocComments[$class] = $classComment === false
                        ? null
                        : Str::setEol($classComment);
                }
            }

            if ($current->isInterface()) {
                return $comments;
            }

            if (\PHP_VERSION_ID >= 80200 && ($traits = $current->getTraits())) {
                if ($groupTraits) {
                    $group = [];
                    if ($comment !== false || $fromClass) {
                        /** @var string|null */
                        $comment = $comments[$class];
                        $group[$class] = $comment;
                    }
                    $originalGroup = $group;
                    foreach ($traits as $traitName => $trait) {
                        // Recurse into inserted traits not already seen
                        if (
                            $trait->hasConstant($name)
                            && ($constant = $trait->getReflectionConstant($name))
                            && !array_key_exists($traitName, $seen)
                        ) {
                            $traitComments = array_diff_key(
                                self::doGetAllConstantDocComments(
                                    $constant,
                                    $fromClass ? $trait : null,
                                    $declaring,
                                    $name,
                                    $classDocComments,
                                ),
                                $seen,
                            );
                            $group += $traitComments;
                            $seen += $traitComments;
                        }
                    }
                    if ($group !== $originalGroup) {
                        $comments[$class] = $group;
                    }
                } else {
                    foreach ($traits as $trait) {
                        // Recurse into inserted traits
                        if (
                            $trait->hasConstant($name)
                            && ($constant = $trait->getReflectionConstant($name))
                        ) {
                            $comments = array_merge(
                                $comments,
                                self::doGetAllConstantDocComments(
                                    $constant,
                                    $fromClass ? $trait : null,
                                    $declaring,
                                    $name,
                                    $classDocComments,
                                )
                            );
                        }
                    }
                }
            }

            $current = $current->getParentClass();
            if (
                !$current
                || !$current->hasConstant($name)
                || !($constant = $current->getReflectionConstant($name))
            ) {
                return $comments;
            }

            if ($constant->isPrivate() && (
                !$fromClass
                || $declaring->isSubclassOf($constant->getDeclaringClass())
            )) {
                return $comments;
            }
            if (!$fromClass) {
                $current = $constant->getDeclaringClass();
            }
        } while (true);
    }

    /**
     * Normalise a PHPDoc type
     *
     * If `$strict` is `true`, an exception is thrown if `$type` is not a valid
     * PHPDoc type.
     *
     * @param array<string,class-string> $aliases
     */
    public static function normaliseType(string $type, array $aliases = [], bool $strict = false): string
    {
        $type = trim($type);
        if (!Regex::match(self::PHPDOC_TYPE, $type)) {
            if ($strict) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid PHPDoc type '%s'",
                    $type,
                ));
            }
            return self::replaceTypes($type);
        }

        if ($aliases) {
            $regex = implode('|', array_keys($aliases));
            if (count($aliases) > 1) {
                $regex = "(?:{$regex})";
            }
            $regex = "/(?<![\$\\\\-])\b{$regex}\b(?![\\\\-])/i";
            $aliases = array_change_key_case($aliases);
            $type = Regex::replaceCallback(
                $regex,
                fn($matches) => $aliases[Str::lower($matches[0])],
                $type,
            );
        }

        $types = Str::splitDelimited('|', $type, true, null, Str::PRESERVE_QUOTED);

        // Move `null` to the end of union types
        $notNull = [];
        foreach ($types as $t) {
            $t = ltrim($t, '?');
            if (strcasecmp($t, 'null')) {
                $notNull[] = $t;
            }
        }

        if ($notNull !== $types) {
            $types = $notNull;
            $nullable = true;
        }

        // Simplify composite types
        $phpTypeRegex = Regex::delimit('^' . Regex::PHP_TYPE . '$', '/');
        foreach ($types as &$type) {
            $brackets = false;
            if ($type !== '' && $type[0] === '(' && $type[-1] === ')') {
                $brackets = true;
                $type = substr($type, 1, -1);
            }
            $split = array_unique(self::replaceTypes(explode('&', $type)));
            $type = implode('&', $split);
            if ($brackets && (
                count($split) > 1
                || !Regex::match($phpTypeRegex, $type)
            )) {
                $type = "($type)";
            }
        }

        /** @disregard P1006 */
        $types = array_unique(self::replaceTypes($types));
        if ($nullable ?? false) {
            $types[] = 'null';
        }

        return implode('|', $types);
    }

    /**
     * @param string[]|string $types
     * @return ($types is string[] ? string[] : string)
     */
    private static function replaceTypes($types)
    {
        return Regex::replace(
            ['/\bclass-string<(?:mixed|object)>/i', '/(?:\bmixed&|&mixed\b)/i'],
            ['class-string', ''],
            $types,
        );
    }

    /**
     * Remove PHP namespaces from a PHPDoc type
     */
    public static function removeTypeNamespaces(string $type): string
    {
        return Regex::replace(
            '/\\\\?(?:' . Regex::PHP_IDENTIFIER . '\\\\)++(?=' . Regex::PHP_IDENTIFIER . ')/',
            '',
            $type,
        );
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
    public static function getParameterTag(
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

        $param .=
            ($param === '' ? '' : ' ')
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

        $param .=
            ($param === '' ? '' : ' ')
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
     * @param ReflectionClass<*> $class
     * @return array<class-string,ReflectionClass<*>>
     */
    private static function getInterfaces(ReflectionClass $class): array
    {
        // Group by base interface, then sort children before parents
        // @phpstan-ignore return.type
        return Arr::sort(
            $class->getInterfaces(),
            true,
            fn(ReflectionClass $a, ReflectionClass $b) =>
                $a->isSubclassOf($b)
                    ? -1
                    : ($b->isSubclassOf($a)
                        ? 1
                        : Reflect::getBaseClass($a)->getName()
                            <=> Reflect::getBaseClass($b)->getName())
        );
    }

    /**
     * @param ReflectionClass<*> $class
     */
    private static function getMethodName(
        ReflectionMethod $method,
        ReflectionClass $class
    ): string {
        $name = $method->getName();

        // Work around https://bugs.php.net/bug.php?id=69180
        if (\PHP_VERSION_ID < 80000 && !self::isMethodInClass($method, $class, $name)) {
            foreach (self::getTraitAliases($class) as $aliases) {
                $alias = array_search($name, $aliases, true);
                if ($alias !== false) {
                    return $alias;
                }
            }
        }

        return $name;
    }

    /**
     * @param ReflectionClass<*> $class
     */
    private static function isMethodInClass(
        ReflectionMethod $method,
        ReflectionClass $class,
        string $name
    ): bool {
        $result = Reflect::isMethodInClass($method, $class, $name);

        if ($result === null) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException(sprintf(
                'Unable to check method location: %s::%s()',
                $class->getName(),
                $method->getName(),
            ));
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }

    /**
     * @param ReflectionClass<*> $class
     */
    private static function isPropertyInClass(
        ReflectionProperty $property,
        ReflectionClass $class,
        string $name
    ): bool {
        $traits = $class->getTraits();
        if (!$traits) {
            return true;
        }

        foreach ($traits as $trait) {
            if (
                $trait->hasProperty($name)
                && $trait->getProperty($name)->getDocComment() === $property->getDocComment()
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ReflectionClass<*> $class
     */
    private static function isConstantInClass(
        ReflectionClassConstant $constant,
        ReflectionClass $class,
        string $name
    ): bool {
        if (\PHP_VERSION_ID < 80200) {
            return true;
        }

        $traits = $class->getTraits();
        if (!$traits) {
            return true;
        }

        foreach ($traits as $trait) {
            if (
                $trait->hasConstant($name)
                && ($traitConstant = $trait->getReflectionConstant($name))
                && $traitConstant->getDocComment() === $constant->getDocComment()
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get an array that maps traits to [ alias => method ] arrays for the trait
     * method aliases of a class
     *
     * @param ReflectionClass<*> $class
     * @return array<class-string,array<string,string>>
     */
    private static function getTraitAliases(ReflectionClass $class): array
    {
        foreach (Reflect::getTraitAliases($class) as $alias => $original) {
            $aliases[$original[0]][$alias] = $original[1];
        }
        return $aliases ?? [];
    }
}
