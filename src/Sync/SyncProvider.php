<?php

declare(strict_types=1);

namespace Lkrms\Sync;

use Lkrms\Convert;
use Lkrms\Generate;
use Lkrms\Sync\Provider\ISyncProvider;

/**
 * Base class for API providers
 *
 * @package Lkrms
 */
abstract class SyncProvider implements ISyncProvider
{
    /**
     * Returns a stable identifier unique to the connected backend instance
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
     * Returns a stable hash unique to the connected backend instance
     *
     * @return string
     * @see SyncProvider::getBackendIdentifier()
     */
    final public function getBackendHash(): string
    {
        return Generate::hash(...$this->getBackendIdentifier());
    }

    /**
     * Normalises arguments commonly passed to getList methods
     *
     * A {@see SyncProvider} MUST NOT add mandatory arguments to any of its
     * {@see SyncOperation::READ_LIST} implementations, but a caller MAY pass
     * undeclared arguments, and a provider MAY take them into account when
     * performing the requested operation.
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
     * Returns a list of entity IDs passed to a getList method
     *
     * Uses {@see SyncProvider::getListFilter()} to normalise `$args`. Returns
     * an empty array if no entity IDs are passed.
     *
     * @param array $args
     * @return array
     */
    protected function getListIds(array $args): array
    {
        return Convert::toList($this->getListFilter($args)["id"] ?? []);
    }
}
