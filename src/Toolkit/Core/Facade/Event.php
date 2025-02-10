<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Contract\Core\Event\EventDispatcherInterface;
use Salient\Contract\Core\Event\EventListenerProviderInterface;
use Salient\Core\Event\EventDispatcher;

/**
 * A facade for EventDispatcherInterface
 *
 * @method static object dispatch(object $event) Dispatch a given event to listeners registered for it (see {@see EventDispatcherInterface::dispatch()})
 * @method static iterable<callable(object): mixed> getListenersForEvent(object $event) Get listeners registered for a given event (see {@see EventListenerProviderInterface::getListenersForEvent()})
 * @method static int listen(callable(object): mixed $listener, string[]|string|null $event = null) Register a listener for a given event or list of events (see {@see EventListenerProviderInterface::listen()})
 * @method static void removeListener(int $id) Deregister an event listener with a given listener ID (see {@see EventListenerProviderInterface::removeListener()})
 *
 * @api
 *
 * @extends Facade<EventDispatcherInterface>
 *
 * @generated
 */
final class Event extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            EventDispatcherInterface::class,
            EventDispatcher::class,
        ];
    }
}
