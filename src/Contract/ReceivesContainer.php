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
     * @param string $id The identifier that was resolved to this instance by
     * the container.
     * @return $this
     */
    public function setContainer(\Lkrms\Container\Container $container, string $id);

}
