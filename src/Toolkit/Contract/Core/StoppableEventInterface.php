<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Psr\EventDispatcher\StoppableEventInterface as PsrStoppableEventInterface;

/**
 * A cancellable event
 */
interface StoppableEventInterface extends PsrStoppableEventInterface
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
