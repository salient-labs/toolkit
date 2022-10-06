<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * The context within which an IProvidable is instantiated
 *
 */
interface IProvidableContext extends IReadable, IImmutable, ReturnsContainer
{
    public function getParent(): ?IHierarchy;

    /**
     * @return $this
     */
    public function withContainer(IContainer $container);

}
