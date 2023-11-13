<?php declare(strict_types=1);

namespace Lkrms\Support;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A cancellable event dispatched by a service
 *
 * @template TService of object
 *
 * @extends ServiceEvent<TService>
 */
class StoppableServiceEvent extends ServiceEvent implements StoppableEventInterface
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
     * Don't pass the event to any more listeners
     *
     * @return $this
     */
    public function stopPropagation()
    {
        $this->Propagate = false;
        return $this;
    }
}
