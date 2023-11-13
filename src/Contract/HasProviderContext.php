<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Returns a provider context
 *
 * @template TContext of IProviderContext
 */
interface HasProviderContext
{
    /**
     * Get the object's current provider context
     *
     * @return TContext|null
     */
    public function context(): ?IProviderContext;

    /**
     * Get the object's current provider context, or throw an exception if no
     * provider context has been set
     *
     * @return TContext
     */
    public function requireContext(): IProviderContext;
}
