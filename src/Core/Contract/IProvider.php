<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

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
     * @return DateFormatter
     */
    public function getDateFormatter(): DateFormatter;
}
