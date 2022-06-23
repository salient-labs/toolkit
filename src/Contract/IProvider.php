<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Support\DateFormatter;

/**
 * Creates objects from backend data
 *
 */
interface IProvider extends IBound
{
    /**
     * Return a stable hash unique to the backend instance
     *
     * @return string
     */
    public function getBackendHash(): string;

    /**
     * Return the backend's preferred date format and/or timezone
     *
     * @return DateFormatter
     */
    public function getDateFormatter(): DateFormatter;

    /**
     * Throw an exception if the backend isn't reachable
     *
     * This method may emit a {@see ConsoleLevel::DEBUG} message after
     * confirming a successful handshake, and positive results should be cached
     * for up to `$ttl` seconds.
     */
    public function checkHeartbeat(int $ttl = 300): void;
}
