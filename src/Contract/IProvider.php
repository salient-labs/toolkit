<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Support\DateFormatter;

/**
 * Creates objects from backend data
 *
 */
interface IProvider extends ReturnsContainer, ReturnsDescription
{
    /**
     * Get a stable list of values that, together with the name of the class,
     * uniquely identifies the backend instance
     *
     * @return array<string|\Stringable>
     */
    public function getBackendIdentifier(): array;

    /**
     * Get a DateFormatter to work with the backend's date format and timezone
     *
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
