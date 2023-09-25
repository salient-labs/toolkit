<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Support\DateFormatter;

/**
 * Creates objects that represent entities in a backend
 */
interface IProvider extends ReturnsContainer, ReturnsEnvironment, ReturnsDescription
{
    /**
     * Get a context for instantiation of objects on the provider's behalf
     */
    public function getContext(?IContainer $container = null): IProviderContext;

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
     * @return array<string|\Stringable>
     */
    public function getBackendIdentifier(): array;

    /**
     * Get a DateFormatter to work with the backend's date format and timezone
     */
    public function dateFormatter(): DateFormatter;

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
