<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Psr\Container\ContainerInterface as Container;

/**
 * Instances are bound to a container
 *
 */
interface IBound
{
    /**
     * Get the container the instance is bound to
     *
     * Each instance:
     * - MAY bind to its container of origin by accepting a {@see Container}
     *   constructor parameter.
     * - MUST return the same container for every call to
     *   {@see IBound::container()}
     *
     * @return Container
     */
    public function container(): Container;

}
