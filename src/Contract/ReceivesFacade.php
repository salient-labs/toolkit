<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives the name of the facade it's servicing
 *
 * @see IFacade
 */
interface ReceivesFacade
{
    /**
     * Called after instantiation by a facade
     *
     * @param class-string<IFacade<static>> $name
     * @return $this
     */
    public function setFacade(string $name);
}
