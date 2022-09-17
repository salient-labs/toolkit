<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\DateFormatter;

/**
 * Creates objects from backend data
 *
 */
interface IProvider
{
    /**
     * Get the container used to instantiate objects
     *
     */
    public function container(): IContainer;

    /**
     * Get a stable hash that, together with the name of the class, uniquely
     * identifies the backend instance
     *
     */
    public function getBackendHash(): string;

    /**
     * Get a DateFormatter to work with the backend's date format and timezone
     *
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
