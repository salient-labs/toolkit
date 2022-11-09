<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use DateTimeInterface;
use JsonSerializable;
use Lkrms\Concept\Entity;
use Lkrms\Concern\TProvidable;
use Lkrms\Concern\TReadable;
use Lkrms\Concern\TWritable;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\IProvider;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Reflect;
use Lkrms\Facade\Test;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncClosureBuilder;
use Lkrms\Sync\Support\SyncEntityProvider;
use Lkrms\Sync\Support\SyncSerializeRules as SerializeRules;
use Lkrms\Sync\Support\SyncSerializeRulesBuilder as SerializeRulesBuilder;
use RuntimeException;
use UnexpectedValueException;

/**
 * Represents the state of an entity in an external system
 *
 * The `protected` properties of a {@see SyncEntity} subclass are readable and
 * writable by default. Override {@see TReadable::getReadable()} and/or
 * {@see TWritable::getWritable()} to change this.
 *
 * The following "magic" property methods are discovered automatically and don't
 * need to be returned by {@see TReadable::getReadable()} or
 * {@see TWritable::getWritable()}:
 * - `protected function _get<PropertyName>()`
 * - `protected function _isset<PropertyName>()` (optional; falls back to
 *   `_get<PropertyName>()` if not declared)
 * - `protected function _set<PropertyName>($value)`
 * - `protected function _unset<PropertyName>()` (optional; falls back to
 *   `_set<PropertyName>(null)` if not declared)
 *
 * Instances serialize to associative arrays of accessible properties with
 * snake_case keys. Override {@see SyncEntity::buildSerializeRules()} to specify
 * how nested entities should be serialized.
 *
 */
abstract class SyncEntity extends Entity implements IProvidable, JsonSerializable
{
    use TProvidable
    {
        setProvider as private _setProvider;
        setContext as private _setContext;
    }

    /**
     * The unique identifier assigned to the entity by its provider
     *
     * @var int|string|null
     */
    public $Id;

    /**
     * The unique identifier assigned to the entity by its canonical backend
     *
     * A {@see SyncEntity}'s canonical backend is the provider regarded as the
     * "single source of truth" for its base type and any properties that aren't
     * "owned" by another provider.
     *
     * @var int|string|null
     */
    public $CanonicalId;

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
     * @var ISyncProvider|null
     */
    private $Provider;

    /**
     * @var ISyncContext|null
     */
    private $Context;

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
        return $this->Provider;
    }

    final public function setProvider(IProvider $provider)
    {
        if (!($provider instanceof ISyncProvider))
        {
            throw new RuntimeException(get_class($provider) . " does not implement " . ISyncProvider::class);
        }

        return $this->_setProvider($provider);
    }

    final public function context(): ?ISyncContext
    {
        return $this->Context;
    }

    final public function setContext(?IProvidableContext $ctx)
    {
        if ($ctx && !($ctx instanceof ISyncContext))
        {
            throw new RuntimeException(get_class($ctx) . " does not implement " . ISyncContext::class);
        }

        return $this->_setContext($ctx);
    }

    final protected function requireContext(): ISyncContext
    {
        if (is_null($ctx = $this->context()))
        {
            throw new RuntimeException("Context required");
        }

        return $ctx;
    }

    /**
     * @param int|string|null $id
     */
    final public static function getShortResourceId($id = null): string
    {
        return self::_shortResourceId(static::class, self::getId(...func_get_args()));
    }

    /**
     * @param int|string|null $id
     */
    final public static function getResourceId($id = null): string
    {
        return self::_resourceId(static::class, self::getId(...func_get_args()));
    }

    /**
     * @param int|string|null $id
     * @return int|string|null
     */
    private static function getId($id = null)
    {
        return func_num_args() ? (is_null($id) ? "" : $id) : null;
    }

    final public function shortResourceId(): string
    {
        return self::_shortResourceId($this->service(), $this->id());
    }

    final public function resourceId(): string
    {
        return self::_resourceId($this->service(), $this->id());
    }

    /**
     * @return int|string
     */
    private function id()
    {
        return is_null($this->Id) ? $this->objectId() : $this->Id;
    }

    /**
     * @param int|string|null $id
     */
    private static function _shortResourceId(string $service, $id): string
    {
        return Convert::classToBasename($service) . (is_null($id) ? "" : "($id)");
    }

    /**
     * @param int|string|null $id
     */
    private static function _resourceId(string $service, $id): string
    {
        return str_replace("\\", "/", $service) . (is_null($id) ? "" : "($id)");
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
        $provider = $container->get(SyncClosureBuilder::entityToProvider(static::class));
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
     * Convert the entity to an associative array
     *
     * Nested objects and lists are returned as-is. Only the top-level entity is
     * converted.
     *
     * @internal
     * @see SyncEntity::buildSerializeRules()
     */
    final protected function serialize(SerializeRules $rules = null): array
    {
        $closureBuilder = SyncClosureBuilder::get(static::class);
        $array = $closureBuilder->getSerializeClosure($rules)($this);
        if ($rules->getRemoveCanonicalId())
        {
            unset($array[$closureBuilder->maybeNormalise("CanonicalId", false, true)]);
        }

        return $array;
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
    protected function buildSerializeRules(SerializeRulesBuilder $build)
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

    private function objectId(): int
    {
        return spl_object_id($this);
    }

    private function _serializeId(&$node, array $path): void
    {
        if (is_null($node))
        {
            return;
        }

        if (Test::isArrayOf($node, SyncEntity::class, false, true, false, true))
        {
            /** @var SyncEntity $child */
            foreach ($node as &$child)
            {
                $child = $child->Id;
            }

            return;
        }

        if (!($node instanceof SyncEntity))
        {
            throw new UnexpectedValueException("Cannot replace (not a SyncEntity): " . implode(".", $path));
        }

        $node = $node->Id;
    }

    private function _serialize(&$node, array $path, SerializeRules $rules, array $parents = []): void
    {
        if (!is_null($maxDepth = $rules->getMaxDepth()) && count($path) > $maxDepth)
        {
            throw new RuntimeException("In too deep: " . implode(".", $path));
        }

        if ($node instanceof SyncEntity)
        {
            if ($path && $rules->getFlags() & SerializeRules::SYNC_STORE)
            {
                $node = $node->toResourceLink();

                return;
            }

            if ($rules->getDetectRecursion())
            {
                // Prevent infinite recursion by replacing each SyncEntity
                // descended from itself with a resource link
                if ($parents[$node->objectId()] ?? false)
                {
                    $node = $node->toResourceLink();

                    return;
                }
                $parents[$node->objectId()] = true;
            }

            $class = get_class($node);
            $until = parent::class;
            $node  = $node->serialize($rules);
        }

        $delete  = $rules->getRemove($class ?? null, $until ?? null, $path);
        $replace = $rules->getReplace($class ?? null, $until ?? null, $path);

        if ($delete)
        {
            $node = array_diff_key($node, array_flip($delete));
        }
        foreach ($replace as $rule)
        {
            if (is_array($rule))
            {
                $_rule    = $rule;
                $key      = array_shift($rule);
                $newKey   = $key;
                $callback = null;

                while ($rule)
                {
                    if (is_null($arg = array_shift($rule)))
                    {
                        continue;
                    }
                    if (is_int($arg) || is_string($arg))
                    {
                        $newKey = $arg;
                        continue;
                    }
                    if ($arg instanceof Closure)
                    {
                        $callback = $arg;
                        continue;
                    }
                    throw new UnexpectedValueException("Invalid rule: " . var_export($_rule, true));
                }

                if ($key !== $newKey && array_key_exists($key, $node))
                {
                    if (array_key_exists($newKey, $node))
                    {
                        throw new UnexpectedValueException("Cannot rename '$key': '$newKey' already set");
                    }
                    Convert::arraySpliceAtKey($node, $key, 1, [$newKey => $node[$key]]);
                    $key = $newKey;
                }

                if ($callback && array_key_exists($key, $node))
                {
                    $node[$key] = $callback($node[$key]);

                    continue;
                }
            }
            else
            {
                $key = $rule;
            }

            $_path   = $path;
            $_path[] = $key;
            $this->_serializeId($node[$key], $_path);
        }

        if (is_array($node))
        {
            if (Test::isIndexedArray($node))
            {
                $isList  = true;
                $lastKey = array_pop($path);
                $path[]  = $lastKey . "[]";
            }
            foreach ($node as $key => & $child)
            {
                if (is_null($child) || is_scalar($child))
                {
                    continue;
                }
                if (!($isList ?? null))
                {
                    $_path   = $path;
                    $_path[] = $key;
                }
                $this->_serialize($child, $_path ?? $path, $rules, $parents);
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

    private function toResourceLink(): array
    {
        return ["@id" => $this->resourceId()];
    }

    final protected function getSerializeRules(): SerializeRules
    {
        return SerializeRules::resolve(
            $this->buildSerializeRules(SerializeRulesBuilder::entity(static::class))
        );
    }

    /**
     * Serialize the entity and any nested entities
     *
     * The entity's {@see SerializeRules} are applied to each `SyncEntity`
     * encountered during this recursive operation.
     *
     * @see SyncEntity::buildSerializeRules()
     */
    final public function toArray(): array
    {
        return $this->_toArray($this->getSerializeRules());
    }

    private function _toArray(SerializeRules $rules): array
    {
        $array = $this;
        $this->_serialize($array, [], $rules);
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
