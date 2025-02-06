<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Core\Event\EventDispatcher;

/**
 * A facade for EventDispatcher
 *
 * @method static object dispatch(object $event) Dispatch an event to listeners registered to receive it (see {@see EventDispatcher::dispatch()})
 * @method static array<callable(object): mixed> getListenersForEvent(object $event) See {@see EventDispatcher::getListenersForEvent()}
 * @method static int listen(callable(object): mixed $listener, string[]|string|null $event = null) Register an event listener with the dispatcher (see {@see EventDispatcher::listen()})
 * @method static void removeListener(int $id) Remove an event listener from the dispatcher (see {@see EventDispatcher::removeListener()})
 *
 * @api
 *
 * @extends Facade<EventDispatcher>
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
        return EventDispatcher::class;
    }
}
