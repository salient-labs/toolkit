<?php declare(strict_types=1);

namespace Salient\Contract\Core\Event;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Salient\Contract\Core\HasName;
use Salient\Contract\Core\Instantiable;

/**
 * @api
 */
interface EventDispatcherInterface extends
    PsrEventDispatcherInterface,
    EventListenerProviderInterface,
    Instantiable
{
    /**
     * Dispatch a given event to listeners registered for it
     *
     * If the event implements {@see HasName}, the return value of
     * {@see HasName::getName()} is added to the list of events for which
     * registered listeners are called.
     *
     * @template TEvent of object
     *
     * @param TEvent $event
     * @return TEvent
     */
    public function dispatch(object $event): object;
}
