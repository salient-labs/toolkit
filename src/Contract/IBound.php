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
     * - SHOULD bind to a {@see Container} injected via its constructor
     * - MUST return the same container for every call to
     *   {@see IBound::container()}
     *
     * @return Container
     */
    public function container(): Container;

}
