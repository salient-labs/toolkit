<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\DateFormatter;

/**
 * Creates objects from backend data
 *
 */
interface IProvider extends IHasContainer
{
    /**
     * Get a stable hash that uniquely identifies the backend instance
     *
     * @return string
     */
    public function getBackendHash(): string;

    /**
     * Get a DateFormatter for the backend's date format and timezone
     *
     * @return DateFormatter
     */
    public function getDateFormatter(): DateFormatter;

    /**
     * Throw an exception if the backend isn't reachable
     *
     * This method MAY emit a {@see ConsoleLevel::DEBUG} message after
     * confirming a successful handshake. Positive results SHOULD be cached for
     * up to `$ttl` seconds.
     */
    public function checkHeartbeat(int $ttl = 300): void;

}
