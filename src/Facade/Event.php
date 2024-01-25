<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\EventDispatcher;
use Psr\EventDispatcher\ListenerProviderInterface;
use Generator;

/**
 * A facade for \Lkrms\Support\EventDispatcher
 *
 * @method static EventDispatcher load(?ListenerProviderInterface $listenerProvider = null) Load and return an instance of the underlying EventDispatcher class
 * @method static EventDispatcher getInstance() Get the underlying EventDispatcher instance
 * @method static bool isLoaded() True if an underlying EventDispatcher instance has been loaded
 * @method static void unload() Clear the underlying EventDispatcher instance
 * @method static object dispatch(object $event) Dispatch an event to listeners registered to receive it (see {@see EventDispatcher::dispatch()})
 * @method static Generator<callable(object): mixed> getListenersForEvent(object $event) See {@see EventDispatcher::getListenersForEvent()}
 * @method static int listen(callable(object): mixed $listener, string[]|string|null $event = null) Register an event listener with the dispatcher (see {@see EventDispatcher::listen()})
 * @method static void removeListener(int $id) Remove an event listener from the dispatcher (see {@see EventDispatcher::removeListener()})
 *
 * @extends Facade<EventDispatcher>
 *
 * @generated
 */
final class Event extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getService(): string
    {
        return EventDispatcher::class;
    }
}
