<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\ITreeNode;

/**
 * @property-read IContainer $Container
 * @property-read ITreeNode $Parent
 */
class ProvidableContext implements IProvidableContext
{
    use TFullyReadable;

    /**
     * @internal
     * @var IContainer
     */
    protected $Container;

    /**
     * @internal
     * @var ITreeNode|null
     */
    protected $Parent;

    public function __construct(IContainer $container, ?ITreeNode $parent = null)
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

    public function getParent(): ?ITreeNode
    {
        return $this->Parent;
    }

    public function withContainer(IContainer $container)
    {
        if ($this->Container === $container)
        {
            return $this;
        }

        $_this = clone $this;
        $_this->Container = $container;

        return $_this;
    }

    /**
     * @return $this
     */
    public function withParent(?ITreeNode $parent)
    {
        if ($this->Parent === $parent)
        {
            return $this;
        }

        $_this         = clone $this;
        $_this->Parent = $parent;

        return $_this;
    }

}
