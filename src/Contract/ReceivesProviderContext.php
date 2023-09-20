<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Receives a provider context
 *
 * @template TProviderContext of IProviderContext
 */
interface ReceivesProviderContext
{
    /**
     * Set the object's provider context
     *
     * @param TProviderContext $context
     * @return $this
     */
    public function setContext(IProviderContext $context);
}
