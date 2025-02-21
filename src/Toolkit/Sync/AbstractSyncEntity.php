<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Container\RequiresContainer;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Providable;
use Salient\Contract\Core\Entity\Temporal;
use Salient\Contract\Core\Provider\ProviderContextInterface;
use Salient\Contract\Core\Provider\ProviderInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Flushable;
use Salient\Contract\Core\HasDescription;
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
use Salient\Core\Concern\ProvidableTrait;
use Salient\Core\Concern\ReadableTrait;
use Salient\Core\Concern\WritableTrait;
use Salient\Core\Date\DateFormatter;
use Salient\Core\Facade\Sync;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\Reflection\SyncEntityReflection;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use DateTimeInterface;
use LogicException;
use ReflectionClass;
use UnexpectedValueException;

abstract class AbstractSyncEntity implements
    SyncEntityInterface,
    Temporal,
    Flushable
{
    use ConstructibleTrait;
    use ExtensibleTrait;
    use ReadableTrait;
    use WritableTrait;
    /** @use ProvidableTrait<SyncProviderInterface,SyncContextInterface> */
    use ProvidableTrait;
    use RequiresContainer;

    /**
     * The unique identifier assigned to the entity by its provider
     *
     * @var int|string|null
     */
    public $Id;

    /**
     * The unique identifier assigned to the entity by its canonical backend
     *
     * @var int|string|null
     */
    public $CanonicalId;

    // --

    /** @var SyncProviderInterface|null */
    private ?ProviderInterface $Provider = null;
    /** @var SyncContextInterface|null */
    private ?ProviderContextInterface $Context = null;
    /** @var int-mask-of<EntityState::*> */
    private int $State = 0;

    /**
     * Entity => "/^<pattern>_/" | false
     *
     * @var array<class-string<self>,string|false>
     */
    private static array $RemovablePrefixRegex = [];

    /**
     * Entity => [ property name => normalised property name, ... ]
     *
     * @var array<class-string<self>,array<string,string>>
     */
    private static array $NormalisedNames = [];

    /**
     * Entity => [ normalised property name | property name, ... ]
     *
     * @var array<class-string<self>,string[]>
     */
    private static array $SerializableNames;

    // --

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
    public static function flushStatic(): void
    {
        unset(
            self::$RemovablePrefixRegex[static::class],
            self::$NormalisedNames[static::class],
            self::$SerializableNames[static::class],
        );
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return SyncIntrospector::get(static::class)
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
     * Return `null` to suppress prefix removal, otherwise use
     * {@see AbstractSyncEntity::normalisePrefixes()} or
     * {@see AbstractSyncEntity::expandPrefixes()} to normalise the return
     * value.
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
        return self::getDefaultProvider($container)->with(static::class, $context);
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
     * Normalise a property name
     *
     * 1. `$name` is converted to snake_case
     * 2. If `$fromData` is `false` or the result matches a `$declaredName`, it
     *    is returned
     * 3. Otherwise, prefixes returned by {@see getRemovablePrefixes()} are
     *    removed
     */
    final public static function normaliseProperty(
        string $name,
        bool $fromData = true,
        string ...$declaredName
    ): string {
        if (isset(self::$NormalisedNames[static::class][$name])) {
            return self::$NormalisedNames[static::class][$name];
        }

        $_name = Str::snake($name);

        if (
            $fromData
            && (!$declaredName || !in_array($_name, $declaredName))
            && ($regex = self::$RemovablePrefixRegex[static::class] ??=
                self::getRemovablePrefixRegex()) !== false
        ) {
            $_name = Regex::replace($regex, '', $_name);
        }

        return self::$NormalisedNames[static::class][$name] = $_name;
    }

    /**
     * @return string|false
     */
    private static function getRemovablePrefixRegex()
    {
        $prefixes = static::getRemovablePrefixes();

        return $prefixes
            ? sprintf(
                count($prefixes) > 1 ? '/^(?:%s)_/' : '/^%s_/',
                implode('|', $prefixes),
            )
            : false;
    }

    /**
     * @inheritDoc
     */
    final public function toArray(?SyncStoreInterface $store = null): array
    {
        /** @var SyncSerializeRulesInterface<self> */
        $rules = self::getSerializeRules();
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
     * @param self|DeferredEntityInterface<self>|DeferredRelationshipInterface<self>|DateTimeInterface|mixed[] $node
     * @param string[] $path
     * @param SyncSerializeRulesInterface<self> $rules
     * @param array<int,true> $parents
     * @param-out ($node is self ? array<string,mixed> : mixed[]|string|null) $node
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
        if (count($path) > $maxDepth) {
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

                /** @var self[]|self|null */
                $child = &$node[$key];
                $this->_serializeId($child);
                unset($child);
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
            /** @var self|null $child */
            foreach ($node as &$child) {
                $this->_serializeId($child);
            }
            unset($child);
        }

        $isList = false;
        if (Arr::hasNumericKeys($node)) {
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
            /** @var self|DeferredEntityInterface<self>|DeferredRelationshipInterface<self>|DateTimeInterface|mixed[] $child */
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
     * @param self[]|self|null $node
     * @param-out ($node is self[] ? array<int|string|null> : ($node is self ? int|string|null : null)) $node
     */
    private function _serializeId(&$node): void
    {
        if ($node === null) {
            return;
        }

        if (is_array($node)) {
            foreach ($node as $key => $child) {
                $children[$key] = $child->Id;
            }
            $node = $children ?? [];
            return;
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

        if (!$rules->getCanonicalId()) {
            unset($array[self::normaliseProperty('CanonicalId', false)]);
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
     * @inheritDoc
     */
    final public static function provide(
        array $data,
        ProviderContextInterface $context
    ) {
        $provider = $context->getProvider();
        $container = $context
            ->getContainer()
            ->inContextOf(get_class($provider));
        $context = $context->withContainer($container);

        $closure = SyncIntrospector::getService($container, static::class)
            ->getCreateSyncEntityFromClosure();

        return $closure($data, $provider, $context);
    }

    /**
     * @inheritDoc
     */
    final public static function provideMultiple(
        iterable $data,
        ProviderContextInterface $context,
        int $conformity = Providable::CONFORMITY_NONE
    ): iterable {
        $provider = $context->getProvider();
        $container = $context
            ->getContainer()
            ->inContextOf(get_class($provider));
        $context = $context->withContainer($container);
        $conformity = max($context->getConformity(), $conformity);
        $introspector = SyncIntrospector::getService($container, static::class);

        foreach ($data as $key => $data) {
            if (!isset($closure)) {
                $closure = $conformity === self::CONFORMITY_PARTIAL || $conformity === self::CONFORMITY_COMPLETE
                    ? $introspector->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                    : $introspector->getCreateSyncEntityFromClosure();
            }

            yield $key => $closure($data, $provider, $context);
        }
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
                SyncEntityProviderInterface::ALGORITHM_SAME
                    | SyncEntityProviderInterface::ALGORITHM_CONTAINS
                    | SyncEntityProviderInterface::ALGORITHM_NGRAM_SIMILARITY
                    | SyncEntityProviderInterface::NORMALISE,
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
     * @return array<string,mixed>
     */
    public function __serialize(): array
    {
        $properties = self::$SerializableNames[static::class] ??= [
            ...(new SyncEntityReflection(static::class))->getSerializableNames(),
            static::getDynamicPropertiesProperty(),
            static::getDynamicPropertyNamesProperty(),
        ];

        foreach ($properties as $property) {
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
}
