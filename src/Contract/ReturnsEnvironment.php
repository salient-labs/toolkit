<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Utility\Env;

/**
 * Returns a shared instance of Lkrms\Utility\Env
 */
interface ReturnsEnvironment
{
    /**
     * Get a shared instance of Lkrms\Utility\Env
     */
    public function env(): Env;
}
