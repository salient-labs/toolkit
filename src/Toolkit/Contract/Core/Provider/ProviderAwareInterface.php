<?php declare(strict_types=1);

namespace Salient\Contract\Core\Provider;

use LogicException;

/**
 * @api
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
     * @param TProvider $provider
     * @return $this
     * @throws LogicException if the object already has a provider.
     */
    public function setProvider(ProviderInterface $provider);
}
