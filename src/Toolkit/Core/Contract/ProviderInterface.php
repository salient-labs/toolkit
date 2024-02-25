<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Container\Contract\HasContainer;
use Salient\Container\ContainerInterface;
use Salient\Core\Contract\DateFormatterInterface;
use Salient\Core\Exception\MethodNotImplementedException;
use Stringable;

/**
 * Services objects on behalf of a backend
 *
 * @template TContext of ProviderContextInterface
 *
 * @extends HasContainer<ContainerInterface>
 */
interface ProviderInterface extends HasContainer, Nameable
{
    /**
     * Get the name of the provider
     *
     * Appropriate values to return are:
     *
     * - already in scope (no lookup required)
     * - ready to use (no formatting required)
     * - unique enough that duplicates are rare
     * - human-readable
     */
    public function name(): string;

    /**
     * Get a context for instantiation of objects on the provider's behalf
     *
     * @return TContext
     */
    public function getContext(?ContainerInterface $container = null): ProviderContextInterface;

    /**
     * Get a stable list of values that, together with the name of the class,
     * uniquely identifies the backend instance
     *
     * This method must be idempotent for each backend instance the provider
     * connects to. The return value should correspond to the smallest possible
     * set of stable metadata that uniquely identifies the specific data source
     * backing the connected instance.
     *
     * This could include:
     *
     * - an endpoint URI (if backend instances are URI-specific or can be
     *   expressed as an immutable URI)
     * - a tenant ID
     * - an installation GUID
     *
     * It should not include:
     *
     * - usernames, API keys, tokens, or other identifiers with a shorter
     *   lifespan than the data source itself
     * - values that aren't unique to the connected data source
     * - case-insensitive values (unless normalised first)
     *
     * @return array<int|float|string|bool|Stringable|null>
     */
    public function getBackendIdentifier(): array;

    /**
     * Get a date formatter to work with the backend's date and time format
     * and/or timezone
     */
    public function dateFormatter(): DateFormatterInterface;

    /**
     * Throw an exception if the backend isn't reachable
     *
     * Positive results should be cached for `$ttl` seconds. Negative results
     * must never be cached.
     *
     * @return $this
     * @throws MethodNotImplementedException if heartbeat monitoring isn't
     * supported.
     */
    public function checkHeartbeat(int $ttl = 300);
}
