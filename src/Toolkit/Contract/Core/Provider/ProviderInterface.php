<?php declare(strict_types=1);

namespace Salient\Contract\Core\Provider;

use Salient\Contract\Container\HasContainer;
use Salient\Contract\Core\Exception\MethodNotImplementedException;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\HasName;
use Stringable;

/**
 * Services objects on behalf of a backend
 *
 * @template TContext of ProviderContextInterface
 */
interface ProviderInterface extends HasContainer, HasName
{
    /**
     * Get the name of the provider
     *
     * Appropriate values to return are:
     *
     * - already in scope (no lookup or transformation required)
     * - likely to be unique
     * - human-readable
     */
    public function getName(): string;

    /**
     * Get a stable list of values that, together with its class name, uniquely
     * identifies the provider's backend instance
     *
     * This method must be idempotent for each backend instance the provider
     * connects to. The return value should correspond to the smallest possible
     * set of stable metadata that uniquely identifies the specific data source
     * backing the connected instance.
     *
     * This may include:
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
     * It must not include:
     *
     * - values retrieved from a provider at runtime
     *
     * @return array<int|float|string|bool|Stringable|null>
     */
    public function getBackendIdentifier(): array;

    /**
     * Get a date formatter to work with the backend's date and time format
     * and/or timezone
     */
    public function getDateFormatter(): DateFormatterInterface;

    /**
     * Get a context within which to instantiate entities on the provider's
     * behalf
     *
     * @return TContext
     */
    public function getContext(): ProviderContextInterface;

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
