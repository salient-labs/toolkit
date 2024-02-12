<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives a provider context
 *
 * @template TContext of IProviderContext
 */
interface ReceivesProviderContext
{
    /**
     * Set the object's provider context
     *
     * @param TContext $context
     * @return $this
     */
    public function setContext(IProviderContext $context);
}
