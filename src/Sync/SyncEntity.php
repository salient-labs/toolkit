<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use DateTimeInterface;
use JsonSerializable;
use Lkrms\Contract\IClassCache;
use Lkrms\Concept\ProviderEntity;
use Lkrms\Concern\TClassCache;
use Lkrms\Support\ClosureBuilder;
use Lkrms\Sync\Provider\SyncEntityProvider;
use Lkrms\Util\Convert;
use Lkrms\Util\Reflect;
use UnexpectedValueException;

/**
 * Represents the state of an entity in an external system
 *
 * By default:
 * - All `protected` properties are gettable and settable.
 *
 *   To change this, override {@see \Lkrms\Concern\TReadable::getGettable()}
 *   and/or {@see \Lkrms\Concern\TSettable::getSettable()}.
 *
 * - {@see SyncEntity::serialize()} returns an associative array of `public`
 *   property values.
 *
 * - No `DoNotSerialize` or `OnlySerializeId` rules are applied. See
 *   {@see SyncEntity::getDoNotSerialize()} and
 *   {@see SyncEntity::getOnlySerializeId()} for more information.
 *
 * - {@see SyncEntity::getSerializedIdKey()} appends `_id` to the names of
 *   fields replaced with their {@see SyncEntity::$Id} during serialization.
 *
 */
abstract class SyncEntity extends ProviderEntity implements IClassCache, JsonSerializable
{
    use TClassCache;

    /**
     * @var int|string|null
     */
    public $Id;

    /**
     * @var array<string,array<string,int>>
     */
    private $DoNotSerialize;

    /**
     * @var array<string,string[]>
     */
    private $OnlySerializeId;

    /**
     * @var bool
     */
    private $DetectRecursion;

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
                $a->getProvider() === $b->getProvider() &&
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
     * Instantiable entity names are removed by default, e.g. for an `AdminUser`
     * class that extends a {@see SyncEntity} subclass called `User`, prefixes
     * "AdminUser" and "User" are removed to ensure fields like "USER_ID" and
     * "ADMIN_USER_FULL_NAME" match with properties like "Id" and "FullName",
     * but if `User` were an `abstract` class, the only prefix removed would be
     * "AdminUser", because classes that can't be instantiated are ignored.
     *
     * Return `null` to suppress prefix removal.
     *
     * @return string[]|null
     */
    protected static function getRemovablePrefixes(): ?array
    {
        return array_map(
            function (string $name) { return Convert::classToBasename($name); },
            Reflect::getClassNamesBetween(static::class, self::class, true)
        );
    }

    final public static function normaliseProperty(string $name): string
    {
        if (!($closure = self::getClassCache(__METHOD__)))
        {
            if ($prefixes = static::getRemovablePrefixes())
            {
                $prefixes = array_unique(array_map(
                    function (string $prefix) { return Convert::toCamelCase($prefix); },
                    $prefixes
                ));
                $regex   = implode("|", $prefixes);
                $regex   = count($prefixes) > 1 ? "($regex)" : $regex;
                $regex   = "/^{$regex}_/";
                $closure = function (string $name) use ($regex)
                {
                    return preg_replace($regex, "", Convert::toSnakeCase($name));
                };
            }
            else
            {
                $closure = function (string $name) { return Convert::toSnakeCase($name); };
            }

            self::setClassCache(__METHOD__, $closure);
        }

        return $closure($name);
    }

    /**
     * Convert the entity to an export-ready associative array
     *
     * Nested objects and lists must be returned as-is. Don't serialize anything
     * except the entity itself.
     *
     * @return array
     * @see SyncEntity::getDoNotSerialize()
     * @see SyncEntity::getOnlySerializeId()
     */
    protected function serialize(): array
    {
        return (ClosureBuilder::get(static::class)->getSerializeClosure())($this);
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
     * @return null|array
     * @see SyncEntity::getOnlySerializeId()
     * @see SyncEntity::serialize()
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
     * @return null|array
     * @see SyncEntity::getSerializedIdKey()
     * @see SyncEntity::getDoNotSerialize()
     * @see SyncEntity::serialize()
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
     * @param string $key The key of the object being replaced in the entity's
     * serialized form.
     * @return string
     * @see SyncEntity::serialize()
     * @see SyncEntity::getOnlySerializeId()
     */
    protected function getSerializedIdKey(string $key): string
    {
        return $key . "_id";
    }

    private function getInstanceKey(): string
    {
        return static::class . "::{$this->Id}";
    }

    private function _serializeId(
        &$node,
        ?SyncEntity $parentEntity,
        array & $parentArray,
        $parentKey
    ) {
        // Rename $node to `<parent_key>_id` or similar if:
        // - its parent was a SyncEntity ($parentEntity)
        // - $parentEntity->getSerializedIdKey($parentKey) returns a new name
        // - the new name hasn't already been used in $parentArray
        if (!is_null($parentEntity) &&
            ($newParentKey = $parentEntity->getSerializedIdKey($parentKey)) &&
            $newParentKey != $parentKey)
        {
            if (array_key_exists($newParentKey, $parentArray))
            {
                throw new UnexpectedValueException("Array key '$newParentKey' already exists");
            }

            $parentArray[$newParentKey] = $node->Id;
            unset($parentArray[$parentKey]);
        }

        $node = $node->Id;
    }

    private function _serialize(
        &$node,
        SyncEntity $root,
        $parents = [],
        SyncEntity $parentEntity = null,
        array & $parentArray     = null,
        $parentKey               = null
    ) {
        $entityNode = null;

        if ($node instanceof SyncEntity)
        {
            $entityNode = $node;

            // Prevent recursion by replacing each $node descended from itself
            // with $node->Id
            if ($root->DetectRecursion && ($parents[$node->getInstanceKey()] ?? false))
            {
                $this->_serializeId($node, $parentEntity, $parentArray, $parentKey);
            }
            else
            {
                if ($root->DetectRecursion)
                {
                    $parents[$node->getInstanceKey()] = true;
                }

                $delete = $replace = [];
                $class  = get_class($node);

                do
                {
                    if ($keys = $root->DoNotSerialize[$class] ?? null)
                    {
                        array_push($delete, ...$keys);
                    }

                    if ($keys = $root->OnlySerializeId[$class] ?? null)
                    {
                        array_push($replace, ...$keys);
                    }
                }
                while (($class = get_parent_class($class)) && $class != SyncEntity::class);

                if ($delete)
                {
                    $node = array_diff_key($node->serialize(), array_flip($delete));
                }
                else
                {
                    $node = $node->serialize();
                }

                foreach ($replace as $key)
                {
                    $this->_serializeId($node[$key], $entityNode, $node, $key);
                }
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

                $this->_serialize($child, $root, $parents, $entityNode, $node, $key);
            }
        }
        elseif ($node instanceof DateTimeInterface)
        {
            $node = $this->getProvider()->getDateFormatter()->format($node);
        }
        else
        {
            throw new UnexpectedValueException("Array or SyncEntity expected: " . print_r($node, true));
        }
    }

    /**
     * Serialize the entity and its nested entities
     *
     * The entity's `DoNotSerialize` and `OnlySerializeId` rules are applied to
     * each `SyncEntity` encountered during this recursive operation.
     *
     * @return array
     * @see SyncEntity::serialize()
     * @see SyncEntity::getDoNotSerialize()
     * @see SyncEntity::getOnlySerializeId()
     */
    final public function toArray(): array
    {
        $this->DoNotSerialize  = $this->getDoNotSerialize() ?: [];
        $this->OnlySerializeId = $this->getOnlySerializeId() ?: [];
        $this->DetectRecursion = !$this->DoNotSerialize && !$this->OnlySerializeId;

        $array = $this;
        $this->_serialize($array, $this);

        return (array)$array;
    }

    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
