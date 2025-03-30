<?php declare(strict_types=1);

namespace Salient\Core\Legacy;

use Salient\Contract\Core\Entity\Extensible;
use Salient\Contract\Core\Entity\Normalisable;
use Salient\Contract\Core\Entity\Providable;
use Salient\Contract\Core\Entity\Readable;
use Salient\Contract\Core\Entity\Relatable;
use Salient\Contract\Core\Entity\Temporal;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Core\Entity\Writable;
use Salient\Utility\Arr;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @internal
 *
 * @template TClass of object
 */
class IntrospectionClass
{
    public const ACTION_GET = 'get';
    public const ACTION_ISSET = 'isset';
    public const ACTION_SET = 'set';
    public const ACTION_UNSET = 'unset';
    public const GREEDY = 1;
    public const LAZY = 2;
    public const CAREFUL = 4;

    /**
     * The name of the class under introspection
     *
     * @var class-string<TClass>
     */
    public $Class;

    /**
     * True if the class implements Readable
     *
     * @var bool
     */
    public $IsReadable;

    /**
     * True if the class implements Writable
     *
     * @var bool
     */
    public $IsWritable;

    /**
     * True if the class implements Extensible
     *
     * @var bool
     */
    public $IsExtensible;

    /**
     * True if the class implements Providable
     *
     * @var bool
     */
    public $IsProvidable;

    /**
     * True if the class implements Relatable
     *
     * @var bool
     */
    public $IsRelatable;

    /**
     * True if the class implements Treeable
     *
     * @var bool
     */
    public $IsTreeable;

    /**
     * True if the class implements Temporal
     *
     * @var bool
     */
    public $HasDates;

    /**
     * Properties (normalised name => declared name)
     *
     * - `public` properties
     * - `protected` properties if the class implements {@see Readable} or
     *   {@see Writable}
     *
     * @var array<string,string>
     */
    public $Properties = [];

    /**
     * Public properties (normalised name => declared name)
     *
     * @var array<string,string>
     */
    public $PublicProperties = [];

    /**
     * Readable properties (normalised name => declared name)
     *
     * Empty if the class does not implement {@see Readable}, otherwise:
     * - `public` properties
     * - `protected` properties returned by
     *   {@see Readable::getReadableProperties()}
     *
     * Does not include "magic" properties.
     *
     * @var array<string,string>
     */
    public $ReadableProperties = [];

    /**
     * Writable properties (normalised name => declared name)
     *
     * Empty if the class does not implement {@see Writable}, otherwise:
     * - `public` properties
     * - `protected` properties returned by
     *   {@see Writable::getWritableProperties()}
     *
     * Does not include "magic" properties.
     *
     * @var array<string,string>
     */
    public $WritableProperties = [];

    /**
     * Set if the class implements Extensible
     */
    public string $DynamicPropertiesProperty;

    /**
     * Set if the class implements Extensible
     */
    public string $DynamicPropertyNamesProperty;

    /**
     * Action => normalised property name => "magic" property method
     *
     * @var array<string,array<string,string>>
     */
    public $Actions = [];

    /**
     * Constructor parameters (normalised name => declared name)
     *
     * @var array<string,string>
     */
    public $Parameters = [];

    /**
     * Parameters that aren't nullable and don't have a default value
     * (normalised name => declared name)
     *
     * @var array<string,string>
     */
    public $RequiredParameters = [];

    /**
     * Parameters that aren't nullable and have a default value (normalised name
     * => declared name)
     *
     * @var array<string,string>
     */
    public $NotNullableParameters = [];

    /**
     * Required parameters with a declared type that can be resolved by a
     * service container (normalised name => class/interface name)
     *
     * @var array<string,string>
     */
    public $ServiceParameters = [];

    /**
     * Parameters to pass by reference (normalised name => declared name)
     *
     * @var array<string,string>
     */
    public $PassByRefParameters = [];

    /**
     * Parameters with a declared type that implements DateTimeInterface
     * (normalised name => declared name)
     *
     * @var array<string,string>
     */
    public $DateParameters = [];

    /**
     * Default values for (all) constructor parameters
     *
     * @var mixed[]
     */
    public $DefaultArguments = [];

    /**
     * Minimum number of arguments required by the constructor
     *
     * @var int
     */
    public $RequiredArguments = 0;

    /**
     * Constructor parameter name => index
     *
     * @var array<string,int>
     */
    public $ParameterIndex = [];

    /**
     * Declared and "magic" properties that are both readable and writable
     *
     * @var string[]
     */
    public $SerializableProperties = [];

    /**
     * Normalised properties (declared and "magic" property names)
     *
     * @var string[]
     */
    public $NormalisedKeys = [];

    /**
     * The normalised parent property
     *
     * `null` if the class does not implement {@see Treeable} or returns an
     * invalid pair of parent and children properties.
     *
     * @var string|null
     */
    public $ParentProperty;

    /**
     * The normalised children property
     *
     * `null` if the class does not implement {@see Treeable} or returns an
     * invalid pair of parent and children properties.
     *
     * @var string|null
     */
    public $ChildrenProperty;

    /**
     * One-to-one relationships between the class and others (normalised
     * property name => target class)
     *
     * @var array<string,class-string<Relatable>>
     */
    public $OneToOneRelationships = [];

    /**
     * One-to-many relationships between the class and others (normalised
     * property name => target class)
     *
     * @var array<string,class-string<Relatable>>
     */
    public $OneToManyRelationships = [];

    /**
     * Normalised date properties (declared and "magic" property names)
     *
     * @var string[]
     */
    public $DateKeys = [];

    /**
     * Normalises property names
     *
     * @var (Closure(string $name, bool $greedy=, string...$hints): string)|null
     */
    public ?Closure $Normaliser = null;

    /**
     * Normalises property names with $greedy = false
     *
     * @var Closure(string): string
     */
    public Closure $GentleNormaliser;

    /**
     * Normalises property names with $hints = $this->NormalisedProperties
     *
     * @var Closure(string): string
     */
    public Closure $CarefulNormaliser;

    /**
     * Signature => closure
     *
     * @var array<string,Closure>
     */
    public $CreateFromSignatureClosures = [];

    /**
     * Signature => (int) $strict => closure
     *
     * @var array<string,array<int,Closure>>
     */
    public $CreateProviderlessFromSignatureClosures = [];

    /**
     * Signature => (int) $strict => closure
     *
     * @var array<string,array<int,Closure>>
     */
    public $CreateProvidableFromSignatureClosures = [];

    /**
     * (int) $strict => closure
     *
     * @var array<int,Closure>
     */
    public $CreateProviderlessFromClosures = [];

    /**
     * (int) $strict => closure
     *
     * @var array<int,Closure>
     */
    public $CreateProvidableFromClosures = [];

    /**
     * Normalised property name => action => closure
     *
     * @var array<string,array<string,Closure>>
     */
    public $PropertyActionClosures = [];

    /** @var Closure|null */
    public $GetNameClosure;

    /**
     * Rules signature => closure
     *
     * @var array<string,Closure>
     */
    public $SerializeClosures = [];

    /** @var ReflectionClass<TClass> */
    protected $Reflector;

    /**
     * @param class-string<TClass> $class
     */
    public function __construct(string $class)
    {
        $class = new ReflectionClass($class);
        $className = $class->getName();
        $this->Reflector = $class;
        $this->Class = $className;
        $this->IsReadable = $class->implementsInterface(Readable::class);
        $this->IsWritable = $class->implementsInterface(Writable::class);
        $this->IsExtensible = $class->implementsInterface(Extensible::class);
        $this->IsProvidable = $class->implementsInterface(Providable::class);
        $this->IsTreeable = $class->implementsInterface(Treeable::class);
        $this->IsRelatable = $this->IsTreeable || $class->implementsInterface(Relatable::class);
        $this->HasDates = $class->implementsInterface(Temporal::class);

        if ($class->implementsInterface(Normalisable::class)) {
            /** @var class-string<Normalisable> $className */
            $this->Normaliser = static fn(string $name, bool $greedy = true, string ...$hints) =>
                $className::normaliseProperty($name, $greedy, ...$hints);
            $this->GentleNormaliser = fn(string $name): string => ($this->Normaliser)($name, false);
            $this->CarefulNormaliser = fn(string $name): string => ($this->Normaliser)($name, true, ...$this->NormalisedKeys);
        }

        $propertyFilter = ReflectionProperty::IS_PUBLIC;
        $methodFilter = 0;
        $reserved = [];

        // Readable and Writable provide access to protected and "magic"
        // property methods
        if ($this->IsReadable || $this->IsWritable) {
            $propertyFilter |= ReflectionProperty::IS_PROTECTED;
            $methodFilter |= ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
        }

        if ($this->IsExtensible) {
            /** @var class-string<Extensible> $className */
            $reserved[] = $this->DynamicPropertiesProperty = $className::getDynamicPropertiesProperty();
            $reserved[] = $this->DynamicPropertyNamesProperty = $className::getDynamicPropertyNamesProperty();
        }

        $parent = $class;
        do {
            $parents[] = $parent->getName();
        } while ($parent = $parent->getParentClass());
        $parents = array_flip($parents);

        // Get instance properties
        $properties = array_filter(
            $class->getProperties($propertyFilter),
            fn(ReflectionProperty $prop) => !$prop->isStatic()
        );
        // Sort by order of declaration, starting with the base class
        uksort(
            $properties,
            function (int $a, int $b) use ($parents, $properties) {
                $depthA = $parents[$properties[$a]->getDeclaringClass()->getName()];
                $depthB = $parents[$properties[$b]->getDeclaringClass()->getName()];

                return $depthB <=> $depthA ?: $a <=> $b;
            }
        );
        $names = Reflect::getNames($properties);
        $this->Properties = array_diff_key(
            Arr::combine(
                $this->maybeNormalise($names, self::LAZY),
                $names
            ),
            array_flip($this->maybeNormalise($reserved, self::LAZY)),
        );
        $this->PublicProperties =
            $propertyFilter & ReflectionProperty::IS_PROTECTED
                ? array_intersect(
                    $this->Properties,
                    Reflect::getNames(array_filter(
                        $properties,
                        fn(ReflectionProperty $prop) => $prop->isPublic()
                    ))
                )
                : $this->Properties;

        if ($this->IsReadable) {
            /** @var class-string<Readable> $className */
            $readable = $className::getReadableProperties();
            $readable = array_merge(
                ['*'] === $readable
                    ? $this->Properties
                    : $readable,
                $this->PublicProperties
            );
            $this->ReadableProperties = array_intersect($this->Properties, $readable);
        }

        if ($this->IsWritable) {
            /** @var class-string<Writable> $className */
            $writable = $className::getWritableProperties();
            $writable = array_merge(
                ['*'] === $writable
                    ? $this->Properties
                    : $writable,
                $this->PublicProperties
            );
            $this->WritableProperties = array_intersect($this->Properties, $writable);
        }

        // Get "magic" property methods, e.g. _get<Property>()
        if ($methodFilter) {
            /** @var ReflectionMethod[] $methods */
            $methods = array_filter(
                $class->getMethods($methodFilter),
                fn(ReflectionMethod $method) => !$method->isStatic()
            );
            $regex = implode('|', [
                ...($this->IsReadable ? [self::ACTION_GET, self::ACTION_ISSET] : []),
                ...($this->IsWritable ? [self::ACTION_SET, self::ACTION_UNSET] : []),
            ]);
            $regex = "/^_(?<action>{$regex})(?<property>.+)\$/i";
            foreach ($methods as $method) {
                if (!Regex::match($regex, $name = $method->getName(), $matches)) {
                    continue;
                }
                $action = Str::lower($matches['action']);
                $property = $this->maybeNormalise($matches['property'], self::LAZY);
                $this->Actions[$action][$property] = $name;
            }
        }

        /**
         * @todo Create a proxy for `protected function __construct()` if the
         * class implements a designated interface, e.g. `IInstantiable`
         */

        // Get constructor parameters
        if (($constructor = $class->getConstructor()) && $constructor->isPublic()) {
            $lastRequired = -1;
            $index = -1;
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                $type = $type instanceof ReflectionNamedType && !$type->isBuiltin()
                    ? $type->getName()
                    : null;
                $normalised = $this->maybeNormalise($name = $param->getName(), self::LAZY);
                $defaultValue = null;
                $isOptional = false;
                if ($param->isOptional()) {
                    if ($param->isDefaultValueAvailable()) {
                        $defaultValue = $param->getDefaultValue();
                    }
                    $isOptional = true;
                    if (!$param->allowsNull()) {
                        $this->NotNullableParameters[$normalised] = $name;
                    }
                } elseif (!$param->allowsNull()) {
                    $this->RequiredParameters[$normalised] = $name;
                    if ($type !== null) {
                        $this->ServiceParameters[$normalised] = $type;
                    }
                }
                $index++;
                if (!$isOptional) {
                    $lastRequired = $index;
                }
                if ($param->isPassedByReference()) {
                    $this->PassByRefParameters[$normalised] = $name;
                }
                if ($type !== null && is_a($type, DateTimeInterface::class, true)) {
                    $this->DateParameters[$normalised] = $name;
                }
                $this->Parameters[$normalised] = $name;
                $this->DefaultArguments[] = $defaultValue;
            }
            $this->RequiredArguments = $lastRequired + 1;
            $this->ParameterIndex = array_flip(array_values($this->Parameters));
        }

        // Create a combined list of normalised property and method names
        $this->NormalisedKeys = array_keys(
            $this->Properties
            + ($this->Actions[self::ACTION_GET] ?? [])
            + ($this->Actions[self::ACTION_ISSET] ?? [])
            + ($this->Actions[self::ACTION_SET] ?? [])
            + ($this->Actions[self::ACTION_UNSET] ?? [])
        );

        // Create a combined list of declared property and normalised method
        // names that are both readable and writable
        $this->SerializableProperties =
            array_merge(
                array_values(
                    array_intersect(
                        ($this->ReadableProperties ?: $this->PublicProperties),
                        ($this->WritableProperties ?: $this->PublicProperties),
                    )
                ),
                array_keys(
                    array_intersect_key(
                        ($this->Actions[self::ACTION_GET] ?? []),
                        ($this->Actions[self::ACTION_SET] ?? []),
                    )
                )
            );

        if ($this->IsRelatable) {
            /** @var class-string<Relatable> $className */
            $relationships = $className::getRelationships();
            $relationships = Arr::combine(
                $this->maybeNormalise(array_keys($relationships), self::LAZY),
                $relationships
            );

            // Create self-referencing parent/child relationships between
            // Treeable classes after identifying the class that declared
            // getParentProperty() and getChildrenProperty(), which is most
            // likely to be the base/service class. If not, explicit
            // relationship declarations take precedence over these.
            if ($this->IsTreeable) {
                $parentMethod = $class->getMethod('getParentProperty');
                $parentClass = $parentMethod->getDeclaringClass();
                $childrenMethod = $class->getMethod('getChildrenProperty');
                $childrenClass = $childrenMethod->getDeclaringClass();

                // If the methods were declared in different classes, choose the
                // least-generic one
                /** @var class-string<Treeable> */
                $service = $childrenClass->isSubclassOf($parentClass)
                    ? $childrenClass->getName()
                    : $parentClass->getName();

                /** @var class-string<Treeable> $className */
                $treeable = [
                    $className::getParentProperty(),
                    $className::getChildrenProperty(),
                ];

                $treeable = array_unique(
                    $this->maybeNormalise($treeable, self::LAZY),
                );

                // Do nothing if, after normalisation, both methods return the
                // same value, or if the values they return don't resolve to
                // serviceable properties
                if (count(array_intersect($this->NormalisedKeys, $treeable)) === 2) {
                    $this->ParentProperty = $treeable[0];
                    $this->ChildrenProperty = $treeable[1];
                    $this->OneToOneRelationships[$this->ParentProperty] = $service;
                    $this->OneToManyRelationships[$this->ChildrenProperty] = $service;
                } else {
                    $this->IsTreeable = false;
                }
            }

            foreach ($relationships as $property => $reference) {
                $type = array_key_first($reference);
                $target = $reference[$type];
                if (!in_array($property, $this->NormalisedKeys, true)) {
                    continue;
                }
                if (!is_a($target, Relatable::class, true)) {
                    continue;
                }
                switch ($type) {
                    case Relatable::ONE_TO_ONE:
                        $this->OneToOneRelationships[$property] = $target;
                        break;

                    case Relatable::ONE_TO_MANY:
                        $this->OneToManyRelationships[$property] = $target;
                        break;
                }
            }
        }

        if ($this->HasDates) {
            /** @var class-string<Temporal> $className */
            $dates = $className::getDateProperties();
        } else {
            $dates = [];
        }

        $nativeDates = [];
        foreach ($properties as $property) {
            if (!$property->hasType()) {
                continue;
            }
            foreach (Reflect::getTypeNames($property->getType()) as $type) {
                if (is_a($type, DateTimeInterface::class, true)) {
                    $nativeDates[] = $property->getName();
                    continue 2;
                }
            }
        }

        $this->DateKeys =
            ['*'] === $dates
                ? ($nativeDates
                    ? $this->maybeNormalise($nativeDates, self::LAZY)
                    : $this->NormalisedKeys)
                : array_intersect(
                    $this->NormalisedKeys,
                    $this->maybeNormalise(array_merge($dates, $nativeDates), self::LAZY),
                );
    }

    /**
     * Normalise strings if the class has a normaliser, otherwise return them
     * as-is
     *
     * @see Normalisable::normalise()
     *
     * @template T of string[]|string
     *
     * @param T $value
     * @param int-mask-of<self::GREEDY|self::LAZY|self::CAREFUL> $flags
     * @return T
     */
    final public function maybeNormalise($value, int $flags = self::GREEDY)
    {
        if (!$this->Normaliser) {
            return $value;
        }
        switch (true) {
            case $flags & self::LAZY:
                $normaliser = $this->GentleNormaliser;
                break;
            case $flags & self::CAREFUL:
                $normaliser = $this->CarefulNormaliser;
                break;
            default:
                $normaliser = $this->Normaliser;
        }
        /** @var T */
        return is_array($value)
            ? array_map($normaliser, $value)
            : ($normaliser)($value);
    }

    /**
     * Get readable properties, including "magic" properties
     *
     * @return string[] Normalised property names
     */
    final public function getReadableProperties(): array
    {
        return array_keys((
            $this->ReadableProperties
                ?: $this->PublicProperties
        ) + ($this->Actions[self::ACTION_GET] ?? []));
    }

    /**
     * Get writable properties, including "magic" properties
     *
     * @return string[] Normalised property names
     */
    final public function getWritableProperties(): array
    {
        return array_keys((
            $this->WritableProperties
                ?: $this->PublicProperties
        ) + ($this->Actions[self::ACTION_SET] ?? []));
    }

    /**
     * True if an action can be performed on a property
     *
     * @param string $property The normalised property name to check
     * @param IntrospectionClass::ACTION_* $action
     */
    final public function propertyActionIsAllowed(string $property, string $action): bool
    {
        switch ($action) {
            case self::ACTION_GET:
            case self::ACTION_ISSET:
                return in_array(
                    $property,
                    $this->getReadableProperties()
                );

            case self::ACTION_SET:
            case self::ACTION_UNSET:
                return in_array(
                    $property,
                    $this->getWritableProperties()
                );

            default:
                return false;
        }
    }
}
