<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Utility\Environment;

/**
 * Returns the Env facade's underlying Environment instance
 *
 */
interface ReturnsEnvironment
{
    /**
     * Get the Env facade's underlying Environment instance
     *
     */
    public function env(): Environment;
}
