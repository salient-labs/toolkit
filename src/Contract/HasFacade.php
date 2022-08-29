<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Instances receive the name of the facade they're servicing
 *
 * @see \Lkrms\Contract\IFacade
 */
interface HasFacade
{
    /**
     * Called immediately after instantiation by a facade
     *
     * @return $this
     */
    public function setFacade(string $name);

}
