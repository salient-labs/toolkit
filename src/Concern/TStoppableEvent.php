<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IStoppableEvent;

/**
 * Implements IStoppableEvent
 *
 * @see IStoppableEvent
 */
trait TStoppableEvent
{
    protected bool $Propagate = true;

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return !$this->Propagate;
    }

    /**
     * @return $this
     */
    public function stopPropagation()
    {
        $this->Propagate = false;
        return $this;
    }
}
