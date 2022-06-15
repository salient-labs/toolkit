<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

use Psr\Container\ContainerInterface as Container;

/**
 * Binds instances to a container
 *
 * - Instances SHOULD bind to their containers of origin by accepting a
 *   {@see Container} constructor parameter
 * - Each instance MUST return the same container every time
 *   {@see IBound::container()} is called
 */
interface IBound
{
    /**
     * Get the container the instance is bound to
     *
     * @return Container
     */
    public function container(): Container;

}
