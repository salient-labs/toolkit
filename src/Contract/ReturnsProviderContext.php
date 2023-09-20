<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Returns a provider context
 *
 * @template TProviderContext of IProviderContext
 */
interface ReturnsProviderContext
{
    /**
     * Get the object's current provider context
     *
     * @return TProviderContext|null
     */
    public function context(): ?IProviderContext;

    /**
     * Get the object's current provider context, or throw an exception if no
     * provider context has been set
     *
     * @return TProviderContext
     */
    public function requireContext(): IProviderContext;
}
