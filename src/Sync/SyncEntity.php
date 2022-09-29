<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Closure;
use DateTimeInterface;
use JsonSerializable;
use Lkrms\Concept\ProviderEntity;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\Support\SerializeRules;
use Lkrms\Support\SerializeRulesBuilder;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Provider\SyncEntityProvider;
use Lkrms\Sync\Support\SyncClosureBuilder;
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

    /**
     * @internal
     */
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
     * Return an entity-agnostic interface to the SyncEntity's current provider
     *
     * @return SyncEntityProvider
     */
    final public static function backend(?IContainer $container = null): SyncEntityProvider
    {
        $container = Container::coalesce($container, false, false);
        /** @var SyncProvider */
        $provider = $container->get(static::class . "Provider");
        return $provider->with(static::class, $container);
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

    final public static function getNormaliser(): Closure
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
     * @see SyncEntity::_getSerializeRules()
     */
    final protected function serialize(): array
    {
        return (SyncClosureBuilder::get(static::class)->getSerializeClosure())($this);
    }

    /**
     * Specify how nested objects should be serialized
     *
     * To prevent infinite recursion when `json_encode()` or similar is used to
     * serialize instances of the class, return a {@see SerializeRules} object
     * configured to remove or replace values that add circular references to
     * the object graph.
     *
     * @return SerializeRules|SerializeRulesBuilder
     */
    protected function _getSerializeRules(SerializeRulesBuilder $build)
    {
        return $build->go();
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
        // - `($rules->IdKeyCallback)($parentKey)` returns a new key, and
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
            // If $parentArray is null, $node is the top-level entity
            if ($rules->OnlySerializePlaceholders && !is_null($parentArray))
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

    final protected function getSerializeRules(): SerializeRules
    {
        $rules = $this->_getSerializeRules(SerializeRules::build());

        if ($rules instanceof SerializeRulesBuilder)
        {
            return $rules->go();
        }

        return $rules;
    }

    /**
     * Serialize the entity and any nested entities
     *
     * The entity's {@see SerializeRules} are applied to each `SyncEntity`
     * encountered during this recursive operation.
     *
     * @see SyncEntity::serialize()
     * @see SyncEntity::_getSerializeRules()
     */
    final public function toArray(): array
    {
        return $this->_toArray($this->getSerializeRules());
    }

    private function _toArray(SerializeRules $rules): array
    {
        $array = $this;
        $this->_serialize($array, $rules);
        return (array)$array;
    }

    /**
     * Serialize the entity and any nested entities, overriding the default
     * SerializeRules
     *
     */
    final public function toCustomArray(SerializeRules $rules): array
    {
        return $this->_toArray($rules);
    }

    /**
     * @internal
     */
    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }

}
