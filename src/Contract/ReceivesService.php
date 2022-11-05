<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives the name of the class or interface it was resolved from
 *
 */
interface ReceivesService
{
    /**
     * Called immediately after instantiation by a container
     *
     * @param string $id The class or interface the container resolved by
     * creating the instance.
     * @return $this
     */
    public function setService(string $id);

}
