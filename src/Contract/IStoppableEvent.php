<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A cancellable event
 */
interface IStoppableEvent extends StoppableEventInterface
{
    /**
     * True if the event should not be passed to listeners
     */
    public function isPropagationStopped(): bool;

    /**
     * Don't pass the event to listeners
     *
     * @return $this
     */
    public function stopPropagation();
}
