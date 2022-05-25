<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Creates objects from backend data
 *
 */
interface IProvider
{
    /**
     * Return a stable hash unique to the backend instance
     *
     * @return string
     */
    public function getBackendHash(): string;

}
