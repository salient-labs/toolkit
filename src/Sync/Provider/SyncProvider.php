<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Container\DI;
use Lkrms\Util\Convert;
use Lkrms\Util\Generate;

/**
 * Base class for API providers
 *
 */
abstract class SyncProvider implements ISyncProvider
{
    /**
     * Return a stable identifier unique to the connected backend instance
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
     * Return a stable hash unique to the connected backend instance
     *
     * @return string
     * @see SyncProvider::getBackendIdentifier()
     */
    final public function getBackendHash(): string
    {
        return Generate::hash(...$this->getBackendIdentifier());
    }

    /**
     * Create a container binding for the class
     *
     * @see SyncProvider::bindInterfaces()
     * @see SyncProvider::bindCustom()
     */
    final public static function bind(): void
    {
        DI::singleton(static::class);
    }

    /**
     * Create container bindings for ISyncProvider interfaces implemented by the
     * class
     *
     * Example:
     *
     * ```php
     * interface OrgUnitProvider extends ISyncProvider
     * {
     *     public function getOrgUnits(): array;
     * }
     *
     * interface UserProvider extends ISyncProvider
     * {
     *     public function getUsers(): array;
     * }
     *
     * class LdapSyncProvider extends SyncProvider implements OrgUnitProvider, UserProvider
     * {
     *     // ...
     * }
     *
     * LdapSyncProvider::bind();
     * LdapSyncProvider::bindInterfaces();
     *
     * // In this case, calling bind() and bindInterfaces() on LdapSyncProvider
     * // is equivalent to:
     * DI::singleton(LdapSyncProvider::class);
     * DI::bind(OrgUnitProvider::class, LdapSyncProvider::class);
     * DI::bind(UserProvider::class, LdapSyncProvider::class);
     *
     * // Now, when an OrgUnitProvider is requested, an LdapSyncProvider
     * // instance will be returned
     * $provider = DI::get(OrgUnitProvider::class);
     * ```
     *
     * @param string ...$interfaces If no interfaces are specified, every
     * ISyncProvider interface implemented by the class will be bound.
     * @return void
     * @see SyncProvider::bind()
     */
    final public static function bindInterfaces(string ...$interfaces): void
    {
        (ClosureBuilder::getFor(static::class)->getBindISyncProviderInterfacesClosure())(...$interfaces);
    }

    /**
     * Create container bindings scoped exclusively to the provider
     *
     * This method is called just before the callback function in
     * {@see SyncProvider::bindAndRun()}. Changes made to the container are
     * removed after the callback returns.
     *
     * If particular {@see \Lkrms\Sync\SyncEntity} subclasses should be used
     * when the provider is instantiating those entities, they should be bound
     * here.
     */
    protected function bindCustom(): void
    {
    }

    /**
     * Run the given callback after applying the provider's custom bindings to a
     * temporary service container
     *
     * @param callable $callback
     * @return mixed
     */
    final public function bindAndRun(callable $callback)
    {
        DI::push();
        try
        {
            $this->bindCustom();
            $this->bindInterfaces();
            return $callback();
        }
        finally
        {
            DI::pop();
        }
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
     * If an array is found at `$args[0]`, a copy of the array with each key
     * converted to `snake_case` is returned.
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
            if (!empty(array_filter($args, function ($arg) { return !is_int($arg) && !is_string($arg); })))
            {
                return [];
            }

            return ["id" => $args];
        }

        $filter = [];

        foreach ($args[0] as $field => $value)
        {
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
