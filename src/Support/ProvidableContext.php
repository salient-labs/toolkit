<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\IHierarchy;

/**
 * The context within which an IProvidable is instantiated
 *
 * @property-read IContainer $Container
 * @property-read IHierarchy $Parent
 */
class ProvidableContext implements IProvidableContext
{
    use TFullyReadable;

    /**
     * @var IContainer
     */
    protected $Container;

    /**
     * @var IHierarchy|null
     */
    protected $Parent;

    public function __construct(IContainer $container, ?IHierarchy $parent = null)
    {
        $this->Container = $container;
        $this->Parent    = $parent;
    }

    public function app(): IContainer
    {
        return $this->Container;
    }

    public function container(): IContainer
    {
        return $this->Container;
    }

    public function getParent(): ?IHierarchy
    {
        return $this->Parent;
    }

    public function withContainer(IContainer $container)
    {
        if ($this->Container === $container)
        {
            return $this;
        }

        $clone = clone $this;
        $clone->Container = $container;

        return $clone;
    }

    /**
     * @return $this
     */
    public function withParent(?IHierarchy $parent)
    {
        if ($this->Parent === $parent)
        {
            return $this;
        }

        $clone         = clone $this;
        $clone->Parent = $parent;

        return $clone;
    }

}
