<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Returns the provider servicing the object
 *
 * @template TProvider of ProviderInterface
 */
interface HasProvider
{
    /**
     * Get the object's provider
     *
     * @return TProvider|null
     */
    public function provider(): ?ProviderInterface;

    /**
     * Get the object's provider, or throw an exception if no provider has been
     * set
     *
     * @return TProvider
     */
    public function requireProvider(): ProviderInterface;
}
