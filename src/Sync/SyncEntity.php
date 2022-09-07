<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Closure;
use DateTimeInterface;
use JsonSerializable;
use Lkrms\Concept\ProviderEntity;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Support\SerializeRules;
use Lkrms\Sync\Provider\SyncEntityProvider;
use UnexpectedValueException;

/**
 * Represents the state of an entity in an external system
 *
 * By default:
 * - All `protected` properties are readable and writable.
 *
 *   To change this, override {@see \Lkrms\Concern\TReadable::getReadable()}
 *   and/or {@see \Lkrms\Concern\TWritable::getWritable()}.
 *
 * - {@see SyncEntity::serialize()} returns an associative array of `public`
 *   properties with property names converted to snake_case.
 *
 */
abstract class SyncEntity extends ProviderEntity implements JsonSerializable
{
    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var array<string,Closure>
     */
    private static $Normalisers = [];

    /**
     * Class name => [ Property name => normalised property name ]
     *
     * @var array<string,array<string,string>>
     */
    private static $Normalised = [];

    public function __clone()
    {
        parent::__clone();
        $this->Id = null;
    }

    /**
     * Return true if the value of a property is the same between this and
     * another instance of the same class
     *
     * @param string $property
     * @param SyncEntity $entity
     * @return bool
     */
    final public function propertyHasSameValueAs(
        string $property,
        SyncEntity $entity
    ): bool
    {
        // $entity must be an instance of the same class
        if (!is_a($entity, static::class))
        {
            return false;
        }

        $a = $this->$property;
        $b = $entity->$property;
        return $a === $b ||
            ($a instanceof SyncEntity && $b instanceof SyncEntity &&
                get_class($a) == get_class($b) &&
                $a->provider() === $b->provider() &&
                $a->Id === $b->Id);
    }

    /**
     * Return an entity-agnostic interface with the SyncEntity's current
     * provider
     *
     * @return SyncEntityProvider
     */
    final public static function backend(): SyncEntityProvider
    {
        return new SyncEntityProvider(static::class);
    }

    /**
     * Return prefixes to remove when normalising field/property names
     *
     * Entity names are removed by default, e.g. for an `AdminUser` class that
     * extends a {@see SyncEntity} subclass called `User`, prefixes "AdminUser"
     * and "User" are removed to ensure fields like "USER_ID" and
     * "ADMIN_USER_FULL_NAME" match with properties like "Id" and "FullName".
     *
     * Return `null` to suppress prefix removal.
     *
     * @return string[]|null
     */
    protected static function getRemovablePrefixes(): ?array
    {
        return array_map(
            function (string $name) { return Convert::classToBasename($name); },
            Reflect::getClassNamesBetween(static::class, self::class, false)
        );
    }

    final public static function getPropertyNormaliser(): Closure
    {
        if ($closure = self::$Normalisers[static::class] ?? null)
        {
            return $closure;
        }

        if ($prefixes = static::getRemovablePrefixes())
        {
            $prefixes = array_unique(array_map(
                fn(string $prefix) => Convert::toSnakeCase($prefix),
                $prefixes
            ));
            $regex   = implode("|", $prefixes);
            $regex   = count($prefixes) > 1 ? "($regex)" : $regex;
            $regex   = "/^{$regex}_/";
            $closure = static function (string $name) use ($regex)
            {
                return self::$Normalised[static::class][$name]
                    ?? (self::$Normalised[static::class][$name]
                        = preg_replace($regex, "", Convert::toSnakeCase($name)));
            };
        }
        else
        {
            $closure = static function (string $name)
            {
                return self::$Normalised[static::class][$name]
                    ?? (self::$Normalised[static::class][$name]
                        = Convert::toSnakeCase($name));
            };
        }

        return self::$Normalisers[static::class] = $closure;
    }

    /**
     * Convert the entity to an export-ready associative array
     *
     * Nested objects and lists must be returned as-is. Don't serialize anything
     * except the entity itself.
     *
     * @return array
     * @see SyncEntity::getSerializeRules()
     */
    protected function serialize(): array
    {
        return (ClosureBuilder::get(static::class)->getSerializeClosure())($this);
    }

    /**
     * Specify how nested objects should be serialized
     *
     * To prevent infinite recursion when `json_encode()` or similar is used to
     * serialize instances of the class, return a {@see SerializeRules} object
     * configured to remove or replace values that add circular references to
     * the object graph.
     *
     */
    protected function getSerializeRules(): SerializeRules
    {
        $rules = new SerializeRules();
        $rules->DoNotSerialize     = $this->getDoNotSerialize() ?: [];
        $rules->OnlySerializeId    = $this->getOnlySerializeId() ?: [];
        $rules->GetSerializedIdKey = fn(string $key): string => $this->getSerializedIdKey($key);
        $rules->DetectRecursion    = !($rules->DoNotSerialize || $rules->OnlySerializeId);
        return $rules;
    }

    /**
     * Specify keys to remove from the serialized forms of nested entity classes
     *
     * `DoNotSerialize` rules are only applied by the entity being serialized
     * via one of its public methods, e.g. {@see SyncEntity::jsonSerialize()}.
     * Rules for entities nested within the entity being serialized are not
     * considered.
     *
     * For example, a `User` entity that returns an `OrgUnit` entity to
     * serialize could prevent recursion by deleting the `users` key from
     * serialized `OrgUnit` entities as follows:
     *
     * ```php
     * protected function getDoNotSerialize(): ?array
     * {
     *     return [
     *         OrgUnit::class => [
     *             "users",
     *         ],
     *     ];
     * }
     * ```
     *
     * @deprecated Override {@see SyncEntity::getSerializeRules()} instead
     * @return null|array
     */
    protected function getDoNotSerialize(): ?array
    {
        return null;
    }

    /**
     * Specify keys to replace with identifier keys in the serialized forms of
     * nested entity classes
     *
     * `OnlySerializeId` rules are only applied by the entity being serialized
     * via one of its public methods, e.g. {@see SyncEntity::jsonSerialize()}.
     * Rules for entities nested within the entity being serialized are not
     * considered.
     *
     * For example, an `OrgUnit` entity that returns a list of `User` entities
     * to serialize could prevent recursion by replacing the `org_unit` key in
     * serialized `User` entities with an `org_unit_id` key as follows:
     *
     * ```php
     * protected function getOnlySerializeId(): ?array
     * {
     *     return [
     *         User::class => [
     *             "org_unit",
     *         ],
     *     ];
     * }
     * ```
     *
     * @deprecated Override {@see SyncEntity::getSerializeRules()} instead
     * @return null|array
     */
    protected function getOnlySerializeId(): ?array
    {
        return null;
    }

    /**
     * Returns the key to use when a nested object is replaced during
     * serialization
     *
     * The default implementation appends `_id` to the key being replaced.
     * `user`, for example, would be replaced with `user_id`.
     *
     * @deprecated Override {@see SyncEntity::getSerializeRules()} instead
     * @param string $key The key of the object being replaced in the entity's
     * serialized form.
     * @return string
     */
    protected function getSerializedIdKey(string $key): string
    {
        return $key . "_id";
    }

    private function getObjectId(): int
    {
        return spl_object_id($this);
    }

    private function getPlaceholder(): array
    {
        return [
            "@sync.type" => static::class,
            "@sync.id"   => $this->Id,
        ];
    }

    private function _serializeId(&$node, SerializeRules $rules, ?SyncEntity $parentEntity, array & $parentArray, $parentKey)
    {
        // Rename $node to `<parent_key>_id` or similar if:
        // - its original parent was a SyncEntity (it can't belong to a list)
        // - `($rules->GetSerializedIdKey)($parentKey)` returns a new key, and
        // - the new key hasn't already been used in $parentArray
        if (!is_null($parentEntity) &&
            ($newParentKey = ($rules->getSerializedIdKeyCallback())($parentKey)) &&
            $newParentKey != $parentKey)
        {
            if (array_key_exists($newParentKey, $parentArray))
            {
                throw new UnexpectedValueException("Array key '$newParentKey' already exists");
            }
            $parentArray[$newParentKey] = $node->Id;
            unset($parentArray[$parentKey]);
            return;
        }
        $node = $node->Id;
    }

    private function _serialize(&$node, SerializeRules $rules, $parents = [], ?SyncEntity $parentEntity = null, array & $parentArray = null, $parentKey = null)
    {
        if ($node instanceof SyncEntity)
        {
            if ($rules->OnlySerializePlaceholders)
            {
                $node = $this->getPlaceholder();
                return;
            }

            $entity = $node;

            if ($rules->DetectRecursion)
            {
                // Prevent infinite recursion by replacing each $node descended
                // from itself with $node->Id
                if ($parents[$node->getObjectId()] ?? false)
                {
                    $this->_serializeId($node, $rules, $parentEntity, $parentArray, $parentKey);
                    return;
                }
                $parents[$node->getObjectId()] = true;
            }

            $class   = get_class($node);
            $delete  = $rules->getDoNotSerialize($class, parent::class);
            $replace = $rules->getOnlySerializeId($class, parent::class);

            $node = $node->serialize();
            if ($delete)
            {
                $node = array_diff_key($node, array_flip($delete));
            }
            foreach ($replace as $key)
            {
                $this->_serializeId($node[$key], $rules, $entity, $node, $key);
            }
        }

        if (is_array($node))
        {
            foreach ($node as $key => & $child)
            {
                if (is_null($child) || is_scalar($child))
                {
                    continue;
                }

                $this->_serialize($child, $rules, $parents, $entity ?? null, $node, $key);
            }
        }
        elseif ($node instanceof DateTimeInterface)
        {
            $node = $this->provider()->getDateFormatter()->format($node);
        }
        else
        {
            throw new UnexpectedValueException("Array or SyncEntity expected: " . print_r($node, true));
        }
    }

    /**
     * Serialize the entity and its nested entities
     *
     * The entity's {@see SerializeRules} are applied to each `SyncEntity`
     * encountered during this recursive operation.
     *
     * @return array
     * @see SyncEntity::serialize()
     * @see SyncEntity::getSerializeRules()
     */
    final public function toArray(): array
    {
        $rules = $this->getSerializeRules();
        $array = $this;
        $this->_serialize($array, $rules);
        return (array)$array;
    }

    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
