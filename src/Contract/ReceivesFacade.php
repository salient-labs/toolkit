<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives the name of the facade it's servicing
 *
 * @see \Lkrms\Contract\IFacade
 */
interface ReceivesFacade
{
    /**
     * Called immediately after instantiation by a facade
     *
     */
    public function setFacade(string $name): void;

}
