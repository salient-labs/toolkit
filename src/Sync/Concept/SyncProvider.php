<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Concern\HasContainer;
use Lkrms\Contract\IBindableSingleton;
use Lkrms\Contract\IContainer;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Provider\SyncEntityProvider;
use Lkrms\Sync\Support\SyncDefinitionBuilder;
use ReflectionClass;

/**
 * Base class for providers that sync entities to and from third-party backends
 * via their APIs
 *
 */
abstract class SyncProvider implements ISyncProvider, IBindableSingleton
{
    use HasContainer;

    /**
     * Surface the provider's implementation of sync operations for an entity
     * via an ISyncDefinition object
     *
     */
    abstract protected function getDefinition(string $entity): ?ISyncDefinition;

    /**
     * Return a stable identifier that, together with the name of the class,
     * uniquely identifies the connected backend instance
     *
     * This method must be idempotent for each backend instance the provider
     * connects to. The return value should correspond to the smallest possible
     * set of stable metadata that uniquely identifies the specific data source
     * backing the connected instance.
     *
     * This could include:
     * - an endpoint URI (if backend instances are URI-specific or can be
     *   expressed as an immutable URI)
     * - a tenant ID
     * - an installation GUID
     *
     * It should not include:
     * - usernames, API keys, tokens, or other identifiers with a shorter
     *   lifespan than the data source itself
     * - values that aren't unique to the connected data source
     * - case-insensitive values (unless normalised first)
     *
     * @return string[]
     */
    abstract protected function getBackendIdentifier(): array;

    /**
     * Specify how to encode dates for the backend, and which timezone to apply
     * (optional)
     *
     * The {@see DateFormatter} returned will be cached for the lifetime of the
     * {@see SyncProvider} instance.
     *
     */
    abstract protected function _getDateFormatter(): DateFormatter;

    /**
     * {@inheritdoc}
     *
     * Bind any {@see \Lkrms\Sync\SyncEntity} classes customised for this
     * provider to their generic parent classes by overriding this method, e.g.:
     *
     * ```php
     * public static function getBindings(): array
     * {
     *     return [
     *         Post::class => CustomPost::class,
     *         User::class => CustomUser::class,
     *     ];
     * }
     * ```
     *
     */
    public static function getBindings(): array
    {
        return [];
    }

    /**
     * Use a fluent interface to create a new SyncDefinition object
     *
     */
    protected function getDefinitionBuilder(string $entity): SyncDefinitionBuilder
    {
        return (new SyncDefinitionBuilder())
            ->entity($entity)
            ->provider($this);
    }

    /**
     * @var string|null
     */
    private $BackendHash;

    /**
     * @var DateFormatter|null
     */
    private $DateFormatter;

    /**
     * @var array<string,string[]>
     */
    private static $SyncProviderInterfaces = [];

    /**
     * @see SyncProvider::getBackendIdentifier()
     */
    final public function getBackendHash(): string
    {
        return $this->BackendHash
            ?: ($this->BackendHash = Compute::hash(...$this->getBackendIdentifier()));
    }

    final public function getDateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?: ($this->DateFormatter = $this->_getDateFormatter());
    }

    final public static function getBindable(): array
    {
        if (!is_null($interfaces = self::$SyncProviderInterfaces[static::class] ?? null))
        {
            return $interfaces;
        }
        $class      = new ReflectionClass(static::class);
        $interfaces = [];
        foreach ($class->getInterfaces() as $name => $interface)
        {
            if ($interface->isSubclassOf(ISyncProvider::class))
            {
                $interfaces[] = $name;
            }
        }
        return self::$SyncProviderInterfaces[static::class] = $interfaces;
    }

    /**
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     */
    public function with(string $syncEntity, ?IContainer $app = null): SyncEntityProvider
    {
        return ($app ?: $this->app())->get(
            SyncEntityProvider::class,
            $syncEntity,
            $this,
            $this->getDefinition($syncEntity)
        );
    }

    /**
     * Normalise arguments commonly passed to getList methods
     *
     * A {@see SyncProvider} MUST NOT add mandatory arguments to any of its
     * {@see \Lkrms\Sync\SyncOperation::READ_LIST} implementations, but a caller
     * MAY pass undeclared arguments, and a provider MAY take them into account
     * when performing the requested operation.
     *
     * `getListFilter` returns an associative filter array based on `$args`.
     * Here's a typical invocation:
     *
     * ```php
     * public function getFaculties(): array {
     *   $filter = $this->getListFilter(func_get_args());
     * }
     * ```
     *
     * The following signatures are recognised:
     *
     * ```php
     * // 1. An array at index 0 (subsequent arguments are ignored)
     * getList(array $filter);
     *
     * // 2. A list of entity IDs (where every argument is an integer or string)
     * getList(...$ids);
     * ```
     *
     * If an array is found at `$args[0]`, a copy of the array with each
     * alphanumeric key converted to `snake_case` is returned. Keys containing
     * characters other than letters, numbers, hyphens and underscores--e.g.
     * `$orderby`--are copied as-is.
     *
     * If every value in `$args` is either an `int` or a `string`, a filter
     * similar to the following is returned:
     *
     * ```php
     * ["id" => [39, 237, 239, 240, 316, 344, 357, 361, 370, 380]]
     * ```
     *
     * Otherwise, an empty array is returned.
     *
     * @param array $args
     * @return array
     */
    protected function getListFilter(array $args): array
    {
        if (empty($args))
        {
            return [];
        }

        if (!is_array($args[0]))
        {
            if (!empty(array_filter(
                $args,
                function ($arg) { return !is_int($arg) && !is_string($arg); }
            )))
            {
                return [];
            }

            return ["id" => $args];
        }

        $filter = [];

        foreach ($args[0] as $field => $value)
        {
            if (preg_match('/[^[:alnum:]_-]/', $field))
            {
                $filter[$field] = $value;
                continue;
            }
            $filter[Convert::toSnakeCase($field)] = $value;
        }

        return $filter;
    }

    /**
     * Return a list of entity IDs passed to a getList method
     *
     * `getListIds` uses {@see SyncProvider::getListFilter()} to normalise
     * `$args`. Returns an empty array if no entity IDs are passed.
     *
     * @param array $args
     * @return array
     */
    protected function getListIds(array $args): array
    {
        return Convert::toList($this->getListFilter($args)["id"] ?? []);
    }
}
