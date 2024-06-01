<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
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
    public function getProvider(): ?ProviderInterface;
}
