<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\ITreeNode;

/**
 * @property-read IContainer $Container
 * @property-read ITreeNode $Parent
 */
final class SyncContext implements IReadable, IImmutable
{
    use TFullyReadable;

    /**
     * @internal
     * @var IContainer
     */
    protected $Container;

    /**
     * @internal
     * @var ITreeNode
     */
    protected $Parent;

    public function __construct(IContainer $container, ?ITreeNode $parent = null)
    {
        $this->Container = $container;
        $this->Parent    = $parent;
    }

    /**
     * @return $this
     */
    public function withContainer(IContainer $container)
    {
        $_this = clone $this;
        $_this->Container = $container;

        return $_this;
    }

    /**
     * @return $this
     */
    public function withParent(?ITreeNode $parent)
    {
        $_this         = clone $this;
        $_this->Parent = $parent;

        return $_this;
    }
}
