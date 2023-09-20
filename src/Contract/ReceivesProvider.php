<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives the provider servicing the object
 *
 * @template TProvider of IProvider
 */
interface ReceivesProvider
{
    /**
     * Set the object's provider
     *
     * Throws an exception if the object already has a provider.
     *
     * @param TProvider $provider
     * @return $this
     */
    public function setProvider(IProvider $provider);
}
