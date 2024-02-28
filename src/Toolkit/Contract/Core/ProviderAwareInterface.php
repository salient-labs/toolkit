<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Receives the provider servicing the object
 *
 * @template TProvider of ProviderInterface
 *
 * @extends HasProvider<TProvider>
 */
interface ProviderAwareInterface extends HasProvider
{
    /**
     * Set the object's provider
     *
     * Throws an exception if the object already has a provider.
     *
     * @param TProvider $provider
     * @return $this
     */
    public function setProvider(ProviderInterface $provider);
}
