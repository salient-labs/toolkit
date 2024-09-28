<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Container\RequiresContainer;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\HasDescription;
use Salient\Contract\Core\ListConformity;
use Salient\Contract\Core\NormaliserFactory;
use Salient\Contract\Core\NormaliserFlag;
use Salient\Contract\Core\Readable;
use Salient\Contract\Core\TextComparisonAlgorithm as Algorithm;
use Salient\Contract\Core\TextComparisonFlag as Flag;
use Salient\Contract\Core\Writable;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Contract\Sync\DeferredEntityInterface;
use Salient\Contract\Sync\DeferredRelationshipInterface;
use Salient\Contract\Sync\EntityState;
use Salient\Contract\Sync\LinkType;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncSerializeRulesInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Concern\ConstructibleTrait;
use Salient\Core\Concern\ExtensibleTrait;
use Salient\Core\Concern\HasNormaliser;
use Salient\Core\Concern\HasReadableProperties;
use Salient\Core\Concern\HasWritableProperties;
use Salient\Core\Concern\ProvidableTrait;
use Salient\Core\Facade\Sync;
use Salient\Core\AbstractEntity;
use Salient\Core\DateFormatter;
use Salient\Iterator\IterableIterator;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use DateTimeInterface;
use Generator;
use LogicException;
use ReflectionClass;
use UnexpectedValueException;

/**
 * Base class for entities serviced by sync providers
 *
 * {@see AbstractSyncEntity} implements {@see Readable} and {@see Writable}, but
 * `protected` properties are not accessible by default. Override
 * {@see AbstractSyncEntity::getReadableProperties()} and/or
 * {@see AbstractSyncEntity::getWritableProperties()} to change this.
 *
 * The following "magic" property methods are discovered automatically and don't
 * need to be returned by {@see AbstractSyncEntity::getReadableProperties()} or
 * {@see AbstractSyncEntity::getWritableProperties()}:
 * - `protected function _get<PropertyName>()`
 * - `protected function _isset<PropertyName>()` (optional; falls back to
 *   `_get<PropertyName>()` if not declared)
 * - `protected function _set<PropertyName>($value)`
 * - `protected function _unset<PropertyName>()` (optional; falls back to
 *   `_set<PropertyName>(null)` if not declared)
 *
 * Accessible properties are mapped to associative arrays with snake_case keys
 * when {@see AbstractSyncEntity} objects are serialized. Override
 * {@see AbstractSyncEntity::buildSerializeRules()} to provide serialization
 * rules for nested entities.
 */
abstract class AbstractSyncEntity extends AbstractEntity implements SyncEntityInterface, NormaliserFactory
{
    use ConstructibleTrait;
    use HasReadableProperties;
    use HasWritableProperties;
    use ExtensibleTrait;
    /** @use ProvidableTrait<SyncProviderInterface,SyncContextInterface> */
    use ProvidableTrait;
    use HasNormaliser;
    use RequiresContainer;

    /**
     * The unique identifier assigned to the entity by its provider
     *
     * @see SyncEntityInterface::getId()
     *
     * @var int|string|null
     */
    public $Id;

    /**
     * The unique identifier assigned to the entity by its canonical backend
     *
     * @see SyncEntityInterface::canonicalId()
     *
     * @var int|string|null
     */
    public $CanonicalId;

    /** @var SyncProviderInterface|null */
    private $Provider;
    /** @var SyncContextInterface|null */
    private $Context;
    /** @var int-mask-of<EntityState::*> */
    private $State = 0;

    /**
     * Entity => [ property name => normalised property name ]
     *
     * @var array<class-string<AbstractSyncEntity>,array<string,string>>
     */
    private static $NormalisedPropertyMap = [];

    /**
     * @inheritDoc
     */
    public static function getPlural(): ?string
    {
        return Inflect::plural(Get::basename(static::class));
    }

    /**
     * @inheritDoc
     */
    public static function getRelationships(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function getDateProperties(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return
            SyncIntrospector::get(static::class)
                ->getGetNameClosure()($this);
    }

    /**
     * Override to specify how object graphs below entities of this type should
     * be serialized
     *
     * To prevent infinite recursion when entities of this type are serialized,
     * return a {@see SyncSerializeRulesBuilder} object configured to remove or
     * replace circular references.
     *
     * @param SyncSerializeRulesBuilder<static> $rulesB
     * @return SyncSerializeRulesBuilder<static>
     */
    protected static function buildSerializeRules(SyncSerializeRulesBuilder $rulesB): SyncSerializeRulesBuilder
    {
        return $rulesB;
    }

    /**
     * Override to specify prefixes to remove when normalising property names
     *
     * Entity names are removed by default, e.g. for an
     * {@see AbstractSyncEntity} subclass called `User`, "User" is removed to
     * ensure fields like "USER_ID" and "USER_NAME" match properties like "Id"
     * and "Name". For a subclass of `User` called `AdminUser`, both "User" and
     * "AdminUser" are removed.
     *
     * Return `null` to suppress prefix removal.
     *
     * @return string[]|null
     */
    protected static function getRemovablePrefixes(): ?array
    {
        $current = new ReflectionClass(static::class);
        do {
            $prefixes[] = Get::basename($current->getName());
            $current = $current->getParentClass();
        } while ($current && $current->isSubclassOf(self::class));

        return self::expandPrefixes($prefixes);
    }

    // --

    /**
     * @inheritDoc
     */
    final public function getId()
    {
        return $this->Id;
    }

    /**
     * @inheritDoc
     */
    final public function getCanonicalId()
    {
        return $this->CanonicalId;
    }

    /**
     * @inheritDoc
     */
    final public static function getDefaultProvider(ContainerInterface $container): SyncProviderInterface
    {
        return $container->get(SyncUtil::getEntityTypeProvider(static::class, SyncUtil::getStore($container)));
    }

    /**
     * @inheritDoc
     */
    final public static function withDefaultProvider(ContainerInterface $container, ?SyncContextInterface $context = null): SyncEntityProviderInterface
    {
        return static::getDefaultProvider($container)->with(static::class, $context);
    }

    /**
     * @inheritDoc
     */
    final public static function getSerializeRules(): SyncSerializeRulesInterface
    {
        return static::buildSerializeRules(
            SyncSerializeRules::build()
                ->entity(static::class)
        )->build();
    }

    /**
     * Get the serialization rules of the entity's parent class
     *
     * @return SyncSerializeRules<static>|null
     */
    final protected static function getParentSerializeRules(): ?SyncSerializeRules
    {
        $class = get_parent_class(get_called_class());

        if (
            $class === false
            || !is_a($class, self::class, true)
            || $class === self::class
        ) {
            return null;
        }

        /** @var SyncSerializeRules<static> */
        return $class::buildSerializeRules(
            SyncSerializeRules::build()
                ->entity($class)
        )->build();
    }

    /**
     * Get a closure that normalises a property name
     *
     * If {@see AbstractSyncEntity::getRemovablePrefixes()} doesn't return any
     * prefixes, a closure that converts the property name to snake_case is
     * returned.
     *
     * Otherwise, the closure converts the property name to snake_case, and if
     * `$greedy` is `true` (the default) and the property name doesn't match one
     * of the provided `$hints`, prefixes are removed before it is returned.
     *
     * {@see AbstractSyncEntity::getRemovablePrefixes()} should use
     * {@see AbstractSyncEntity::normalisePrefixes()} or
     * {@see AbstractSyncEntity::expandPrefixes()} to normalise prefixes.
     */
    final public static function getNormaliser(): Closure
    {
        // If there aren't any prefixes to remove, return a closure that
        // converts everything to snake_case
        $prefixes = static::getRemovablePrefixes();
        if (!$prefixes) {
            return static function (string $name): string {
                return self::$NormalisedPropertyMap[static::class][$name]
                    ??= Str::snake($name);
            };
        }

        $regex = implode('|', $prefixes);
        $regex = count($prefixes) > 1 ? "(?:$regex)" : $regex;
        $regex = "/^{$regex}_/";

        return static function (
            string $name,
            bool $greedy = true,
            string ...$hints
        ) use ($regex): string {
            if ($greedy && !$hints) {
                return self::$NormalisedPropertyMap[static::class][$name]
                    ??= Regex::replace($regex, '', Str::snake($name));
            }
            $_name = Str::snake($name);
            if (!$greedy || in_array($_name, $hints)) {
                return $_name;
            }

            return Regex::replace($regex, '', $_name);
        };
    }

    /**
     * @inheritDoc
     */
    final public function toArray(?SyncStoreInterface $store = null): array
    {
        /** @var SyncSerializeRulesInterface<self> */
        $rules = static::getSerializeRules();
        return $this->_toArray($rules, $store);
    }

    /**
     * @inheritDoc
     */
    final public function toArrayWith(SyncSerializeRulesInterface $rules, ?SyncStoreInterface $store = null): array
    {
        /** @var SyncSerializeRulesInterface<self> $rules */
        return $this->_toArray($rules, $store);
    }

    /**
     * @inheritDoc
     */
    final public function toLink(?SyncStoreInterface $store = null, int $type = LinkType::DEFAULT, bool $compact = true): array
    {
        switch ($type) {
            case LinkType::DEFAULT:
                return [
                    '@type' => $this->getTypeUri($store, $compact),
                    '@id' => $this->Id === null
                        ? spl_object_id($this)
                        : $this->Id,
                ];

            case LinkType::COMPACT:
                return [
                    '@id' => $this->getUri($store, $compact),
                ];

            case LinkType::FRIENDLY:
                return Arr::whereNotEmpty([
                    '@type' => $this->getTypeUri($store, $compact),
                    '@id' => $this->Id === null
                        ? spl_object_id($this)
                        : $this->Id,
                    '@name' => $this->getName(),
                    '@description' =>
                        $this instanceof HasDescription
                            ? $this->getDescription()
                            : null,
                ]);

            default:
                throw new LogicException("Invalid link type: $type");
        }
    }

    /**
     * @inheritDoc
     */
    final public function getUri(?SyncStoreInterface $store = null, bool $compact = true): string
    {
        return sprintf(
            '%s/%s',
            $this->getTypeUri($store, $compact),
            $this->Id === null
                ? spl_object_id($this)
                : $this->Id
        );
    }

    /**
     * Get the current state of the entity
     *
     * @return int-mask-of<EntityState::*>
     */
    final public function state(): int
    {
        return $this->State;
    }

    /**
     * Convert prefixes to snake_case for removal from property names
     *
     * e.g. `['AdminUserGroup']` becomes `['admin_user_group']`.
     *
     * @param string[] $prefixes
     * @return string[]
     */
    final protected static function normalisePrefixes(array $prefixes): array
    {
        if (!$prefixes) {
            return [];
        }

        return Arr::snakeCase($prefixes);
    }

    /**
     * Convert prefixes to snake_case and expand them for removal from property
     * names
     *
     * e.g. `['AdminUserGroup']` becomes `['admin_user_group', 'user_group',
     * 'group']`.
     *
     * @param string[] $prefixes
     * @return string[]
     */
    final protected static function expandPrefixes(array $prefixes): array
    {
        if (!$prefixes) {
            return [];
        }

        foreach ($prefixes as $prefix) {
            $prefix = Str::snake($prefix);
            $expanded[$prefix] = true;
            $prefix = explode('_', $prefix);
            while (array_shift($prefix)) {
                $expanded[implode('_', $prefix)] = true;
            }
        }

        return array_keys($expanded);
    }

    private function getTypeUri(?SyncStoreInterface $store, bool $compact): string
    {
        /** @var class-string<self> */
        $service = $this->getService();
        $store ??= $this->Provider ? $this->Provider->getStore() : null;
        return SyncUtil::getEntityTypeUri($service, $compact, $store);
    }

    /**
     * @param SyncSerializeRulesInterface<self> $rules
     * @return array<string,mixed>
     */
    private function _toArray(SyncSerializeRulesInterface $rules, ?SyncStoreInterface $store): array
    {
        /** @var SyncSerializeRulesInterface<self> $rules */
        if ($rules->getDateFormatter() === null) {
            $rules = $rules->withDateFormatter(
                $this->Provider
                    ? $this->Provider->getDateFormatter()
                    : new DateFormatter()
            );
        }

        $array = $this;
        $this->_serialize($array, [], $rules, $store);

        return (array) $array;
    }

    /**
     * @param AbstractSyncEntity|DeferredEntityInterface<AbstractSyncEntity>|DeferredRelationshipInterface<AbstractSyncEntity>|DateTimeInterface|mixed[] $node
     * @param string[] $path
     * @param SyncSerializeRulesInterface<self> $rules
     * @param array<int,true> $parents
     */
    private function _serialize(
        &$node,
        array $path,
        SyncSerializeRulesInterface $rules,
        ?SyncStoreInterface $store,
        bool $nodeIsList = false,
        array $parents = []
    ): void {
        $maxDepth = $rules->getMaxDepth();
        if ($maxDepth !== null && count($path) > $maxDepth) {
            throw new UnexpectedValueException('In too deep: ' . implode('.', $path));
        }

        if ($node instanceof DateTimeInterface) {
            /** @var DateFormatterInterface */
            $formatter = $rules->getDateFormatter();
            $node = $formatter->format($node);
            return;
        }

        // Now is not the time to resolve deferred entities
        if ($node instanceof DeferredEntityInterface) {
            $node = $node->toLink(LinkType::DEFAULT);
            return;
        }

        /** @todo Serialize deferred relationships */
        if ($node instanceof DeferredRelationshipInterface) {
            $node = null;
            return;
        }

        if ($node instanceof AbstractSyncEntity) {
            if ($path && $rules->getForSyncStore()) {
                $node = $node->toLink($store, LinkType::DEFAULT);
                return;
            }

            if ($rules->getDetectRecursion()) {
                // Prevent infinite recursion by replacing each sync entity
                // descended from itself with a link
                if ($parents[spl_object_id($node)] ?? false) {
                    $node = $node->toLink($store, LinkType::DEFAULT);
                    $node['@why'] = 'Circular reference detected';
                    return;
                }
                $parents[spl_object_id($node)] = true;
            }

            $class = get_class($node);
            $node = $node->serialize($rules);
        }

        $delete = $rules->getRemovableKeys($class ?? null, null, $path);
        $replace = $rules->getReplaceableKeys($class ?? null, null, $path);

        // Don't delete values returned in both lists
        $delete = array_diff_key($delete, $replace);

        if ($delete) {
            $node = array_diff_key($node, $delete);
        }

        $replace = array_intersect_key($replace, $node + ['[]' => null]);

        foreach ($replace as $key => [$newKey, $closure]) {
            if ($key !== '[]') {
                if ($newKey !== null && $key !== $newKey) {
                    $node = Arr::rename($node, $key, $newKey);
                    $key = $newKey;
                }

                if ($closure) {
                    if ($node[$key] !== null) {
                        $node[$key] = $closure($node[$key], $store);
                    }
                    continue;
                }

                $_path = $path;
                $_path[] = (string) $key;
                $this->_serializeId($node[$key], $_path);
                continue;
            }

            if (!$nodeIsList) {
                continue;
            }

            if ($closure) {
                foreach ($node as &$child) {
                    if ($child !== null) {
                        $child = $closure($child, $store);
                    }
                }
                unset($child);
                continue;
            }

            $_path = $path;
            $last = array_pop($_path) . '[]';
            $_path[] = $last;
            foreach ($node as &$child) {
                $this->_serializeId($child, $_path);
            }
            unset($child);
        }

        $isList = false;
        if (Arr::isIndexed($node)) {
            $isList = true;
            $last = array_pop($path) . '[]';
            $path[] = $last;
        }

        unset($_path);
        foreach ($node as $key => &$child) {
            if ($child === null || is_scalar($child)) {
                continue;
            }
            if (!$isList) {
                $_path = $path;
                $_path[] = (string) $key;
            }
            $this->_serialize(
                $child,
                $_path ?? $path,
                $rules,
                $store,
                $isList,
                $parents,
            );
        }
    }

    /**
     * @param AbstractSyncEntity[]|AbstractSyncEntity|null $node
     * @param string[] $path
     */
    private function _serializeId(&$node, array $path): void
    {
        if ($node === null) {
            return;
        }

        if (Arr::of($node, AbstractSyncEntity::class, true) && Arr::isIndexed($node, true)) {
            /** @var AbstractSyncEntity $child */
            foreach ($node as &$child) {
                $child = $child->Id;
            }

            return;
        }

        if (!$node instanceof AbstractSyncEntity) {
            throw new UnexpectedValueException('Cannot replace (not an AbstractSyncEntity): ' . implode('.', $path));
        }

        $node = $node->Id;
    }

    /**
     * Convert the entity to an associative array
     *
     * Nested objects and lists are returned as-is. Only the top-level entity is
     * replaced.
     *
     * @param SyncSerializeRulesInterface<static> $rules
     * @return array<string,mixed>
     */
    private function serialize(SyncSerializeRulesInterface $rules): array
    {
        $clone = clone $this;
        $clone->State |= EntityState::SERIALIZING;
        $array = SyncIntrospector::get(static::class)
            ->getSerializeClosure($rules)($clone);

        if (!$rules->getIncludeCanonicalId()) {
            unset($array[
                SyncIntrospector::get(static::class)
                    ->maybeNormalise('CanonicalId', NormaliserFlag::CAREFUL)
            ]);
        }

        return $array;
    }

    /**
     * @internal
     *
     * @return array<string,mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param SyncProviderInterface $provider
     * @param SyncContextInterface|null $context
     */
    final public static function provide(
        array $data,
        ProviderInterface $provider,
        ?ProviderContextInterface $context = null
    ) {
        $container = $context
            ? $context->getContainer()
            : $provider->getContainer();
        $container = $container->inContextOf(get_class($provider));

        $context = ($context ?? $provider->getContext())->withContainer($container);

        $closure = SyncIntrospector::getService(
            $container, static::class
        )->getCreateSyncEntityFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * @param SyncProviderInterface $provider
     * @param SyncContextInterface|null $context
     */
    final public static function provideList(
        iterable $list,
        ProviderInterface $provider,
        $conformity = ListConformity::NONE,
        ?ProviderContextInterface $context = null
    ): FluentIteratorInterface {
        return IterableIterator::from(
            self::_provideList($list, $provider, $conformity, $context)
        );
    }

    /**
     * @inheritDoc
     */
    final public static function idFromNameOrId(
        $nameOrId,
        $providerOrContext,
        ?float $uncertaintyThreshold = null,
        ?string $nameProperty = null,
        ?float &$uncertainty = null
    ) {
        if ($nameOrId === null) {
            $uncertainty = null;
            return null;
        }

        if ($providerOrContext instanceof SyncProviderInterface) {
            $provider = $providerOrContext;
            $context = $provider->getContext();
        } else {
            $context = $providerOrContext;
            $provider = $context->getProvider();
        }

        if ($provider->isValidIdentifier($nameOrId, static::class)) {
            $uncertainty = 0.0;
            return $nameOrId;
        }

        $entity = $provider
            ->with(static::class, $context)
            ->getResolver(
                $nameProperty,
                Algorithm::SAME | Algorithm::CONTAINS | Algorithm::NGRAM_SIMILARITY | Flag::NORMALISE,
                $uncertaintyThreshold,
                null,
                true,
            )
            ->getByName((string) $nameOrId, $uncertainty);

        if ($entity) {
            return $entity->Id;
        }

        throw new SyncEntityNotFoundException(
            $provider,
            static::class,
            $nameProperty === null
                ? ['name' => $nameOrId]
                : [$nameProperty => $nameOrId],
        );
    }

    /**
     * @inheritDoc
     */
    public function postLoad(): void {}

    /**
     * @return array<string,mixed>
     */
    public function __serialize(): array
    {
        foreach ([
            ...SyncIntrospector::get(static::class)->SerializableProperties,
            'MetaProperties',
            'MetaPropertyNames',
        ] as $property) {
            $data[$property] = $this->{$property};
        }

        $data['Provider'] = $this->Provider === null
            ? null
            : $this->Provider->getStore()->getProviderSignature($this->Provider);

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $property => $value) {
            if ($property === 'Provider' && $value !== null) {
                $value = is_string($value) && Sync::isLoaded() && Sync::hasProvider($value)
                    ? Sync::getProvider($value)
                    : null;
                if ($value === null) {
                    throw new UnexpectedValueException('Cannot unserialize missing provider');
                }
            }
            $this->{$property} = $value;
        }
    }

    /**
     * @param iterable<array-key,mixed[]> $list
     * @param SyncProviderInterface $provider
     * @param ListConformity::* $conformity
     * @param SyncContextInterface|null $context
     * @return Generator<array-key,static>
     */
    private static function _provideList(
        iterable $list,
        ProviderInterface $provider,
        $conformity,
        ?ProviderContextInterface $context
    ): Generator {
        $container = $context
            ? $context->getContainer()
            : $provider->getContainer();
        $container = $container->inContextOf(get_class($provider));

        $conformity = $context
            ? max($context->getConformity(), $conformity)
            : $conformity;

        $context = ($context ?? $provider->getContext())->withContainer($container);

        $introspector = SyncIntrospector::getService($container, static::class);

        foreach ($list as $key => $data) {
            if (!isset($closure)) {
                $closure = $conformity === ListConformity::PARTIAL || $conformity === ListConformity::COMPLETE
                    ? $introspector->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                    : $introspector->getCreateSyncEntityFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
    }
}
