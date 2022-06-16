<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Container\Container;
use Lkrms\Contract\IBindable;
use Lkrms\Concern\TBindableSingleton;
use Lkrms\Support\DateFormatter;
use Lkrms\Util\Convert;
use Lkrms\Util\Generate;
use UnexpectedValueException;

/**
 * Base class for API providers
 *
 */
abstract class SyncProvider implements ISyncProvider, IBindable
{
    use TBindableSingleton;

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
     * Create container bindings for ISyncProvider interfaces implemented by the
     * class
     */
    final public static function bindServices(Container $container, string ...$interfaces)
    {
        self::_bindServices($container, false, ...$interfaces);
    }

    /**
     * Create container bindings for ISyncProvider interfaces implemented by the
     * class that aren't in the given exception list
     */
    final public static function bindServicesExcept(Container $container, string ...$interfaces)
    {
        if (!$interfaces)
        {
            throw new UnexpectedValueException("No interfaces to exclude");
        }
        self::_bindServices($container, true, ...$interfaces);
    }

    private static function _bindServices(Container $container, bool $invert, string ...$interfaces)
    {
        (ClosureBuilder::get(
            static::class
        )->getBindISyncProviderInterfacesClosure())($container, $invert, ...$interfaces);
    }

    /**
     * {@inheritdoc}
     *
     * This method is called before the callback in
     * {@see SyncProvider::invokeInBoundContainer()}. Changes made to the
     * container are removed after the callback returns.
     *
     * If particular {@see \Lkrms\Sync\SyncEntity} subclasses should be used
     * when the provider is instantiating those entities, they should be bound
     * here.
     */
    public static function bindConcrete(Container $container)
    {
    }

    final public function invokeInBoundContainer(callable $callback, Container $container = null)
    {
        if (!$container)
        {
            $container = $this->container();
        }
        $container->push();
        try
        {
            static::bindServices($container);
            static::bindConcrete($container);
            $clone = clone $container;
            $container->bindContainer($clone);
            return $callback($clone);
        }
        finally
        {
            $container->pop();
        }
    }

    protected function _getDateFormatter(): DateFormatter
    {
        return new DateFormatter();
    }

    /**
     * @var DateFormatter|null
     */
    private $DateFormatter;

    final public function getDateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?: ($this->DateFormatter = $this->_getDateFormatter());
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
