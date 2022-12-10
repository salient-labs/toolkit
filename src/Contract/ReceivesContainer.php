<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives the container that created it
 *
 */
interface ReceivesContainer
{
    /**
     * Called immediately after instantiation by a container
     *
     * @return $this
     */
    public function setContainer(IContainer $container);
}
