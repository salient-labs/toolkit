<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Instances receive the container that created them
 *
 */
interface ReceivesContainer
{
    /**
     * Called immediately after instantiation by a container
     *
     * @return $this
     */
    public function setContainer(\Lkrms\Container\Container $container);

}
