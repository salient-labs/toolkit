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
use LogicException;
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
     * Returns an empty array if no doc comments are found for the class or any
     * extended classes or interfaces.
     *
     * @param ReflectionClass<*> $class
     * @param bool $includeAll If `true`, entries are returned for `$class` and
     * every parent, including any without doc comments.
     * @return ($includeAll is false ? array<class-string,string> : array<class-string,string|null>)
     */
    public static function getAllClassDocComments(
        ReflectionClass $class,
        bool $includeAll = false
    ): array {
        $comments = [];
        $current = $class;
        do {
            $name = $current->getName();
            $comment = $current->getDocComment();
            if ($comment === false) {
                if ($includeAll) {
                    $comments[$name] = null;
                }
            } else {
                $comments[$name] = Str::setEol($comment);
            }

            if ($current->isInterface()) {
                break;
            }

            foreach ($current->getTraits() as $trait) {
                // Recurse into inserted traits
                $comments = array_merge(
                    $comments,
                    self::getAllClassDocComments($trait, $includeAll),
                );
            }
        } while ($current = $current->getParentClass());

        if ($class->isTrait()) {
            return $comments;
        }

        foreach (self::getInterfaces($class) as $name => $interface) {
            $comment = $interface->getDocComment();
            if ($comment === false) {
                if ($includeAll) {
                    $comments[$name] = null;
                }
            } else {
                $comments[$name] = Str::setEol($comment);
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
     * @param ReflectionClass<*>|null $fromClass If given, entries are
     * returned for `$fromClass` and every parent with `$method`, including any
     * without doc comments or where `$method` is not declared.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
     * @return ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     */
    public static function getAllMethodDocComments(
        ReflectionMethod $method,
        ?ReflectionClass $fromClass = null,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 2) {
            $classDocComments = [];
        }

        $class = $fromClass ?? $method->getDeclaringClass();
        $name = self::getMethodName($method, $class);
        $comments = self::doGetAllMethodDocComments(
            $method,
            $fromClass,
            $name,
            $classDocComments,
        );

        foreach (self::getInterfaces($class) as $interface) {
            if (!$interface->hasMethod($name)) {
                continue;
            }
            $comments = array_merge(
                $comments,
                self::doGetAllMethodDocComments(
                    $interface->getMethod($name),
                    $fromClass ? $interface : null,
                    $name,
                    $classDocComments,
                )
            );
        }

        return $comments;
    }

    /**
     * @param ReflectionClass<*>|null $fromClass
     * @param array<class-string,string|null>|null $classDocComments
     * @return ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
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
            // The declaring class of a method declared in a trait is always the
            // class or trait that inserted it, so use its location to be sure
            $isDeclaring = (
                !$fromClass
                || $method->getDeclaringClass()->getName() === $current->getName()
            ) && self::isMethodInClass($method, $current, $name);

            $comment = $isDeclaring ? $method->getDocComment() : false;

            if ($comment !== false || $fromClass) {
                $class = $current->getName();
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);

                if ($classDocComments !== null) {
                    $comment = $current->getDocComment();
                    $classDocComments[$class] = $comment === false
                        ? null
                        : Str::setEol($comment);
                }
            }

            if ($current->isInterface()) {
                return $comments;
            }

            $aliases = self::getTraitAliases($current);
            foreach ($current->getTraits() as $traitName => $trait) {
                $originalName = $aliases[$traitName][$name] ?? $name;
                if (!$trait->hasMethod($originalName)) {
                    continue;
                }
                // Recurse into inserted traits
                $comments = array_merge(
                    $comments,
                    self::doGetAllMethodDocComments(
                        $trait->getMethod($originalName),
                        $fromClass ? $trait : null,
                        $originalName,
                        $classDocComments,
                    ),
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
     * @param ReflectionClass<*>|null $fromClass If given, entries are
     * returned for `$fromClass` and every parent with `$property`, including
     * any without doc comments or where `$property` is not declared.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
     * @return ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     */
    public static function getAllPropertyDocComments(
        ReflectionProperty $property,
        ?ReflectionClass $fromClass = null,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 2) {
            $classDocComments = [];
        }

        $name = $property->getName();
        $comments = self::doGetAllPropertyDocComments(
            $property,
            $fromClass,
            $name,
            $classDocComments,
        );

        return $comments;
    }

    /**
     * @param ReflectionClass<*>|null $fromClass
     * @param array<class-string,string|null>|null $classDocComments
     * @return ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     */
    private static function doGetAllPropertyDocComments(
        ReflectionProperty $property,
        ?ReflectionClass $fromClass,
        string $name,
        ?array &$classDocComments
    ): array {
        $comments = [];
        $current = $fromClass ?? $property->getDeclaringClass();
        do {
            $isDeclaring = (
                !$fromClass
                || $property->getDeclaringClass()->getName() === $current->getName()
            ) && self::isPropertyInClass($property, $current, $name);

            $comment = $isDeclaring ? $property->getDocComment() : false;

            if ($comment !== false || $fromClass) {
                $class = $current->getName();
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);
                if ($classDocComments !== null) {
                    $comment = $current->getDocComment();
                    $classDocComments[$class] = $comment === false
                        ? null
                        : Str::setEol($comment);
                }
            }

            foreach ($current->getTraits() as $trait) {
                if (!$trait->hasProperty($name)) {
                    continue;
                }
                // Recurse into inserted traits
                $comments = array_merge(
                    $comments,
                    self::doGetAllPropertyDocComments(
                        $trait->getProperty($name),
                        $fromClass ? $trait : null,
                        $name,
                        $classDocComments,
                    )
                );
            }

            $current = $current->getParentClass();
            if (!$current || !$current->hasProperty($name)) {
                return $comments;
            }

            $property = $current->getProperty($name);
            if (!$fromClass) {
                $current = $property->getDeclaringClass();
            }
        } while (true);
    }

    /**
     * Get an array of doc comments for a class constant from its declaring
     * class and its parents
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes, interfaces or traits.
     *
     * @param ReflectionClass<*>|null $fromClass If given, entries are
     * returned for `$fromClass` and every parent with `$constant`, including
     * any without doc comments or where `$constant` is not declared.
     * @param array<class-string,string|null>|null $classDocComments If given,
     * receives the doc comment of the declaring class for each entry in the
     * return value.
     * @return ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     */
    public static function getAllConstantDocComments(
        ReflectionClassConstant $constant,
        ?ReflectionClass $fromClass = null,
        ?array &$classDocComments = null
    ): array {
        if (func_num_args() > 2) {
            $classDocComments = [];
        }

        $class = $fromClass ?? $constant->getDeclaringClass();
        $name = $constant->getName();
        $comments = self::doGetAllConstantDocComments(
            $constant,
            $fromClass,
            $name,
            $classDocComments,
        );

        foreach (self::getInterfaces($class) as $interface) {
            if (
                !$interface->hasConstant($name)
                || !($constant = $interface->getReflectionConstant($name))
            ) {
                continue;
            }
            $comments = array_merge(
                $comments,
                self::doGetAllConstantDocComments(
                    $constant,
                    $fromClass ? $interface : null,
                    $name,
                    $classDocComments,
                )
            );
        }

        return $comments;
    }

    /**
     * @param ReflectionClass<*>|null $fromClass
     * @param array<class-string,string|null>|null $classDocComments
     * @return ($fromClass is null ? array<class-string,string> : array<class-string,string|null>)
     */
    private static function doGetAllConstantDocComments(
        ReflectionClassConstant $constant,
        ?ReflectionClass $fromClass,
        string $name,
        ?array &$classDocComments
    ): array {
        $comments = [];
        $current = $fromClass ?? $constant->getDeclaringClass();
        do {
            $isDeclaring = (
                !$fromClass
                || $constant->getDeclaringClass()->getName() === $current->getName()
            ) && self::isConstantInClass($constant, $current, $name);

            $comment = $isDeclaring ? $constant->getDocComment() : false;

            if ($comment !== false || $fromClass) {
                $class = $current->getName();
                $comments[$class] = $comment === false
                    ? null
                    : Str::setEol($comment);

                if ($classDocComments !== null) {
                    $comment = $current->getDocComment();
                    $classDocComments[$class] = $comment === false
                        ? null
                        : Str::setEol($comment);
                }
            }

            if ($current->isInterface()) {
                return $comments;
            }

            if (\PHP_VERSION_ID >= 80200) {
                foreach ($current->getTraits() as $trait) {
                    if (
                        !$trait->hasConstant($name)
                        || !($constant = $trait->getReflectionConstant($name))
                    ) {
                        continue;
                    }
                    // Recurse into inserted traits
                    $comments = array_merge(
                        $comments,
                        self::doGetAllConstantDocComments(
                            $constant,
                            $fromClass ? $trait : null,
                            $name,
                            $classDocComments,
                        )
                    );
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

        $types = array_unique(self::replaceTypes($types));
        if ($nullable ?? false) {
            $types[] = 'null';
        }

        return implode('|', $types);
    }

    /**
     * @template T of string[]|string
     *
     * @param T $types
     * @return T
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
        if ($method->isInternal() && !$class->isInternal()) {
            return false;
        }

        $traits = $class->getTraits();
        if (!$traits) {
            return true;
        }

        $location = [
            $method->getFileName(),
            $method->getStartLine(),
            $class->getStartLine(),
            $class->getEndLine(),
        ];

        if (in_array(false, $location, true)) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException(sprintf(
                'Unable to check method location: %s::%s()',
                $class->getName(),
                $method->getName(),
            ));
            // @codeCoverageIgnoreEnd
        }

        [$file, $line, $start, $end] = $location;

        if (
            $file !== $class->getFileName()
            || $line < $start
            || $line > $end
        ) {
            return false;
        }

        if ($line > $start && $line < $end) {
            return true;
        }

        // Check if the method belongs to an adjacent trait on the same line
        if ($inserted = Reflect::getTraitAliases($class)[$name] ?? null) {
            $traits = array_intersect_key($traits, [$inserted[0] => null]);
            $name = $inserted[1];
        }
        foreach ($traits as $trait) {
            if (
                $trait->hasMethod($name)
                && ($traitMethod = $trait->getMethod($name))->getFileName() === $file
                && $traitMethod->getStartLine() === $line
            ) {
                throw new LogicException(sprintf(
                    'Unable to check location of %s::%s(): %s::%s() declared on same line',
                    $class->getName(),
                    $method->getName(),
                    $traitMethod->getDeclaringClass()->getName(),
                    $name,
                ));
            }
        }

        // @codeCoverageIgnoreStart
        return true;
        // @codeCoverageIgnoreEnd
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
