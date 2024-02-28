<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @api
 *
 * @template TContext of ProviderContextInterface
 */
interface ProviderContextAwareInterface
{
    /**
     * Set the object's provider context
     *
     * @param TContext $context
     * @return $this
     */
    public function setContext(ProviderContextInterface $context);

    /**
     * Get the object's current provider context
     *
     * @return TContext|null
     */
    public function getContext(): ?ProviderContextInterface;

    /**
     * Get the object's current provider context, or throw an exception if no
     * provider context has been set
     *
     * @return TContext
     */
    public function requireContext(): ProviderContextInterface;
}
