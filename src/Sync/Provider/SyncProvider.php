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
     * Create DI container bindings for the class and the ISyncProvider
     * interfaces it implements
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
     * // Option 1: bind the class and its ISyncProvider interfaces manually
     * DI::singleton(LdapSyncProvider::class);
     * DI::bind(OrgUnitProvider::class, LdapSyncProvider::class);
     * DI::bind(UserProvider::class, LdapSyncProvider::class);
     *
     * // Option 2: call the SyncProvider's register() method
     * LdapSyncProvider::register();
     *
     * // Now, when an OrgUnitProvider is requested, an LdapSyncProvider
     * // instance will be returned
     * $provider = DI::get(OrgUnitProvider::class);
     * ```
     *
     * @param string ...$interfaces If no interfaces are specified, every
     * ISyncProvider interface implemented by the class will be bound.
     * @return void
     */
    public static function register(string ...$interfaces): void
    {
        DI::singleton(static::class);
        (ClosureBuilder::getFor(static::class)->getBindISyncProviderInterfacesClosure())(...$interfaces);
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
