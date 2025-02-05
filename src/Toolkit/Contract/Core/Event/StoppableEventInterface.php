<?php declare(strict_types=1);

namespace Salient\Contract\Core\Event;

use Psr\EventDispatcher\StoppableEventInterface as PsrStoppableEventInterface;

/**
 * @api
 */
interface StoppableEventInterface extends PsrStoppableEventInterface
{
    /**
     * Check if the event should be propagated to listeners
     *
     * @return bool If `true`, no further listeners should be called for this
     * event.
     */
    public function isPropagationStopped(): bool;

    /**
     * Do not propagate the event to further listeners
     *
     * @return $this
     */
    public function stopPropagation();
}
