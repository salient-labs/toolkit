<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Core\Contract\StoppableEventInterface;

/**
 * Implements IStoppableEvent
 *
 * @see StoppableEventInterface
 */
trait StoppableEventTrait
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
