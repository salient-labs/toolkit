<?php declare(strict_types=1);

namespace Salient\Core\Reflection;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Contract\Core\Entity\Normalisable;
use Salient\Contract\Core\Entity\Providable;
use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Entity\Relatable;
use Salient\Contract\Core\Entity\Temporal;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Core\Entity\Writable;
use Salient\Contract\Core\Hierarchical;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @api
 *
 * @template T of object
 *
 * @extends ReflectionClass<T>
 */
class ClassReflection extends ReflectionClass
{
    private ?bool $HasNormaliser = null;
    /** @var Closure(string $name, bool $fromData=): string */
    private Closure $Normaliser;
    /** @var list<string> */
    private array $DeclaredNames;

    /**
     * @inheritDoc
     */
    public function getConstructor(): ?MethodReflection
    {
        $method = parent::getConstructor();
        return $method
            ? new MethodReflection($this->name, $method->name)
            : null;
    }

    /**
     * @inheritDoc
     */
    public function getMethod($name): MethodReflection
    {
        return new MethodReflection($this->name, $name);
    }

    /**
     * @return MethodReflection[]
     */
    public function getMethods($filter = null): array
    {
        foreach (parent::getMethods($filter) as $method) {
            $methods[] = new MethodReflection($this->name, $method->name);
        }
        return $methods ?? [];
    }

    /**
     * Check if the class is hierarchical
     */
    public function isHierarchical(): bool
    {
        return $this->implementsInterface(Hierarchical::class);
    }

    /**
     * Check if the class has readable properties
     */
    public function isReadable(): bool
    {
        return $this->implementsInterface(Readable::class);
    }

    /**
     * Check if the class has writable properties
     */
    public function isWritable(): bool
    {
        return $this->implementsInterface(Writable::class);
    }

    /**
     * Check if the class has dynamic properties
     *
     * @phpstan-assert-if-true !null $this->getDynamicPropertiesProperty()
     * @phpstan-assert-if-true !null $this->getDynamicPropertyNamesProperty()
     */
    public function isExtensible(): bool
    {
        return $this->implementsInterface(Extensible::class);
    }

    /**
     * Check if the class can normalise property names
     *
     * @phpstan-assert-if-true !null $this->getNormaliser()
     */
    public function isNormalisable(): bool
    {
        return $this->implementsInterface(Normalisable::class);
    }

    /**
     * Check if the class can be serviced by a provider
     */
    public function isProvidable(): bool
    {
        return $this->implementsInterface(Providable::class);
    }

    /**
     * Check if the class has relationships
     */
    public function isRelatable(): bool
    {
        return $this->implementsInterface(Relatable::class);
    }

    /**
     * Check if the class has parent/children properties
     *
     * @phpstan-assert-if-true true $this->isHierarchical()
     * @phpstan-assert-if-true true $this->isRelatable()
     * @phpstan-assert-if-true !null $this->getParentProperty()
     * @phpstan-assert-if-true !null $this->getChildrenProperty()
     */
    public function isTreeable(): bool
    {
        return $this->implementsInterface(Treeable::class);
    }

    /**
     * Check if the class has date properties
     */
    public function isTemporal(): bool
    {
        return $this->implementsInterface(Temporal::class);
    }

    /**
     * Get the property that stores dynamic properties, or null if the class
     * does not have dynamic properties
     */
    public function getDynamicPropertiesProperty(): ?string
    {
        /** @var string|null */
        return $this->isExtensible()
            ? $this->getMethod('getDynamicPropertiesProperty')->invoke(null)
            : null;
    }

    /**
     * Get the property that stores dynamic property names, or null if the class
     * does not have dynamic properties
     */
    public function getDynamicPropertyNamesProperty(): ?string
    {
        /** @var string|null */
        return $this->isExtensible()
            ? $this->getMethod('getDynamicPropertyNamesProperty')->invoke(null)
            : null;
    }

    /**
     * Get the property that links children to a parent of the same type, or
     * null if the class does not have parent/children properties
     */
    public function getParentProperty(): ?string
    {
        /** @var string|null */
        return $this->isTreeable()
            ? $this->getMethod('getParentProperty')->invoke(null)
            : null;
    }

    /**
     * Get the property that links a parent to children of the same type, or
     * null if the class does not have parent/children properties
     */
    public function getChildrenProperty(): ?string
    {
        /** @var string|null */
        return $this->isTreeable()
            ? $this->getMethod('getChildrenProperty')->invoke(null)
            : null;
    }

    /**
     * Normalise the given property name or names
     *
     * If the class doesn't implement {@see Normalisable}, `$name` is returned
     * unchanged.
     *
     * @param string[]|string $name
     * @return ($name is string[] ? string[] : string)
     */
    public function normalise($name, bool $fromData = true)
    {
        $normaliser = $this->HasNormaliser
            ? $this->Normaliser
            : ($this->HasNormaliser === false
                ? null
                : $this->getNormaliser());

        if (!$normaliser) {
            return $name;
        }
        if (is_string($name)) {
            return $normaliser($name, $fromData);
        }
        foreach ($name as $key => $name) {
            $result[$key] = $normaliser($name, $fromData);
        }
        return $result ?? [];
    }

    /**
     * Get a closure that normalises property names, or null if the class does
     * not normalise property names
     *
     * @return (Closure(string $name, bool $fromData=): string)|null
     */
    public function getNormaliser(): ?Closure
    {
        if ($this->HasNormaliser) {
            return $this->Normaliser;
        }
        if ($this->HasNormaliser === false) {
            return null;
        }
        if (!$this->isNormalisable()) {
            $this->HasNormaliser = false;
            return null;
        }
        $closure = $this->getMethod('normaliseProperty')->getClosure(null);
        $this->Normaliser = fn(string $name, bool $fromData = true) =>
            $fromData
                ? $closure($name, true, ...($this->DeclaredNames ?? $this->getDeclaredNames()))
                : $closure($name, false);
        $this->HasNormaliser = true;
        return $this->Normaliser;
    }

    /**
     * Get normalised names for the declared and "magic" properties of the class
     * that are readable or writable
     *
     * @return list<string>
     */
    public function getDeclaredNames(): array
    {
        return $this->DeclaredNames ??= array_keys(
            $this->getReadableProperties()
            + $this->getWritableProperties()
            + $this->getActionProperties()
        );
    }

    /**
     * Get normalised names for the declared and "magic" properties of the class
     * that are both readable and writable
     *
     * @return list<string>
     */
    public function getSerializableNames(): array
    {
        return array_keys(
            array_intersect_key(
                $this->getReadableProperties() + $this->getActionProperties('get'),
                $this->getWritableProperties() + $this->getActionProperties('set'),
            )
        );
    }

    /**
     * Get normalised names for the declared and "magic" properties of the class
     * that are writable
     *
     * @return list<string>
     */
    public function getWritableNames(): array
    {
        return array_keys(
            $this->getWritableProperties() + $this->getActionProperties('set')
        );
    }

    /**
     * Get an array that maps normalised names to declared names for accessible
     * properties
     *
     * Returns names of public properties and any protected properties covered
     * by {@see Readable::getReadableProperties()} or
     * {@see Writable::getWritableProperties()}, if applicable.
     *
     * @return array<string,string>
     */
    public function getAccessiblePropertyNames(): array
    {
        return Reflect::getNames($this->getAccessibleProperties());
    }

    /**
     * Get an array that maps normalised names to declared names for readable
     * properties
     *
     * Returns names of public properties and any protected properties covered
     * by {@see Readable::getReadableProperties()}, if applicable.
     *
     * @return array<string,string>
     */
    public function getReadablePropertyNames(): array
    {
        return Reflect::getNames($this->getReadableProperties());
    }

    /**
     * Get an array that maps normalised names to declared names for writable
     * properties
     *
     * Returns names of public properties and any protected properties covered
     * by {@see Writable::getWritableProperties()}, if applicable.
     *
     * @return array<string,string>
     */
    public function getWritablePropertyNames(): array
    {
        return Reflect::getNames($this->getWritableProperties());
    }

    /**
     * Get accessible properties, indexed by normalised name
     *
     * Returns public properties and any protected properties covered by
     * {@see Readable::getReadableProperties()} or
     * {@see Writable::getWritableProperties()}, if applicable.
     *
     * @return array<string,ReflectionProperty>
     */
    public function getAccessibleProperties(): array
    {
        return $this->filterProperties($this->isReadable(), 'getReadableProperties')
            + $this->filterProperties($this->isWritable(), 'getWritableProperties');
    }

    /**
     * Get readable properties, indexed by normalised name
     *
     * Returns public properties and any protected properties covered by
     * {@see Readable::getReadableProperties()}, if applicable.
     *
     * @return array<string,ReflectionProperty>
     */
    public function getReadableProperties(): array
    {
        return $this->filterProperties($this->isReadable(), 'getReadableProperties');
    }

    /**
     * Get writable properties, indexed by normalised name
     *
     * Returns public properties and any protected properties covered by
     * {@see Writable::getWritableProperties()}, if applicable.
     *
     * @return array<string,ReflectionProperty>
     */
    public function getWritableProperties(): array
    {
        return $this->filterProperties($this->isWritable(), 'getWritableProperties');
    }

    /**
     * @return array<string,ReflectionProperty>
     */
    private function filterProperties(bool $protected, string $listMethod): array
    {
        $filter = ReflectionProperty::IS_PUBLIC;
        if ($protected) {
            $filter |= ReflectionProperty::IS_PROTECTED;
            /** @var string[] */
            $list = $this->getMethod($listMethod)->invoke(null);
        }
        $reserved = array_fill_keys($this->getReservedProperties(), true);
        $normaliser = $this->getNormaliser();
        foreach ($this->getProperties($filter) as $property) {
            if (
                !$property->isStatic()
                && !($reserved[$property->name] ?? null)
                && (
                    !$protected
                    || $list === ['*']
                    || $property->isPublic()
                    || in_array($property->name, $list, true)
                )
            ) {
                $name = $normaliser
                    ? $normaliser($property->name, false)
                    : $property->name;
                $properties[$name] = $property;
            }
        }
        return $properties ?? [];
    }

    /**
     * Get "magic" properties, indexed by action and normalised property name
     *
     * @return array<"get"|"isset"|"set"|"unset",array<string,MethodReflection>>
     */
    public function getPropertyActions(): array
    {
        $regex = [];
        if ($this->isReadable()) {
            $regex[] = 'get';
            $regex[] = 'isset';
        }
        if ($this->isWritable()) {
            $regex[] = 'set';
            $regex[] = 'unset';
        }
        if (!$regex) {
            return [];
        }
        $regex = '/^_(?<action>' . implode('|', $regex) . ')(?<property>.+)$/i';
        $filter = MethodReflection::IS_PUBLIC | MethodReflection::IS_PROTECTED;
        if ($reserved = $this->getReservedProperties()) {
            $reserved = array_fill_keys($this->normalise($reserved, false), true);
        }
        $normaliser = $this->getNormaliser();
        foreach ($this->getMethods($filter) as $method) {
            if (
                !$method->isStatic()
                && Regex::match($regex, $method->name, $matches)
            ) {
                $property = $normaliser
                    ? $normaliser($matches['property'], false)
                    : $matches['property'];
                if (!($reserved[$property] ?? null)) {
                    /** @var "get"|"isset"|"set"|"unset" */
                    $action = Str::lower($matches['action']);
                    $actions[$action][$property] = $method;
                }
            }
        }
        return $actions ?? [];
    }

    /**
     * Get "magic" properties, indexed by normalised property name and action
     *
     * If no actions are given, properties are returned for all actions.
     *
     * @param "get"|"isset"|"set"|"unset" ...$action
     * @return array<string,array<"get"|"isset"|"set"|"unset",MethodReflection>>
     */
    public function getActionProperties(string ...$action): array
    {
        $actions = $this->getPropertyActions();
        if ($action) {
            $actions = array_intersect_key($actions, array_fill_keys($action, true));
        }
        foreach ($actions as $action => $methods) {
            foreach ($methods as $property => $method) {
                $properties[$property][$action] = $method;
            }
        }
        return $properties ?? [];
    }

    /**
     * Get normalised names for the declared and "magic" properties of the class
     * that accept date values
     *
     * @return list<string>
     */
    public function getDateNames(): array
    {
        /** @var string[] */
        $properties = $this->isTemporal()
            ? $this->getMethod('getDateProperties')->invoke(null)
            : [];
        $readable = $this->getReadableProperties();
        $writable = $this->getWritableProperties();
        $readOnly = array_diff_key($readable, $writable);
        $names = [];
        foreach ($readable + $writable as $name => $property) {
            if (
                ($type = $property->getType())
                && $type instanceof ReflectionNamedType
                // Allow `DateTimeInterface` out, require `DateTimeImmutable` in
                && ((
                    ($isReadOnly = isset($readOnly[$name]))
                    && is_a($type->getName(), DateTimeInterface::class, true)
                ) || (
                    !$isReadOnly && (
                        !strcmp($typeName = $type->getName(), DateTimeImmutable::class)
                        || is_a(DateTimeImmutable::class, $typeName, true)
                    )
                ))
            ) {
                $names[$name] = $property->name;
            }
        }
        // Remove native date properties from `$properties` and normalise the
        // rest so they can be matched with "magic" properties
        if (
            $properties
            && $properties !== ['*']
            && ($properties = array_diff($properties, $names))
        ) {
            $properties = $this->normalise($properties, false);
        }
        foreach ($this->getActionProperties('get', 'set') as $name => $actions) {
            // Allow `DateTimeInterface` out, require `DateTimeImmutable` in
            if ((
                !isset($actions['get'])
                || $actions['get']->returns(DateTimeInterface::class)
            ) && (
                !isset($actions['set'])
                || $actions['set']->accepts(DateTimeImmutable::class)
            )) {
                $names[$name] = $name;
            }
        }
        if ($names) {
            $names = array_keys($names);
        }
        if ($properties === ['*']) {
            return $names
                ? $names
                : $this->getDeclaredNames();
        }
        if (
            $properties
            && ($properties = array_diff($properties, $names))
            && ($properties = array_intersect($properties, $this->getDeclaredNames()))
        ) {
            return array_merge($names, array_values($properties));
        }
        return $names;
    }

    /**
     * Get property relationships, indexed by normalised property name
     *
     * @return array<string,PropertyRelationship>
     */
    public function getPropertyRelationships(): array
    {
        if (!$this->isRelatable()) {
            return [];
        }
        $normaliser = $this->getNormaliser();
        $declared = array_fill_keys($this->getDeclaredNames(), true);
        // Create self-referencing parent/child relationships for `Treeable`
        // classes by targeting the least-generic parent that declared
        // `getParentProperty()` or `getChildrenProperty()`
        if ($this->isTreeable()) {
            $class = $this->getMethod('getParentProperty')->getDeclaringClass();
            $class2 = $this->getMethod('getChildrenProperty')->getDeclaringClass();
            if ($class2->isSubclassOf($class)) {
                $class = $class2;
            }
            if (!$class->implementsInterface(Treeable::class)) {
                $class = $this;
                while (
                    ($parent = $class->getParentClass())
                    && $parent->implementsInterface(Treeable::class)
                ) {
                    $class = $parent;
                }
            }
            /** @var class-string<Treeable> */
            $target = $class->name;
            foreach ([
                [$this->getParentProperty(), Relatable::ONE_TO_ONE],
                [$this->getChildrenProperty(), Relatable::ONE_TO_MANY],
            ] as [$property, $type]) {
                $name = $normaliser
                    ? $normaliser($property, false)
                    : $property;
                $relationships[$name] = new PropertyRelationship($property, $type, $target);
            }
            $relationships = array_intersect_key($relationships, $declared);
            if (count($relationships) !== 2) {
                throw new ReflectionException(sprintf(
                    '%s did not return valid parent/children properties',
                    $this->name,
                ));
            }
        }
        /** @var array<string,non-empty-array<Relatable::*,class-string<Relatable>>> */
        $references = $this->getMethod('getRelationships')->invoke(null);
        foreach ($references as $property => $reference) {
            $name = $normaliser
                ? $normaliser($property, false)
                : $property;
            if ($declared[$name] ?? null) {
                $type = array_key_first($reference);
                $target = $reference[$type];
                $relationships[$name] = new PropertyRelationship($property, $type, $target);
            }
        }
        return $relationships ?? [];
    }

    /**
     * @return string[]
     */
    private function getReservedProperties(): array
    {
        if ($this->isExtensible()) {
            $reserved[] = $this->getDynamicPropertiesProperty();
            $reserved[] = $this->getDynamicPropertyNamesProperty();
        }
        return $reserved ?? [];
    }
}
