<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Concern\HasContainer;
use Lkrms\Contract\IBindableSingleton;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncEntityProvider;
use ReflectionClass;
use UnexpectedValueException;

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
    abstract protected function getDefinition(string $entity): ISyncDefinition;

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
    abstract protected function _getBackendIdentifier(): array;

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
     * Get an array that maps concrete classes to more specific subclasses
     *
     * {@inheritdoc}
     *
     * Bind any {@see SyncEntity} classes customised for this provider to their
     * generic parent classes by overriding this method, e.g.:
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
     * @see SyncProvider::_getBackendIdentifier()
     */
    final public function getBackendHash(): string
    {
        return $this->BackendHash
            ?: ($this->BackendHash = Compute::hash(...$this->_getBackendIdentifier()));
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

    public function with(string $syncEntity, $context = null): SyncEntityProvider
    {
        $container = ($context instanceof SyncContext
            ? $context->container()
            : ($context ?: $this->container()))->inContextOf(static::class);
        $context = ($context instanceof SyncContext
            ? $context->withContainer($container)
            : new SyncContext($container));

        return $container->get(
            SyncEntityProvider::class,
            $syncEntity,
            $this,
            $this->getDefinition($syncEntity),
            $context
        );
    }

    /**
     * Convert arguments to a normalised filter array
     *
     * A {@see SyncProvider} may accept **optional** arguments after a
     * {@see \Lkrms\Sync\Support\SyncOperation}'s mandatory parameters, but
     * using them to declare a filtering API, e.g. to filter
     * {@see \Lkrms\Sync\Support\SyncOperation::READ_LIST} results, is not
     * recommended. Create filters by passing undeclared arguments to
     * {@see SyncProvider::argsToFilter()} instead.
     *
     * Here's a typical invocation:
     *
     * ```php
     * public function getFaculties(SyncContext $ctx): array {
     *   $filter = $this->argsToFilter(func_get_args());
     * }
     * ```
     *
     * After {@see SyncContext} is removed (if present), `$args` must be empty
     * or correspond to one of the following signatures, otherwise an exception
     * will be thrown.
     *
     * 1. An associative array: `fn(array $filter)`
     *    - Alphanumeric keys are converted to snake_case
     *    - Keys containing characters other than letters, numbers, hyphens and
     *      underscores, e.g. `'$orderby'`, are returned as-is
     *
     * 2. A list of entity IDs: `fn(int|string ...$ids)`
     *    - Converted to `[ "id" => $ids ]`
     *    - See {@see SyncProvider::argsToIds()}
     *
     * 3. A list of entities: `fn(SyncEntity ...$entities)`
     *    - Converted to an array that maps the normalised name of each entity's
     *      unqualified
     *      {@see \Lkrms\Contract\IProvidable::providable() base class} to an
     *      array of entities
     *
     * @param bool $replaceWithId If `true`, {@see SyncEntity} objects are
     * replaced with the value of their {@see SyncEntity::$Id Id} when `$args`
     * contains an associative array or a list of entities. This operation is
     * not recursive.
     */
    protected function argsToFilter(array $args, bool $replaceWithId = true): array
    {
        if (($args[0] ?? null) instanceof SyncContext)
        {
            array_shift($args);
        }

        if (empty($args))
        {
            return [];
        }

        if (is_array($args[0]) && count($args) === 1)
        {
            return array_combine(array_map(
                fn($key) => preg_match('/[^[:alnum:]_-]/', $key) ? $key : Convert::toSnakeCase($key),
                array_keys($args[0])
            ), $replaceWithId ? array_map(
                fn($value) => $value instanceof SyncEntity ? $value->Id : $value,
                $args[0]
            ) : $args[0]);
        }

        if (empty(array_filter(
            $args,
            fn($arg) => !(is_int($arg) || is_string($arg))
        )))
        {
            return ["id" => $args];
        }

        if (empty(array_filter(
            $args,
            fn($arg) => !($arg instanceof SyncEntity)
        )))
        {
            return array_merge_recursive(...array_map(
                fn(SyncEntity $entity): array => [
                    Convert::toSnakeCase(Convert::classToBasename(
                        $entity->providable() ?: get_class($entity)
                    )) => [$replaceWithId ? $entity->Id : $entity]
                ],
                $args
            ));
        }

        throw new UnexpectedValueException("Invalid arguments");
    }

    /**
     * Convert arguments to a filter and return the values at $filter["id"]
     *
     * Calls {@see SyncProvider::argsToFilter()} to normalise `$args`.
     *
     * @return array Empty if no IDs were passed.
     */
    protected function argsToIds(array $args): array
    {
        return Convert::toList($this->argsToFilter($args)["id"] ?? []);
    }
}
