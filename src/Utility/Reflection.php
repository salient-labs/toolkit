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
 * Work with PHP's Reflector classes
 */
final class Reflection
{
    /**
     * Get the names of Reflector objects
     *
     * @param array<\ReflectionClass|\ReflectionClassConstant|\ReflectionFunctionAbstract|\ReflectionParameter|\ReflectionProperty> $reflectors
     * @return string[]
     */
    public function getNames(array $reflectors): array
    {
        return array_map(
            /** @param \ReflectionClass|\ReflectionClassConstant|\ReflectionFunctionAbstract|\ReflectionParameter|\ReflectionProperty $reflector */
            fn($reflector) => $reflector->getName(),
            $reflectors
        );
    }

    /**
     * Get a list of classes between a child and one of its parents
     *
     * Returns the canonical name of `$child`, followed by the names of its
     * parent classes up to and optionally including `$parent`.
     *
     * @template TParent of object
     * @template TChild of TParent
     * @param class-string<TChild> $child
     * @param class-string<TParent> $parent
     * @return class-string<TParent>[]
     */
    public function getClassesBetween(string $child, string $parent, bool $withParent = true): array
    {
        if (!is_a($child, $parent, true) || interface_exists($parent)) {
            return [];
        }

        $child = new ReflectionClass($child);
        $parent = new ReflectionClass($parent);

        $names = [];
        while ($child->isSubclassOf($parent)) {
            $names[] = $child->getName();
            $child = $child->getParentClass();
        }
        if ($withParent) {
            $names[] = $parent->getName();
        }

        return $names;
    }

    /**
     * Follow ReflectionClass->getParentClass() until an ancestor with no parent
     * is found
     */
    public function getBaseClass(ReflectionClass $class): ReflectionClass
    {
        while ($parent = $class->getParentClass()) {
            $class = $parent;
        }

        return $class;
    }

    /**
     * If a method has a prototype, return its declaring class, otherwise return
     * the method's declaring class
     */
    public function getMethodPrototypeClass(ReflectionMethod $method): ReflectionClass
    {
        try {
            return $method->getPrototype()->getDeclaringClass();
        } catch (ReflectionException $ex) {
            return $method->getDeclaringClass();
        }
    }

    /**
     * Get all types in a ReflectionType
     *
     * Different versions of PHP return different `ReflectionType` objects:
     *
     * - `ReflectionType` (became `abstract` in PHP 8)
     * - `ReflectionNamedType` (PHP 7.1+)
     * - `ReflectionUnionType` (PHP 8+)
     * - `ReflectionIntersectionType` (PHP 8.1+)
     *
     * {@see Reflection::getAllTypes()} normalises them to an array of
     * `ReflectionNamedType` and/or `ReflectionType` instances.
     *
     * @return array<ReflectionNamedType|ReflectionType>
     * @see Reflection::getAllTypeNames()
     */
    public function getAllTypes(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType ||
                $type instanceof ReflectionIntersectionType) {
            return $type->getTypes();
        }

        return is_null($type) ? [] : [$type];
    }

    /**
     * Get the names of all types in a ReflectionType
     *
     * @return string[]
     * @see Reflection::getAllTypes()
     */
    public function getAllTypeNames(?ReflectionType $type): array
    {
        return array_map(
            fn(ReflectionType $t) => $this->getTypeName($t),
            $this->getAllTypes($type)
        );
    }

    /**
     * Get the name of a ReflectionNamedType or ReflectionType
     */
    private function getTypeName(ReflectionType $type): string
    {
        return $type instanceof ReflectionNamedType
            ? $type->getName()
            // @phpstan-ignore-next-line
            : (string) $type;
    }

    /**
     * Get an array of doc comments for a ReflectionClass and any ancestors
     *
     * Returns an empty array if no doc comments are found for the class or any
     * inherited classes or interfaces.
     *
     * @return array<class-string,string>
     */
    public function getAllClassDocComments(ReflectionClass $class): array
    {
        $interfaces = $this->getInterfaces($class);
        $comments = [];
        do {
            if (($comment = $class->getDocComment()) !== false) {
                $comments[$class->getName()] = Str::setEol($comment);
            }
        } while ($class = $class->getParentClass());

        foreach ($interfaces as $interface) {
            if (($comment = $interface->getDocComment()) !== false) {
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
     * class or in any inherited classes, traits or interfaces.
     *
     * @param array<class-string,string|null>|null $classDocComments If provided,
     * `$classDocComments` is populated with one of the following for each doc
     * comment returned:
     * - the doc comment of the method's declaring class, or
     * - `null` if the declaring class has no doc comment
     * @return array<class-string,string>
     */
    public function getAllMethodDocComments(ReflectionMethod $method, ?array &$classDocComments = null): array
    {
        if (func_num_args() > 1) {
            $classDocComments = [];
        }
        $name = $method->getName();
        $comments = $this->_getAllMethodDocComments($method, $name, $classDocComments);

        foreach ($this->getInterfaces($method->getDeclaringClass()) as $interface) {
            if ($interface->hasMethod($name) &&
                    ($comment = $interface->getMethod($name)->getDocComment()) !== false) {
                $class = $interface->getName();
                $comments[$class] = Str::setEol($comment);
                if (!is_null($classDocComments)) {
                    $comment = $interface->getDocComment() ?: null;
                    $classDocComments[$class] = $comment === null ? null : Str::setEol($comment);
                }
            }
        }

        return is_null($classDocComments)
            ? Convert::stringsToUnique($comments)
            : Convert::columnsToUnique($comments, $classDocComments);
    }

    /**
     * @return array<class-string,string>
     */
    private function _getAllMethodDocComments(ReflectionMethod $method, string $name, ?array &$classDocComments): array
    {
        $comments = [];
        do {
            if (($comment = $method->getDocComment()) !== false) {
                $class = $method->getDeclaringClass()->getName();
                $comments[$class] = Str::setEol($comment);
                if (!is_null($classDocComments)) {
                    $comment = $method->getDeclaringClass()->getDocComment() ?: null;
                    $classDocComments[$class] = $comment === null ? null : Str::setEol($comment);
                }
            }
            // Interfaces don't have traits, so there's nothing else to do here
            if ($method->getDeclaringClass()->isInterface()) {
                return $comments;
            }
            // getTraits() doesn't return inherited traits, so recurse into them
            foreach ($method->getDeclaringClass()->getTraits() as $trait) {
                if ($trait->hasMethod($name)) {
                    $comments = array_merge(
                        $comments,
                        $this->_getAllMethodDocComments(
                            $trait->getMethod($name),
                            $name,
                            $classDocComments
                        )
                    );
                }
            }
            if (!($parent = $method->getDeclaringClass()->getParentClass()) ||
                    !$parent->hasMethod($name)) {
                return $comments;
            }
            $method = $parent->getMethod($name);
        } while (true);
    }

    /**
     * Get an array of doc comments for a ReflectionProperty from its declaring
     * class and any ancestors that declare the same property
     *
     * Returns an empty array if no doc comments are found in the declaring
     * class or in any inherited classes or traits.
     *
     * @param array<class-string,string|null>|null $classDocComments If provided,
     * `$classDocComments` is populated with one of the following for each doc
     * comment returned:
     * - the doc comment of the property's declaring class, or
     * - `null` if the declaring class has no doc comment
     * @return array<class-string,string>
     */
    public function getAllPropertyDocComments(ReflectionProperty $property, ?array &$classDocComments = null): array
    {
        if (func_num_args() > 1) {
            $classDocComments = [];
        }
        $name = $property->getName();
        $comments = $this->_getAllPropertyDocComments($property, $name, $classDocComments);

        return is_null($classDocComments)
            ? Convert::stringsToUnique($comments)
            : Convert::columnsToUnique($comments, $classDocComments);
    }

    /**
     * @return array<class-string,string>
     */
    private function _getAllPropertyDocComments(
        ReflectionProperty $property,
        string $name,
        ?array &$classDocComments
    ): array {
        $comments = [];
        do {
            if (($comment = $property->getDocComment()) !== false) {
                $class = $property->getDeclaringClass()->getName();
                $comments[$class] = Str::setEol($comment);
                if (!is_null($classDocComments)) {
                    $comment = $property->getDeclaringClass()->getDocComment() ?: null;
                    $classDocComments[$class] = $comment === null ? null : Str::setEol($comment);
                }
            }
            foreach ($property->getDeclaringClass()->getTraits() as $trait) {
                if ($trait->hasProperty($name)) {
                    $comments = array_merge(
                        $comments,
                        $this->_getAllPropertyDocComments(
                            $trait->getProperty($name),
                            $name,
                            $classDocComments
                        )
                    );
                }
            }
            if (!($parent = $property->getDeclaringClass()->getParentClass()) ||
                    !$parent->hasProperty($name)) {
                return $comments;
            }
            $property = $parent->getProperty($name);
        } while (true);
    }

    /**
     * Convert the given ReflectionType to a PHP type declaration
     *
     * @param ReflectionType|null $type e.g. the return value of
     * `ReflectionParameter::getType()`.
     * @param callable|null $typeNameCallback Applied to qualified class names
     * if set. Must return `null` or an unqualified alias:
     * ```php
     * callback(string $name): ?string
     * ```
     */
    public function getTypeDeclaration(
        ?ReflectionType $type,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null
    ): string {
        $glue = '|';
        if ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();
        } elseif ($type instanceof ReflectionIntersectionType) {
            $glue = '&';
            $types = $type->getTypes();
        } elseif (is_null($type)) {
            $types = [];
        } else {
            $types = [$type];
        }
        $parts = [];
        /** @var ReflectionNamedType|ReflectionType $type */
        foreach ($types as $type) {
            $name = $this->getTypeName($type);
            $alias = $typeNameCallback ? $typeNameCallback($name) : null;
            $parts[] = ($type->allowsNull() && strcasecmp($name, 'null') ? '?' : '')
                . ($alias || $type->isBuiltin() ? '' : $classPrefix)
                . ($alias ?: $name);
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
    public function getParameterDeclaration(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null,
        ?string $type = null,
        ?string $name = null,
        bool $phpDoc = false
    ): string {
        // If getTypeDeclaration isn't called, neither is $typeNameCallback
        $param = $this->getTypeDeclaration($parameter->getType(), $classPrefix, $typeNameCallback);
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
    public function getParameterPhpDoc(
        ReflectionParameter $parameter,
        string $classPrefix = '\\',
        ?callable $typeNameCallback = null,
        ?string $type = null,
        ?string $name = null,
        ?string $documentation = null,
        bool $force = false
    ): ?string {
        // If getTypeDeclaration isn't called, neither is $typeNameCallback
        $param = $this->getTypeDeclaration($parameter->getType(), $classPrefix, $typeNameCallback);
        $param = is_null($type) ? $param : $type;
        $param .= ($param ? ' ' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . ($name ?: $parameter->getName());

        if (!$force && !$documentation &&
                preg_replace(
                    ['/ = .*/', '/&(?=(\.\.\.)?\$)/'],
                    '',
                    $this->getParameterDeclaration($parameter, $classPrefix, $typeNameCallback, null, $name)
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
     * @return array<string,ReflectionClass> An array that maps trait names to
     * `ReflectionClass` instances.
     */
    public function getAllTraits(ReflectionClass $class): array
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
     * @return ReflectionClass[]
     */
    private function getInterfaces(ReflectionClass $class): array
    {
        if (!($interfaces = $class->getInterfaces())) {
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
                        : $this->getBaseClass($a)->getName() <=> $this->getBaseClass($b)->getName())
        );

        return $interfaces;
    }
}
