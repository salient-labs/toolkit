<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concept;

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
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\Support\SyncEntityProvider;
use RuntimeException;
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
     * Class name => entity type ID
     *
     * @var array<string,int>
     */
    private static $TypeId = [];

    /**
     * Class name => [ Property name => normalised property name ]
     *
     * @var array<string,array<string,string>>
     */
    private static $Normalised = [];

    /**
     * Called when the class is registered with an entity store
     *
     * See {@see \Lkrms\Sync\Support\SyncStore::entityType()} for more
     * information.
     *
     * @throws \RuntimeException if the class already has an entity type ID.
     */
    final public static function setEntityTypeId(int $entityTypeId): void
    {
        self::$TypeId[static::class] = $entityTypeId;
    }

    final public static function entityTypeId(): ?int
    {
        return self::$TypeId[static::class] ?? null;
    }

    final public function provider(): ?ISyncProvider
    {
        if (is_null($provider = parent::provider()))
        {
            return null;
        }
        if (!($provider instanceof ISyncProvider))
        {
            throw new RuntimeException(get_class($provider) . " does not implement " . ISyncProvider::class);
        }

        return $provider;
    }

    final protected function context(): ?ISyncContext
    {
        if (is_null($ctx = parent::context()))
        {
            return null;
        }
        if (!($ctx instanceof ISyncContext))
        {
            throw new RuntimeException(get_class($ctx) . " does not implement " . ISyncContext::class);
        }

        return $ctx;
    }

    final protected function requireContext(): ISyncContext
    {
        if (is_null($ctx = $this->context()))
        {
            throw new RuntimeException("Context required");
        }

        return $ctx;
    }

    final public function getResourceName(): string
    {
        return Convert::classToBasename(static::class) . "({$this->Id})";
    }

    final public function getResourcePath(): string
    {
        return str_replace("\\", "/", static::class) . "({$this->Id})";
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
        /** @var ISyncProvider */
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
            fn(string $name): string => Convert::classToBasename($name),
            Reflect::getClassNamesBetween(static::class, self::class, false)
        );
    }

    /**
     * Returns a closure to normalise a property name
     *
     * {@inheritdoc}
     *
     * If `$aggressive` is `false`, prefixes returned by
     * {@see SyncEntity::getRemovablePrefixes()} are not removed from `$name`.
     * Otherwise, if `$hints` are provided and `$name` matches one of them after
     * snake_case conversion, prefix removal is skipped.
     *
     * @return Closure
     * ```php
     * function (string $name, bool $aggressive = true, string ...$hints): string
     * ```
     */
    final public static function getNormaliser(): Closure
    {
        if ($prefixes = static::getRemovablePrefixes())
        {
            $prefixes = array_unique(array_map(
                fn(string $prefix): string => Convert::toSnakeCase($prefix),
                $prefixes
            ));
            $regex = implode("|", $prefixes);
            $regex = count($prefixes) > 1 ? "($regex)" : $regex;
            $regex = "/^{$regex}_/";
            return static function (string $name, bool $aggressive = true, string ...$hints) use ($regex): string
            {
                if ($aggressive && !$hints)
                {
                    return self::$Normalised[static::class][$name]
                        ?? (self::$Normalised[static::class][$name]
                            = preg_replace($regex, "", Convert::toSnakeCase($name)));
                }
                $_name = Convert::toSnakeCase($name);
                if (!$aggressive || in_array($_name, $hints))
                {
                    return $_name;
                }

                return preg_replace($regex, "", $_name);
            };
        }

        return static function (string $name): string
        {
            return self::$Normalised[static::class][$name]
                ?? (self::$Normalised[static::class][$name]
                    = Convert::toSnakeCase($name));
        };
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

    /**
     * Defer instantiation of an entity or list of entities
     *
     * @param int|string|int[]|string[] $deferred An entity ID or list thereof.
     * @param mixed $replace A reference to the variable to replace when the
     * entity or list is resolved. Do not assign anything else to it after
     * calling this method.
     */
    final protected function defer($deferred, &$replace, ?string $entity = null): void
    {
        $ctx = $this->requireContext();
        if (is_array($deferred))
        {
            $ctx = $ctx->withListArrays();
        }

        DeferredSyncEntity::defer($this->provider(), $ctx->push($this), $entity ?: static::class, $deferred, $replace);
    }

    private function getObjectId(): int
    {
        return spl_object_id($this);
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
            $parentArray[$newParentKey] = $node->Id ?? null;
            unset($parentArray[$parentKey]);

            return;
        }

        $node = $node->Id ?? null;
    }

    private function _serialize(&$node, SerializeRules $rules, $parents = [], ?SyncEntity $parentEntity = null, array & $parentArray = null, $parentKey = null)
    {
        if ($node instanceof SyncEntity)
        {
            // If $parentArray is null, $node is the top-level entity
            if ($rules->OnlySerializePlaceholders && !is_null($parentArray))
            {
                $node = $node->toPlaceholder();
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

    private function toPlaceholder(): array
    {
        return ["@id" => $this->getResourcePath()];
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
